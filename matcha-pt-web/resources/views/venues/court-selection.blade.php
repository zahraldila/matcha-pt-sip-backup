@extends('layouts.app')

@section('content')
@php $courtList = $venue->courts->values(); @endphp
<div class="min-h-screen bg-slate-50 pb-32 md:pb-28">
  <div class="mx-auto max-w-[1000px] px-4 py-5 sm:px-6 lg:px-8">

    {{-- ===== LANGKAH 1: PILIH ===== --}}
    <div id="step-select">
      <a href="{{ route('venues.show', $venue->venue_id) }}" class="mb-5 inline-flex items-center gap-3 text-lg font-black text-slate-900"><span class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white"><i class="fa-solid fa-chevron-left text-xs"></i></span>Pilih Court</a>

      <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-wrap items-center gap-2">
          <div id="date-strip" class="flex flex-wrap items-center gap-2"></div>
          <label class="ml-auto flex h-12 items-center gap-2 rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-600"><i class="fa-regular fa-calendar"></i><input id="date-picker" type="date" class="bg-transparent text-xs font-bold outline-none"></label>
        </div>
        <h2 id="month-label" class="sr-only"></h2>
      </section>

      <section class="mt-7">
        <h2 class="mb-3 text-sm font-black text-slate-900">Daftar Ketersediaan Lapangan / Court</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @forelse($courtList as $court)
          @php
            $price = (float) ($court->harga_per_jam ?? 0);
            $photo = $court->image_url ?: $venue->main_photo;
            $tags = array_values(array_filter([$court->sport?->nama_sport, $court->tipe_court ?: 'Indoor', $venue->jenis_permukaan ?? null]));
          @endphp
          <button type="button" data-court-id="{{ $court->court_id }}" data-court-name="{{ $court->nama_court }}" data-price="{{ $price }}" class="court-card flex flex-col rounded-2xl border border-slate-200 bg-white p-3 text-left shadow-sm transition">
            <div class="aspect-[16/10] w-full overflow-hidden rounded-xl bg-slate-100"><img src="{{ $photo }}" alt="{{ $court->nama_court }}" class="h-full w-full object-cover"></div>
            <h3 class="mt-3 truncate text-sm font-black text-slate-900">{{ $court->nama_court }}</h3>
            <div class="mt-1 flex flex-wrap gap-1">
              @foreach($tags as $tag)<span class="rounded-md bg-[#eef6e7] px-2 py-0.5 text-[9px] font-black text-[#063B00]">{{ $tag }}</span>@endforeach
            </div>
            <p class="mt-2 text-xs font-black text-[#063B00]">Rp {{ number_format($price, 0, ',', '.') }} <span class="text-[10px] font-normal text-slate-400">/ jam</span></p>
            <span class="mt-3 flex h-10 items-center justify-between rounded-lg bg-[#063B00] px-3 text-[11px] font-black text-white"><span class="court-count">Memuat...</span><i class="fa-solid fa-chevron-right text-[10px]"></i></span>
          </button>
        @empty
          <div class="col-span-full rounded-2xl border border-dashed p-8 text-center text-sm text-slate-500">Belum ada court tersedia.</div>
        @endforelse
        </div>
      </section>

      <section id="slot-section" class="mt-7 hidden rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
        <div class="mb-3 flex items-end justify-between gap-3"><div><h2 class="text-sm font-black text-slate-900">Pilih Jam &middot; <span id="slot-court-name"></span></h2><p id="selected-date-label" class="mt-1 text-xs text-slate-500">-</p></div><strong id="hourly-price" class="text-sm text-[#063B00]"></strong></div>
        <div class="mb-3 flex flex-wrap gap-3 text-[10px] font-bold text-slate-500"><span><i class="fa-solid fa-circle-check mr-1 text-[#063B00]"></i>Tersedia</span><span><i class="fa-solid fa-ban mr-1"></i>Sudah dibooking</span><span><i class="fa-solid fa-clock mr-1"></i>Sudah lewat</span></div>
        <div id="slot-grid" class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6"></div>
      </section>
    </div>

    {{-- ===== LANGKAH 2: RINGKASAN BOOKING ===== --}}
    <div id="step-summary" class="hidden">
      <button id="back-to-select" type="button" class="mb-5 inline-flex items-center gap-3 text-lg font-black text-slate-900"><span class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white"><i class="fa-solid fa-chevron-left text-xs"></i></span>Ringkasan Booking</button>

      <div id="review-panel" class="mx-auto max-w-xl space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-sm font-black text-slate-900">Detail Pesanan</h2>
          <div class="mt-4 space-y-3 text-xs">
            <div class="flex justify-between gap-3"><span class="text-slate-500">Venue</span><strong class="text-right">{{ $venue->nama_venue }}</strong></div>
            <div class="flex justify-between gap-3"><span class="text-slate-500">Court</span><strong id="sum-court" class="text-right"></strong></div>
            <div class="flex justify-between gap-3"><span class="text-slate-500">Tanggal</span><strong id="sum-date" class="text-right"></strong></div>
            <div class="flex justify-between gap-3"><span class="text-slate-500">Jam</span><strong id="sum-time" class="text-right"></strong></div>
            <div class="flex justify-between gap-3"><span class="text-slate-500">Durasi</span><strong id="sum-duration" class="text-right"></strong></div>
            <div class="flex justify-between gap-3"><span class="text-slate-500">Rincian harga</span><strong id="sum-price" class="text-right"></strong></div>
          </div>
          <div class="mt-4 flex items-end justify-between border-t pt-4"><span class="text-xs text-slate-500">Total</span><strong id="sum-total" class="text-xl text-[#063B00]"></strong></div>
        </div>

        <form id="booking-form" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-sm font-black text-slate-900">Data Pemesan</h2>
          <p class="rounded-xl bg-slate-50 p-3 text-xs"><strong>Metode pembayaran:</strong> Bayar di venue</p>
          <label class="block text-xs font-bold">Nama Lengkap<input name="customer_name" value="{{ auth()->user()->nama ?? '' }}" required class="mt-2 h-11 w-full rounded-xl border border-slate-200 px-3"></label>
          <label class="block text-xs font-bold">No. WhatsApp<input name="customer_phone" value="{{ auth()->user()->no_hp ?? '' }}" required class="mt-2 h-11 w-full rounded-xl border border-slate-200 px-3"></label>
          <label class="flex items-start gap-2 text-xs text-slate-600"><input id="accept-terms" type="checkbox" class="mt-0.5"><span>Saya setuju dengan syarat booking singkat venue.</span></label>
          <div class="grid grid-cols-2 gap-3 pt-2">
            <button id="cancel-booking" type="button" class="h-12 rounded-xl border border-slate-300 text-sm font-black text-slate-700">Batalkan Booking</button>
            <button id="confirm-button" type="submit" disabled class="h-12 rounded-xl bg-[#063B00] text-sm font-black text-white disabled:bg-slate-300">Booking</button>
          </div>
        </form>
      </div>

      <div id="success-panel" class="mx-auto hidden max-w-xl rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#eef6e7] text-2xl text-[#063B00]"><i class="fa-solid fa-check"></i></div>
        <h2 class="mt-4 text-xl font-black">Booking Terkonfirmasi</h2>
        <p id="booking-code" class="mt-2 text-sm font-bold text-slate-500"></p>
        <a id="create-game-link" href="#" class="mt-5 flex h-12 items-center justify-center rounded-xl bg-[#063B00] text-sm font-black text-white">Buat Game</a>
        <a href="{{ url('/player/bookings') }}" class="mt-2 flex h-10 items-center justify-center text-xs font-bold text-slate-500">Lihat Booking Saya</a>
      </div>
    </div>
  </div>
</div>

{{-- Bar bawah: muncul setelah court, tanggal, dan jam dipilih --}}
<div id="continue-bar" class="fixed inset-x-0 bottom-0 z-40 hidden border-t border-slate-200 bg-white/95 backdrop-blur">
  <div class="mx-auto flex max-w-[1000px] items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
    <div class="min-w-0 text-xs"><p id="bar-line" class="truncate font-bold text-slate-900"></p><p class="text-slate-500">Total <strong id="bar-total" class="text-[#063B00]"></strong></p></div>
    <button id="continue-booking" type="button" class="h-12 shrink-0 rounded-xl bg-[#063B00] px-6 text-sm font-black text-white">Lanjutkan Booking</button>
  </div>
</div>

<div id="login-prompt" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-950/40 p-4"><div class="w-full max-w-sm rounded-3xl bg-white p-6 text-center shadow-2xl"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#eef6e7] text-xl text-[#063B00]"><i class="fa-solid fa-user-lock"></i></div><h2 class="mt-4 text-lg font-black text-slate-900">Login dulu, yuk</h2><p class="mt-2 text-xs text-slate-500">Untuk melanjutkan booking, silakan login atau daftar akun terlebih dahulu.</p><a href="{{ Route::has('login') ? route('login') : url('/login') }}" class="mt-5 flex h-12 items-center justify-center rounded-xl bg-[#063B00] text-sm font-black text-white">Login</a><a href="{{ Route::has('register') ? route('register') : url('/register') }}" class="mt-2 flex h-12 items-center justify-center rounded-xl border border-slate-200 text-sm font-black text-slate-700">Daftar Akun</a><button id="close-login-prompt" type="button" class="mt-2 h-10 text-xs font-bold text-slate-500">Nanti saja</button></div></div>

<script>
(() => {
 const $=id=>document.getElementById(id);
 const isLoggedIn=@json(auth()->check());
 const strip=$('date-strip'), slotGrid=$('slot-grid'), loginPrompt=$('login-prompt');
 const cards=[...document.querySelectorAll('.court-card')];
 const today=new Date(); today.setHours(0,0,0,0);
 const dayNames=['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
 let selected=new Date(today), courtId=null, courtName='', price=0, chosen=[]; let cache={};
 const iso=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
 const money=n=>'Rp '+Number(n).toLocaleString('id-ID');
 const isPast=slot=>{if(iso(selected)!==iso(today))return false;const n=new Date();return slot.start<`${String(n.getHours()).padStart(2,'0')}:${String(n.getMinutes()).padStart(2,'0')}`};
 const isBlocked=slot=>['Terisi','Terlewat','Sudah dibooking','Jadwal Saya'].includes(slot.status)||isPast(slot);
 const show=(el,on)=>el.classList.toggle('hidden',!on);
 function showLoginPrompt(){loginPrompt.classList.replace('hidden','flex')}
 $('close-login-prompt').onclick=()=>loginPrompt.classList.replace('flex','hidden');

 function renderStrip(){strip.innerHTML='';const months=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];for(let i=0;i<6;i++){const d=new Date(today);d.setDate(today.getDate()+i);const active=iso(d)===iso(selected);const b=document.createElement('button');b.type='button';b.className=`flex h-12 min-w-[64px] flex-col items-center justify-center rounded-xl px-3 text-center ${active?'bg-[#063B00] text-white':'bg-slate-50 text-slate-700 hover:bg-slate-100'}`;b.innerHTML=`<span class="text-[9px] font-bold ${active?'text-white/80':'text-slate-400'}">${dayNames[d.getDay()]}</span><strong class="text-[11px]">${d.getDate()} ${months[d.getMonth()]}</strong>`;b.onclick=()=>setDate(d);strip.appendChild(b)}const dp=$('date-picker');if(dp){dp.min=iso(today);dp.value=iso(selected)}$('month-label').textContent=selected.toLocaleDateString('id-ID',{month:'long',year:'numeric'})}

 async function fetchSlots(id,date){const k=id+'|'+date;if(cache[k])return cache[k];try{const res=await fetch(`{{ url('/venues/courts') }}/${id}/booking-slots?date=${date}`,{headers:{Accept:'application/json'}});const data=await res.json();cache[k]=data.slots||[];return cache[k]}catch(e){return null}}

 function updateCounts(){const date=iso(selected);cards.forEach(card=>{const label=card.querySelector('.court-count');label.textContent='Memuat...';fetchSlots(card.dataset.courtId,date).then(s=>{if(date!==iso(selected))return;label.textContent=s?`${s.filter(x=>x.status==='Tersedia'&&!isPast(x)).length} Jadwal Tersedia`:'Gagal memuat'})})}

 async function renderSlots(){if(!courtId)return;const date=iso(selected),id=courtId;slotGrid.innerHTML='<p class="col-span-full py-8 text-center text-xs text-slate-400">Memuat jadwal...</p>';const slots=await fetchSlots(id,date);if(id!==courtId||date!==iso(selected))return;if(!slots){slotGrid.innerHTML='<p class="col-span-full py-8 text-center text-xs text-red-500">Gagal memuat jadwal.</p>';return}if(!slots.length){slotGrid.innerHTML='<p class="col-span-full py-8 text-center text-xs text-slate-400">Venue tutup pada tanggal ini.</p>';return}slotGrid.innerHTML='';slots.forEach(slot=>{const blocked=isBlocked(slot);const active=chosen.some(s=>s.start===slot.start);const label=active?'Dipilih':slot.status==='Jadwal Saya'?'Jadwal saya':isPast(slot)||slot.status==='Terlewat'?'Sudah lewat':slot.status==='Terisi'||slot.status==='Sudah dibooking'?'Sudah dibooking':'Tersedia';const b=document.createElement('button');b.type='button';b.disabled=blocked;b.className=`h-16 rounded-xl border px-2 text-center text-xs ${blocked?'cursor-not-allowed border-slate-100 bg-slate-100 text-slate-400':active?'border-[#063B00] bg-[#063B00] text-white':'border-slate-200 bg-white text-slate-700'}`;b.innerHTML=`<strong class="block">${slot.start}-${slot.end}</strong><span class="mt-1 block text-[9px]">${label}</span>`;b.onclick=()=>toggleSlot(slot);slotGrid.appendChild(b)})}

 function selectCourt(card){cards.forEach(c=>c.classList.remove('ring-2','ring-[#063B00]'));card.classList.add('ring-2','ring-[#063B00]');courtId=card.dataset.courtId;courtName=card.dataset.courtName;price=Number(card.dataset.price||0);$('slot-court-name').textContent=courtName;$('hourly-price').textContent=money(price)+' / jam';chosen=[];show($('slot-section'),true);renderSlots();updateBar();$('slot-section').scrollIntoView({behavior:'smooth',block:'nearest'})}
 cards.forEach(c=>c.onclick=()=>selectCourt(c));

 function setDate(d){selected=new Date(d);selected.setHours(0,0,0,0);chosen=[];renderStrip();$('selected-date-label').textContent=selected.toLocaleDateString('id-ID',{weekday:'long',day:'numeric',month:'long'});updateCounts();renderSlots();updateBar()}

 function toggleSlot(slot){const ix=chosen.findIndex(s=>s.start===slot.start);if(ix>=0)chosen.splice(ix,1);else{const next=[...chosen,slot].sort((a,b)=>a.start.localeCompare(b.start));const ok=next.every((s,i)=>i===0||s.start===next[i-1].end);if(!ok){alert('Pilih jam yang berurutan.');return}chosen.push(slot)}renderSlots();updateBar()}

 function updateBar(){chosen.sort((a,b)=>a.start.localeCompare(b.start));const ready=courtId&&chosen.length;show($('continue-bar'),!!ready);if(!ready)return;$('bar-line').textContent=`${courtName} · ${selected.toLocaleDateString('id-ID',{day:'numeric',month:'short'})} · ${chosen[0].start}-${chosen.at(-1).end}`;$('bar-total').textContent=money(price*chosen.length)}

 function goSummary(){if(!chosen.length)return;if(!isLoggedIn){showLoginPrompt();return}chosen.sort((a,b)=>a.start.localeCompare(b.start));$('sum-court').textContent=courtName;$('sum-date').textContent=selected.toLocaleDateString('id-ID',{weekday:'long',day:'numeric',month:'long',year:'numeric'});$('sum-time').textContent=`${chosen[0].start} - ${chosen.at(-1).end}`;$('sum-duration').textContent=`${chosen.length} jam`;$('sum-price').textContent=`${money(price)} x ${chosen.length}`;$('sum-total').textContent=money(price*chosen.length);show($('step-select'),false);show($('continue-bar'),false);show($('step-summary'),true);window.scrollTo({top:0,behavior:'smooth'})}
 function backToSelect(){show($('step-summary'),false);show($('step-select'),true);updateBar()}

 $('continue-booking').onclick=goSummary;
 $('back-to-select').onclick=backToSelect;
 $('cancel-booking').onclick=()=>{chosen=[];$('accept-terms').checked=false;$('confirm-button').disabled=true;backToSelect();renderSlots();updateBar()};
 $('accept-terms').onchange=e=>$('confirm-button').disabled=!e.target.checked;

 $('booking-form').onsubmit=async e=>{e.preventDefault();const btn=$('confirm-button');btn.disabled=true;const fd=new FormData(e.target);chosen.sort((a,b)=>a.start.localeCompare(b.start));try{const hold=await fetch(`{{ route('venues.bookings.store') }}`,{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json','Content-Type':'application/json'},body:JSON.stringify({court_id:Number(courtId),booking_date:iso(selected),start_time:chosen[0].start,end_time:chosen.at(-1).end,customer_name:fd.get('customer_name'),customer_phone:fd.get('customer_phone')})});if(hold.status===401||hold.status===419){showLoginPrompt();btn.disabled=false;return}const hd=await hold.json();if(!hold.ok)throw new Error(hd.message||hd.errors?.slot?.[0]||'Gagal menahan slot');const confirm=await fetch(`{{ url('/venues/bookings') }}/${hd.booking.booking_id}/confirm`,{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}});const cd=await confirm.json();if(!confirm.ok)throw new Error(cd.message||cd.errors?.slot?.[0]||'Slot baru saja diambil orang lain');cache={};show($('review-panel'),false);show($('success-panel'),true);$('booking-code').textContent=cd.booking.booking_code;$('create-game-link').href=`{{ url('/sessions/create') }}?venue_id={{ $venue->venue_id }}&court_id=${courtId}&booking_id=${hd.booking.booking_id}`}catch(err){alert(err.message);btn.disabled=!$('accept-terms').checked}};

 const dp=$('date-picker');if(dp)dp.onchange=e=>{if(e.target.value){const [y,m,dd]=e.target.value.split('-').map(Number);setDate(new Date(y,m-1,dd))}};renderStrip();setDate(selected);
})();
</script>
@endsection