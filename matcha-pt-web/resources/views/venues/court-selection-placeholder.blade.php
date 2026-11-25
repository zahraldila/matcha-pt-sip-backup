@extends('layouts.app')

@section('content')
@php
    $courtList = $venue->courts->values();
    $mainCourt = $courtList->first();
@endphp
<div class="min-h-screen bg-[#f7f8f5] pb-28">
    <div class="mx-auto max-w-[1180px] px-4 py-5 sm:px-6 lg:px-8 xl:px-0">
        <a href="{{ route('venues.show', $venue->venue_id) }}" class="mb-5 inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-[#063B00]">
            <i class="fa-solid fa-arrow-left text-xs"></i> Kembali ke Venue
        </a>

        <div class="mb-5">
            <p class="text-xs font-bold text-[#7FAF25]">Sewa Lapangan</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-[#111827] md:text-3xl">Pilih Court & Jam</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $venue->nama_venue }}</p>
        </div>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_330px]">
            <main class="min-w-0">
                <section class="rounded-[20px] border border-slate-200 bg-white p-4 shadow-[0_8px_30px_rgba(15,23,42,.05)] sm:p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-black text-[#111827]">Pilih Tanggal</h2>
                        <span id="month-label" class="text-xs font-bold text-slate-500"></span>
                    </div>
                    <div id="date-strip" class="grid grid-cols-5 gap-2 sm:grid-cols-7"></div>
                </section>

                <section class="mt-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-black text-[#111827]">Pilihan Court</h2>
                        <button type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600">
                            <i class="fa-solid fa-filter mr-1"></i> Filter
                        </button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @forelse($courtList as $index => $court)
                            @php
                                $courtPhoto = $court->image_url ?: $venue->main_photo;
                                $sportName = $court->sport->nama_sport ?? 'Padel';
                            @endphp
                            <button type="button" data-court-id="{{ $court->court_id }}" data-court-name="{{ $court->nama_court }}" data-court-price="{{ (float) ($court->harga_per_jam ?? 0) }}" class="court-card text-left rounded-[18px] border border-slate-200 bg-white p-3 shadow-[0_4px_18px_rgba(15,23,42,.04)] transition {{ $index === 0 ? 'ring-2 ring-[#063B00]' : '' }}">
                                <div class="flex gap-3">
                                    <img src="{{ $courtPhoto }}" alt="{{ $court->nama_court }}" class="h-20 w-24 rounded-xl object-cover" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=400&q=80';">
                                    <div class="min-w-0 flex-1">
                                        <h3 class="truncate text-sm font-black text-[#111827]">{{ $court->nama_court }}</h3>
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            <span class="rounded-md bg-[#eef6e7] px-2 py-1 text-[10px] font-bold text-[#063B00]">{{ $sportName }}</span>
                                            <span class="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">{{ $court->tipe_court ?: 'Indoor' }}</span>
                                        </div>
                                        <p class="mt-3 text-xs font-black text-[#063B00]">Rp {{ number_format((float) ($court->harga_per_jam ?? 0), 0, ',', '.') }} <span class="font-semibold text-slate-400">/ jam</span></p>
                                    </div>
                                </div>
                            </button>
                        @empty
                            <div class="col-span-full rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">Belum ada court tersedia.</div>
                        @endforelse
                    </div>
                </section>

                @if($mainCourt)
                <section class="mt-5 rounded-[20px] border border-slate-200 bg-white p-4 shadow-[0_8px_30px_rgba(15,23,42,.05)] sm:p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-black text-[#111827]">Pilih Jam</h2>
                        <div class="flex flex-wrap justify-end gap-2 text-[9px] font-bold text-slate-500">
                            <span><i class="mr-1 h-2 w-2 inline-block rounded-full bg-[#eef6e7] border border-[#063B00]"></i>Tersedia</span>
                            <span><i class="mr-1 h-2 w-2 inline-block rounded-full bg-slate-300"></i>Terisi</span>
                            <span><i class="mr-1 h-2 w-2 inline-block rounded-full bg-[#063B00]"></i>Dipilih</span>
                            <span><i class="mr-1 h-2 w-2 inline-block rounded-full bg-[#C8F000]"></i>Jadwal Saya</span>
                        </div>
                    </div>
                    <div id="slot-grid" class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6"></div>
                </section>
                @endif
            </main>

            <aside class="lg:block">
                <div class="lg:sticky lg:top-24 rounded-[20px] border border-slate-200 bg-white p-5 shadow-[0_12px_35px_rgba(15,23,42,.08)]">
                    <h2 class="text-sm font-black text-[#111827]">Ringkasan Booking</h2>
                    <div class="mt-4 space-y-3 text-xs">
                        <div class="flex justify-between gap-3"><span class="text-slate-500">Venue</span><strong class="text-right text-slate-800">{{ $venue->nama_venue }}</strong></div>
                        <div class="flex justify-between gap-3"><span class="text-slate-500">Court</span><strong id="summary-court" class="text-right text-slate-800">{{ $mainCourt?->nama_court }}</strong></div>
                        <div class="flex justify-between gap-3"><span class="text-slate-500">Tanggal</span><strong id="summary-date" class="text-right text-slate-800">-</strong></div>
                        <div class="flex justify-between gap-3"><span class="text-slate-500">Waktu</span><strong id="summary-time" class="text-right text-slate-800">Belum dipilih</strong></div>
                    </div>
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <div class="flex items-end justify-between"><span class="text-xs font-bold text-slate-500">Total</span><strong id="summary-total" class="text-xl font-black text-[#063B00]">Rp 0</strong></div>
                        <button id="continue-booking" type="button" disabled class="mt-4 h-12 w-full rounded-xl bg-[#063B00] text-sm font-black text-white disabled:cursor-not-allowed disabled:bg-slate-300">
                            Lanjut
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<div id="booking-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm">
    <div class="max-h-[92vh] w-full max-w-md overflow-y-auto rounded-[24px] bg-white p-5 shadow-2xl">
        <div id="hold-banner" class="rounded-xl bg-[#edf6e4] px-4 py-3 text-xs font-bold text-[#063B00]">Slot ditahan 15 menit. Selesaikan sebelum waktu habis.</div>
        <h2 class="mt-5 text-xl font-black text-[#111827]">Rincian Booking</h2>
        <div class="mt-4 rounded-2xl border border-slate-200 p-4 text-xs">
            <div class="flex justify-between"><span class="text-slate-500">Tanggal</span><strong id="detail-date"></strong></div>
            <div class="mt-3 flex justify-between"><span class="text-slate-500">Venue / Court</span><strong id="detail-court" class="text-right"></strong></div>
            <div class="mt-3 flex justify-between"><span class="text-slate-500">Waktu</span><strong id="detail-time"></strong></div>
            <div class="mt-4 border-t border-slate-100 pt-4 flex justify-between"><span class="font-bold">Total</span><strong id="detail-total" class="text-lg text-[#063B00]"></strong></div>
        </div>
        <form id="booking-form" class="mt-5 space-y-3">
            @csrf
            <label class="block text-xs font-bold text-slate-700">Nama Lengkap<input name="customer_name" value="{{ auth()->user()->nama ?? '' }}" required class="mt-2 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-[#063B00]"></label>
            <label class="block text-xs font-bold text-slate-700">No. WhatsApp<input name="customer_phone" value="{{ auth()->user()->no_hp ?? '' }}" required class="mt-2 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-[#063B00]"></label>
            <div class="rounded-xl bg-slate-50 px-3 py-3 text-xs text-slate-600"><strong>Pembayaran:</strong> Bayar di Venue</div>
            <button type="submit" class="h-12 w-full rounded-xl bg-[#063B00] text-sm font-black text-white">Konfirmasi Booking</button>
            <button type="button" id="close-modal" class="h-10 w-full text-xs font-bold text-slate-500">Kembali</button>
        </form>
        <div id="success-panel" class="hidden text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#eef6e7] text-2xl text-[#063B00]"><i class="fa-solid fa-check"></i></div>
            <h2 class="mt-4 text-xl font-black">Booking Terkonfirmasi</h2>
            <p id="booking-code" class="mt-2 text-sm font-bold text-slate-500"></p>
            <a id="create-game-link" href="#" class="mt-5 flex h-12 items-center justify-center rounded-xl bg-[#063B00] text-sm font-black text-white">Buat Game dari Jadwal Ini</a>
            <button type="button" id="close-success" class="mt-2 h-10 text-xs font-bold text-slate-500">Lihat Booking Saya</button>
        </div>
    </div>
</div>

<script>
(() => {
  const firstCourt = document.querySelector('.court-card');
  let courtId = firstCourt?.dataset.courtId;
  let courtName = firstCourt?.dataset.courtName || '';
  let courtPrice = Number(firstCourt?.dataset.courtPrice || 0);
  let selectedDate = '';
  let selected = [];

  const fmt = n => 'Rp ' + Number(n).toLocaleString('id-ID');
  const dateStrip = document.getElementById('date-strip');
  const monthLabel = document.getElementById('month-label');
  const today = new Date();
  today.setHours(0,0,0,0);

  function iso(d) { return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'); }
  for (let i=0;i<14;i++) {
    const d = new Date(today); d.setDate(today.getDate()+i);
    const btn = document.createElement('button');
    btn.type='button'; btn.dataset.date=iso(d);
    btn.className='date-card rounded-xl border border-slate-200 bg-white px-2 py-3 text-center';
    btn.innerHTML=`<span class="block text-[10px] font-bold text-slate-400">${d.toLocaleDateString('id-ID',{weekday:'short'})}</span><strong class="mt-1 block text-sm text-slate-800">${d.getDate()}</strong>`;
    btn.onclick=()=>selectDate(btn, d);
    dateStrip.appendChild(btn);
  }

  function selectDate(btn, d) {
    document.querySelectorAll('.date-card').forEach(b=>b.classList.remove('ring-2','ring-[#063B00]','bg-[#eef6e7]'));
    btn.classList.add('ring-2','ring-[#063B00]','bg-[#eef6e7]');
    selectedDate=iso(d); selected=[];
    monthLabel.textContent=d.toLocaleDateString('id-ID',{month:'long',year:'numeric'});
    document.getElementById('summary-date').textContent=d.toLocaleDateString('id-ID',{weekday:'short',day:'numeric',month:'short'});
    loadSlots();
  }

  async function loadSlots() {
    if (!courtId || !selectedDate) return;
    const grid=document.getElementById('slot-grid'); grid.innerHTML='<p class="col-span-full py-6 text-center text-xs text-slate-400">Memuat jadwal...</p>';
    try {
      const res=await fetch(`{{ url('/venues/courts') }}/${courtId}/booking-slots?date=${selectedDate}`);
      const data=await res.json();
      grid.innerHTML='';
      (data.slots||[]).forEach(slot=>{
        const btn=document.createElement('button'); btn.type='button'; btn.dataset.start=slot.start; btn.dataset.end=slot.end;
        const unavailable=['Terisi','Terlewat'].includes(slot.status);
        btn.className='slot-button h-12 rounded-xl border text-xs font-black '+(unavailable?'border-slate-200 bg-slate-100 text-slate-400 cursor-not-allowed':slot.status==='Jadwal Saya'?'border-[#C8F000] bg-[#C8F000] text-[#063B00]':'border-slate-200 bg-white text-slate-700');
        btn.textContent=`${slot.start}`;
        btn.disabled=unavailable;
        btn.onclick=()=>toggleSlot(btn,slot);
        grid.appendChild(btn);
      });
      updateSummary();
    } catch(e) { grid.innerHTML='<p class="col-span-full py-6 text-center text-xs text-red-500">Gagal memuat jadwal.</p>'; }
  }

  function toggleSlot(btn,slot) {
    const idx=selected.findIndex(s=>s.start===slot.start);
    if(idx>=0) selected.splice(idx,1); else {
      const sorted=[...selected,slot].sort((a,b)=>a.start.localeCompare(b.start));
      const contiguous=sorted.every((s,i)=>i===0||s.start===sorted[i-1].end);
      if(!contiguous){alert('Pilih slot yang berurutan.');return;}
      selected.push(slot);
    }
    btn.classList.toggle('ring-2'); btn.classList.toggle('ring-[#063B00]'); btn.classList.toggle('bg-[#063B00]'); btn.classList.toggle('text-white');
    updateSummary();
  }

  function updateSummary() {
    if(selected.length===0){document.getElementById('summary-time').textContent='Belum dipilih';document.getElementById('summary-total').textContent='Rp 0';document.getElementById('continue-booking').disabled=true;return;}
    selected.sort((a,b)=>a.start.localeCompare(b.start));
    const end=selected[selected.length-1].end;
    document.getElementById('summary-time').textContent=`${selected[0].start} - ${end}`;
    document.getElementById('summary-total').textContent=fmt(courtPrice*selected.length);
    document.getElementById('continue-booking').disabled=false;
  }

  document.querySelectorAll('.court-card').forEach(card=>card.onclick=()=>{
    document.querySelectorAll('.court-card').forEach(c=>c.classList.remove('ring-2','ring-[#063B00]'));
    card.classList.add('ring-2','ring-[#063B00]'); courtId=card.dataset.courtId; courtName=card.dataset.courtName; courtPrice=Number(card.dataset.courtPrice||0); selected=[]; document.getElementById('summary-court').textContent=courtName; loadSlots();
  });

  document.querySelectorAll('.date-card')[0]?.click();

  const modal=document.getElementById('booking-modal');
  document.getElementById('continue-booking').onclick=()=>{
    if(!selected.length)return;
    selected.sort((a,b)=>a.start.localeCompare(b.start));
    document.getElementById('detail-date').textContent=document.getElementById('summary-date').textContent;
    document.getElementById('detail-court').textContent=`{{ $venue->nama_venue }} / ${courtName}`;
    document.getElementById('detail-time').textContent=`${selected[0].start} - ${selected[selected.length-1].end} (${selected.length} jam)`;
    document.getElementById('detail-total').textContent=fmt(courtPrice*selected.length);
    modal.classList.remove('hidden');modal.classList.add('flex');
  };
  document.getElementById('close-modal').onclick=()=>{modal.classList.add('hidden');modal.classList.remove('flex');};
  document.getElementById('close-success').onclick=()=>location.reload();

  document.getElementById('booking-form').onsubmit=async e=>{
    e.preventDefault();
    const fd=new FormData(e.target);
    const start=selected[0].start,end=selected[selected.length-1].end;
    try {
      const hold=await fetch(`{{ route('venues.bookings.store') }}`,{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json','Content-Type':'application/json'},body:JSON.stringify({court_id:Number(courtId),booking_date:selectedDate,start_time:start,end_time:end,customer_name:fd.get('customer_name'),customer_phone:fd.get('customer_phone')})});
      const holdData=await hold.json(); if(!hold.ok) throw new Error(holdData.message||'Gagal menahan slot');
      const id=holdData.booking.booking_id;
      const confirm=await fetch(`{{ url('/venues/bookings') }}/${id}/confirm`,{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}});
      const confirmData=await confirm.json(); if(!confirm.ok) throw new Error(confirmData.message||'Gagal mengonfirmasi booking');
      document.getElementById('booking-form').classList.add('hidden');document.getElementById('hold-banner').classList.add('hidden');document.querySelector('#success-panel').previousElementSibling?.classList.add('hidden');
      document.getElementById('success-panel').classList.remove('hidden');
      document.getElementById('booking-code').textContent=confirmData.booking.booking_code;
      const params=new URLSearchParams({venue:'{{ $venue->nama_venue }}',court:courtName,date:selectedDate,start,end,total:String(courtPrice*selected.length),booking_id:String(id)});
      document.getElementById('create-game-link').href=`{{ url('/games/create') }}?${params.toString()}`;
    } catch(err){alert(err.message);}
  };
})();
</script>
@endsection
