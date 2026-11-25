@extends('layouts.app')

@section('content')
@php $courtList = $venue->courts->values(); @endphp
<div class="min-h-screen bg-slate-50 pb-28 md:pb-10">
  <div class="mx-auto max-w-[1180px] px-4 py-5 sm:px-6 lg:px-8">
    <a href="{{ route('venues.show', $venue->venue_id) }}" class="mb-5 inline-flex h-10 items-center gap-2 text-sm font-bold text-slate-600"><i class="fa-solid fa-arrow-left"></i>Kembali ke Venue</a>
    <div class="mb-5"><p class="text-xs font-black text-[#063B00]">Sewa Lapangan</p><h1 class="mt-1 text-2xl font-black text-slate-900 md:text-3xl">Pilih Court & Jadwal</h1></div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
      <main class="min-w-0 space-y-6">
        <section><h2 class="mb-3 text-sm font-black text-slate-900">Pilih Court</h2>
          <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
          @forelse($courtList as $i => $court)
            @php $price=(float)($court->harga_per_jam ?? 0); $photo=$court->image_url ?: $venue->main_photo; @endphp
            <button type="button" data-court-id="{{ $court->court_id }}" data-court-name="{{ $court->nama_court }}" data-price="{{ $price }}" class="court-card min-h-[188px] rounded-2xl border border-slate-200 bg-white p-3 text-left shadow-sm {{ $i===0?'ring-2 ring-[#063B00]':'' }}">
              <div class="h-20 overflow-hidden rounded-xl bg-slate-100"><img src="{{ $photo }}" alt="{{ $court->nama_court }}" class="h-full w-full object-cover"></div>
              <h3 class="mt-3 truncate text-sm font-black text-slate-900">{{ $court->nama_court }}</h3>
              <p class="mt-1 text-xs text-slate-500">{{ $court->tipe_court ?: 'Indoor' }}</p>
              <p class="mt-3 text-sm font-black text-[#063B00]">Rp {{ number_format($price,0,',','.') }} <span class="text-[10px] text-slate-400">/ jam</span></p>
            </button>
          @empty
            <div class="col-span-full rounded-2xl border border-dashed p-8 text-center text-sm text-slate-500">Belum ada court tersedia.</div>
          @endforelse
          </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
          <div class="mb-4 flex items-center justify-between gap-3"><h2 class="text-sm font-black text-slate-900">Pilih Tanggal & Jam</h2><div id="month-label" class="text-xs font-bold text-slate-500"></div></div>
          <div class="grid gap-5 xl:grid-cols-[minmax(320px,420px)_1fr]">
            <div>
              <div class="flex items-center justify-between"><button id="prev-month" type="button" class="h-11 w-11 rounded-xl border border-slate-200" aria-label="Bulan sebelumnya"><i class="fa-solid fa-chevron-left"></i></button><strong id="calendar-title"></strong><button id="next-month" type="button" class="h-11 w-11 rounded-xl border border-slate-200" aria-label="Bulan berikutnya"><i class="fa-solid fa-chevron-right"></i></button></div>
              <div class="venue-calendar-grid mt-3 mb-1" aria-label="Nama hari">
                <span class="venue-calendar-weekday">Min</span>
                <span class="venue-calendar-weekday">Sen</span>
                <span class="venue-calendar-weekday">Sel</span>
                <span class="venue-calendar-weekday">Rab</span>
                <span class="venue-calendar-weekday">Kam</span>
                <span class="venue-calendar-weekday">Jum</span>
                <span class="venue-calendar-weekday">Sab</span>
              </div>
              <div id="calendar-grid" class="venue-calendar-grid mt-1" aria-label="Kalender bulanan"></div>
              <div class="mt-3 grid grid-cols-2 gap-2"><button id="today-btn" type="button" class="h-11 rounded-xl border border-slate-200 text-xs font-bold">Hari ini</button><button id="tomorrow-btn" type="button" class="h-11 rounded-xl border border-slate-200 text-xs font-bold">Besok</button></div>
            </div>
            <div class="min-w-0">
              <div class="mb-3 flex items-end justify-between gap-3"><div><p class="text-xs text-slate-500">Jadwal</p><strong id="selected-date-label" class="text-sm text-slate-900">-</strong></div><strong id="hourly-price" class="text-sm text-[#063B00]"></strong></div>
              <div class="mb-3 flex flex-wrap gap-3 text-[10px] font-bold text-slate-500"><span><i class="fa-solid fa-circle-check mr-1 text-[#063B00]"></i>Tersedia</span><span><i class="fa-solid fa-ban mr-1"></i>Sudah dibooking</span><span><i class="fa-solid fa-clock mr-1"></i>Sudah lewat</span></div>
              <div id="slot-grid" class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4"></div>
            </div>
          </div>
        </section>
      </main>

      <aside><div class="lg:sticky lg:top-24 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-sm font-black text-slate-900">Ringkasan Booking</h2><div class="mt-4 space-y-3 text-xs"><div class="flex justify-between gap-3"><span class="text-slate-500">Venue</span><strong class="text-right">{{ $venue->nama_venue }}</strong></div><div class="flex justify-between gap-3"><span class="text-slate-500">Court</span><strong id="summary-court" class="text-right"></strong></div><div class="flex justify-between gap-3"><span class="text-slate-500">Tanggal</span><strong id="summary-date" class="text-right">-</strong></div><div class="flex justify-between gap-3"><span class="text-slate-500">Jam</span><strong id="summary-time" class="text-right">Belum dipilih</strong></div><div class="flex justify-between gap-3"><span class="text-slate-500">Durasi</span><strong id="summary-duration" class="text-right">0 jam</strong></div></div><div class="mt-5 border-t pt-4"><div class="flex items-end justify-between"><span class="text-xs text-slate-500">Total</span><strong id="summary-total" class="text-xl text-[#063B00]">Rp 0</strong></div><button id="continue-booking" disabled class="mt-4 h-12 w-full rounded-xl bg-[#063B00] text-sm font-black text-white disabled:bg-slate-300">Lanjut</button></div></div></aside>
    </div>
  </div>
</div>

<div id="booking-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-950/40 p-4"><div class="max-h-[92vh] w-full max-w-md overflow-y-auto rounded-3xl bg-white p-5 shadow-2xl"><div id="review-panel"><h2 class="text-xl font-black">Rincian Booking</h2><div class="mt-4 space-y-3 rounded-2xl border border-slate-200 p-4 text-xs"><div class="flex justify-between"><span class="text-slate-500">Venue / Court</span><strong id="detail-court" class="text-right"></strong></div><div class="flex justify-between"><span class="text-slate-500">Tanggal & jam</span><strong id="detail-time" class="text-right"></strong></div><div class="flex justify-between"><span class="text-slate-500">Durasi</span><strong id="detail-duration"></strong></div><div class="flex justify-between border-t pt-3"><span class="font-bold">Rincian harga</span><strong id="detail-price" class="text-right"></strong></div></div><form id="booking-form" class="mt-4 space-y-3"><p class="rounded-xl bg-slate-50 p-3 text-xs"><strong>Metode pembayaran:</strong> Bayar di venue</p><label class="block text-xs font-bold">Nama Lengkap<input name="customer_name" value="{{ auth()->user()->nama ?? '' }}" required class="mt-2 h-11 w-full rounded-xl border border-slate-200 px-3"></label><label class="block text-xs font-bold">No. WhatsApp<input name="customer_phone" value="{{ auth()->user()->no_hp ?? '' }}" required class="mt-2 h-11 w-full rounded-xl border border-slate-200 px-3"></label><label class="flex items-start gap-2 text-xs text-slate-600"><input id="accept-terms" type="checkbox" class="mt-0.5"><span>Saya setuju dengan syarat booking singkat venue.</span></label><button id="confirm-button" disabled class="h-12 w-full rounded-xl bg-[#063B00] text-sm font-black text-white">Konfirmasi Booking</button></form></div><div id="success-panel" class="hidden py-4 text-center"><div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#eef6e7] text-2xl text-[#063B00]"><i class="fa-solid fa-check"></i></div><h2 class="mt-4 text-xl font-black">Booking Terkonfirmasi</h2><p id="booking-code" class="mt-2 text-sm font-bold text-slate-500"></p><a id="create-game-link" href="#" class="mt-5 flex h-12 items-center justify-center rounded-xl bg-[#063B00] text-sm font-black text-white">Buat Game</a><button id="close-success" type="button" class="mt-2 h-10 text-xs font-bold text-slate-500">Lihat Booking Saya</button></div></div></div>

<script>
(() => {
 const grid=document.getElementById('calendar-grid'), monthLabel=document.getElementById('month-label'), title=document.getElementById('calendar-title');
 let month=new Date(), selected=new Date(), courtId=null, courtName='', price=0, chosen=[];
 const today=new Date(); today.setHours(0,0,0,0);
 const iso=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
 const money=n=>'Rp '+Number(n).toLocaleString('id-ID');
 const cards=[...document.querySelectorAll('.court-card')];

 function selectCourt(card){cards.forEach(c=>c.classList.remove('ring-2','ring-[#063B00]'));card.classList.add('ring-2','ring-[#063B00]');courtId=card.dataset.courtId;courtName=card.dataset.courtName;price=Number(card.dataset.price||0);document.getElementById('summary-court').textContent=courtName;document.getElementById('hourly-price').textContent=money(price)+' / jam';chosen=[];loadSlots()}
 cards.forEach(c=>c.onclick=()=>selectCourt(c));if(cards[0])selectCourt(cards[0]);

 function renderCalendar(){title.textContent=month.toLocaleDateString('id-ID',{month:'long',year:'numeric'});monthLabel.textContent=title.textContent;grid.innerHTML='';const first=new Date(month.getFullYear(),month.getMonth(),1);const offset=(first.getDay()+6)%7;const days=new Date(month.getFullYear(),month.getMonth()+1,0).getDate();for(let i=0;i<offset;i++){const blank=document.createElement('span');blank.className='venue-calendar-day venue-calendar-empty';grid.appendChild(blank)}for(let day=1;day<=days;day++){const d=new Date(month.getFullYear(),month.getMonth(),day);d.setHours(0,0,0,0);const past=d<today;const active=d.getFullYear()==selected.getFullYear()&&d.getMonth()==selected.getMonth()&&d.getDate()==selected.getDate();const b=document.createElement('button');b.type='button';b.textContent=day;b.disabled=past;b.className='venue-calendar-day';if(past)b.classList.add('is-past');if(active)b.classList.add('is-selected');b.setAttribute('aria-current',active?'date':'false');b.onclick=()=>setDate(d);grid.appendChild(b)}}
 function setDate(d){selected=d;chosen=[];renderCalendar();document.getElementById('summary-date').textContent=d.toLocaleDateString('id-ID',{weekday:'short',day:'numeric',month:'short',year:'numeric'});document.getElementById('selected-date-label').textContent=d.toLocaleDateString('id-ID',{weekday:'long',day:'numeric',month:'long'});loadSlots();updateSummary()}
 document.getElementById('prev-month').onclick=()=>{month=new Date(month.getFullYear(),month.getMonth()-1,1);renderCalendar()};document.getElementById('next-month').onclick=()=>{month=new Date(month.getFullYear(),month.getMonth()+1,1);renderCalendar()};document.getElementById('today-btn').onclick=()=>setDate(new Date());document.getElementById('tomorrow-btn').onclick=()=>setDate(new Date(Date.now()+86400000));

 async function loadSlots(){if(!courtId)return;const slotGrid=document.getElementById('slot-grid');slotGrid.innerHTML='<p class="col-span-full py-8 text-center text-xs text-slate-400">Memuat jadwal...</p>';try{const res=await fetch(`{{ url('/venues/courts') }}/${courtId}/booking-slots?date=${iso(selected)}`,{headers:{Accept:'application/json'}});const data=await res.json();slotGrid.innerHTML='';(data.slots||[]).forEach(slot=>{const b=document.createElement('button');b.type='button';b.dataset.start=slot.start;b.dataset.end=slot.end;const disabled=slot.status==='Terisi'||slot.status==='Terlewat'||slot.status==='Sudah dibooking';const active=chosen.some(s=>s.start===slot.start);b.disabled=disabled;b.className=`h-16 rounded-xl border px-2 text-center text-xs ${disabled?'border-slate-100 bg-slate-100 text-slate-400 cursor-not-allowed':active?'border-[#063B00] bg-[#063B00] text-white':'border-slate-200 bg-white text-slate-700'}`;b.innerHTML=`<strong class="block ${slot.status==='Terisi'?'line-through':''}">${slot.start}-${slot.end}</strong><span class="mt-1 block text-[9px]">${active?'Dipilih':slot.status==='Terisi'?'Sudah dibooking':slot.status==='Terlewat'?'Sudah lewat':'Tersedia'}</span>`;b.onclick=()=>toggleSlot(slot,b);slotGrid.appendChild(b)})}catch(e){slotGrid.innerHTML='<p class="col-span-full py-8 text-center text-xs text-red-500">Gagal memuat jadwal.</p>'}}
 function toggleSlot(slot,b){const ix=chosen.findIndex(s=>s.start===slot.start);if(ix>=0)chosen.splice(ix,1);else{const next=[...chosen,slot].sort((a,b)=>a.start.localeCompare(b.start));const ok=next.every((s,i)=>i===0||s.start===next[i-1].end);if(!ok){alert('Pilih jam yang berurutan.');return}chosen.push(slot)}loadSlots();updateSummary()}
 function updateSummary(){chosen.sort((a,b)=>a.start.localeCompare(b.start));const time=chosen.length?`${chosen[0].start} - ${chosen.at(-1).end}`:'Belum dipilih';document.getElementById('summary-time').textContent=time;document.getElementById('summary-duration').textContent=`${chosen.length} jam`;document.getElementById('summary-total').textContent=money(price*chosen.length);document.getElementById('continue-booking').disabled=!chosen.length}

 setDate(selected);
 document.getElementById('continue-booking').onclick=()=>{if(!chosen.length)return;chosen.sort((a,b)=>a.start.localeCompare(b.start));document.getElementById('detail-court').textContent=`{{ $venue->nama_venue }} / ${courtName}`;document.getElementById('detail-time').textContent=`${selected.toLocaleDateString('id-ID',{day:'numeric',month:'short'})}, ${chosen[0].start}-${chosen.at(-1).end}`;document.getElementById('detail-duration').textContent=`${chosen.length} jam`;document.getElementById('detail-price').textContent=`${money(price)} x ${chosen.length} = ${money(price*chosen.length)}`;document.getElementById('booking-modal').classList.replace('hidden','flex')};
 document.getElementById('accept-terms').onchange=e=>document.getElementById('confirm-button').disabled=!e.target.checked;
 document.getElementById('booking-form').onsubmit=async e=>{e.preventDefault();const fd=new FormData(e.target);chosen.sort((a,b)=>a.start.localeCompare(b.start));try{const hold=await fetch(`{{ route('venues.bookings.store') }}`,{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json','Content-Type':'application/json'},body:JSON.stringify({court_id:Number(courtId),booking_date:iso(selected),start_time:chosen[0].start,end_time:chosen.at(-1).end,customer_name:fd.get('customer_name'),customer_phone:fd.get('customer_phone')})});const hd=await hold.json();if(!hold.ok)throw new Error(hd.message||hd.errors?.slot?.[0]||'Gagal menahan slot');const confirm=await fetch(`{{ url('/venues/bookings') }}/${hd.booking.booking_id}/confirm`,{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}});const cd=await confirm.json();if(!confirm.ok)throw new Error(cd.message||cd.errors?.slot?.[0]||'Slot baru saja diambil orang lain');document.getElementById('review-panel').classList.add('hidden');document.getElementById('success-panel').classList.remove('hidden');document.getElementById('booking-code').textContent=cd.booking.booking_code;document.getElementById('create-game-link').href=`{{ url('/sessions/create') }}?venue_id={{ $venue->venue_id }}&court_id=${courtId}&booking_id=${hd.booking.booking_id}`}catch(err){alert(err.message)}};
 document.getElementById('close-success').onclick=()=>location.reload();
})();
</script>
@endsection
