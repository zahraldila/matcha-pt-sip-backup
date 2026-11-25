-- Venue booking schema for existing MATCHA Supabase database
-- Compatible with tb_booking / tb_booking_slot created by Laravel.
-- Run this manually in Supabase SQL Editor. It does not create a new project.

begin;

alter table public.tb_booking
  add column if not exists expires_at timestamptz;

alter table public.tb_booking
  alter column payment_method set default 'cash';
alter table public.tb_booking
  alter column payment_status set default 'unpaid';

-- Existing values are normalized to the booking status contract.
update public.tb_booking
set booking_status = 'confirmed'
where booking_status is null or lower(trim(booking_status)) = 'confirmed';

alter table public.tb_booking
  drop constraint if exists tb_booking_status_check;
alter table public.tb_booking
  add constraint tb_booking_status_check
  check (booking_status in ('pending', 'confirmed', 'cancelled', 'expired'));

alter table public.tb_booking
  drop constraint if exists tb_booking_payment_method_check;
alter table public.tb_booking
  add constraint tb_booking_payment_method_check
  check (payment_method in ('cash', 'qris', 'bank_transfer', 'ewallet'));

create index if not exists tb_booking_user_status_idx
  on public.tb_booking (user_id, booking_status);
create index if not exists tb_booking_court_date_status_idx
  on public.tb_booking (court_id, booking_date, booking_status);
create index if not exists tb_booking_expires_idx
  on public.tb_booking (expires_at)
  where booking_status = 'pending';

create index if not exists tb_booking_slot_court_date_idx
  on public.tb_booking_slot (court_id, slot_date, slot_start);

-- Unique court/date/start prevents direct double booking. Keep only active rows;
-- cancellation removes slot rows in application code, so the unique key is enough.
create unique index if not exists tb_booking_slot_court_date_start_unique
  on public.tb_booking_slot (court_id, slot_date, slot_start);

-- Expire holds automatically whenever the status is read or a booking is created.
create or replace function public.expire_venue_booking_holds()
returns void
language sql
security definer
set search_path = public
as $$
  update public.tb_booking
  set booking_status = 'expired', expires_at = expires_at
  where booking_status = 'pending'
    and expires_at is not null
    and expires_at <= now();

  delete from public.tb_booking_slot
  where booking_id in (
    select booking_id from public.tb_booking
    where booking_status = 'expired'
  );
$$;

-- Atomic booking confirmation/check. It must be called with service_role or by
-- a server function; it rechecks every slot and creates the booking transactionally.
create or replace function public.confirm_venue_booking(
  p_user_id bigint,
  p_court_id bigint,
  p_booking_date date,
  p_start_time time,
  p_end_time time,
  p_customer_name text,
  p_customer_phone text,
  p_hourly_price numeric
)
returns bigint
language plpgsql
security definer
set search_path = public
as $$
declare
  v_duration integer;
  v_cursor time;
  v_booking_id bigint;
  v_sequence integer;
  v_code text;
  v_total numeric;
begin
  perform public.expire_venue_booking_holds();

  if p_start_time >= p_end_time then
    raise exception 'INVALID_TIME_RANGE';
  end if;

  v_duration := extract(epoch from (p_end_time - p_start_time)) / 3600;
  if v_duration <= 0 or mod(extract(epoch from (p_end_time - p_start_time))::integer, 3600) <> 0 then
    raise exception 'INVALID_SLOT_DURATION';
  end if;

  v_cursor := p_start_time;
  while v_cursor < p_end_time loop
    if exists (
      select 1
      from public.tb_booking_slot bs
      join public.tb_booking b on b.booking_id = bs.booking_id
      where bs.court_id = p_court_id
        and bs.slot_date = p_booking_date
        and bs.slot_start = v_cursor
        and b.booking_status in ('pending', 'confirmed')
        and (b.booking_status = 'confirmed' or b.expires_at > now())
    ) then
      raise exception 'SLOT_ALREADY_BOOKED';
    end if;
    v_cursor := v_cursor + interval '1 hour';
  end loop;

  v_total := p_hourly_price * v_duration;
  select count(*) + 1 into v_sequence
  from public.tb_booking
  where booking_date = p_booking_date;
  v_code := 'MT-' || to_char(p_booking_date, 'YYYYMMDD') || '-' || lpad(v_sequence::text, 3, '0');

  insert into public.tb_booking (
    booking_code, user_id, court_id, booking_date, start_time, end_time,
    duration_hours, total_price, customer_name, customer_phone,
    booking_status, payment_method, payment_status, expires_at
  ) values (
    v_code, p_user_id, p_court_id, p_booking_date, p_start_time, p_end_time,
    v_duration, v_total, p_customer_name, p_customer_phone,
    'confirmed', 'cash', 'unpaid', null
  ) returning booking_id into v_booking_id;

  v_cursor := p_start_time;
  while v_cursor < p_end_time loop
    insert into public.tb_booking_slot (booking_id, court_id, slot_date, slot_start)
    values (v_booking_id, p_court_id, p_booking_date, v_cursor);
    v_cursor := v_cursor + interval '1 hour';
  end loop;

  return v_booking_id;
end;
$$;

-- RLS: direct client access is conservative. The Laravel/server role may use
-- service_role; Supabase JWT can optionally carry bigint user_id as app_user_id.
-- Adjust app_user_id mapping in this policy if your Supabase auth is integrated.
alter table public.tb_booking enable row level security;
alter table public.tb_booking_slot enable row level security;

revoke all on table public.tb_booking from anon, authenticated;
revoke all on table public.tb_booking_slot from anon, authenticated;

create policy tb_booking_select_owner on public.tb_booking
  for select
  to authenticated
  using (
    nullif(current_setting('request.jwt.claim.app_user_id', true), '')::bigint = user_id
  );

create policy tb_booking_slot_select_owner on public.tb_booking_slot
  for select
  to authenticated
  using (
    exists (
      select 1 from public.tb_booking b
      where b.booking_id = tb_booking_slot.booking_id
        and nullif(current_setting('request.jwt.claim.app_user_id', true), '')::bigint = b.user_id
    )
  );

commit;
