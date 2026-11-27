@extends('layouts.app')

@section('content')
@php
  $wa = preg_replace('/\D+/', '', (string) ($venue['pic_phone'] ?? ''));
  if ($wa !== '' && str_starts_with($wa, '0')) { $wa = '62'.substr($wa, 1); }
  $picName = $venue['pic_name'] ?? 'PIC Venue';
  $facilities = collect($venue['facilities'] ?? [])->map(fn ($x) => is_array($x) ? ($x['nama'] ?? $x['name'] ?? '') : (is_object($x) ? ($x->nama ?? $x->name ?? '') : (string) $x))->filter()->values();
  $hours = (string) ($venue['operating_hours'] ?? '');
@endphp
<div class="min-h-screen bg-slate-50 pb-28 md:pb-10">
  <div class="mx-auto max-w-[1100px] px-4 py-5 sm:px-6 lg:px-8">
    <a href="{{ route('venues.index') }}" class="mb-5 inline-flex items-center gap-2 text-lg font-black text-slate-900">
      <i class="fa-solid fa-chevron-left text-xs text-slate-500"></i>Detail Venue
    </a>

    <div class="grid items-stretch gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
      <main class="min-w-0">
        <div class="overflow-hidden rounded-2xl bg-slate-900">
          @if(!empty($venue['image']))
            <img src="{{ $venue['image'] }}" alt="{{ $venue['name'] }}" class="aspect-[16/10] w-full object-cover">
          @else
            <div class="flex aspect-[16/10] w-full flex-col items-center justify-center text-slate-400">
              <i class="fa-solid fa-building text-4xl"></i>
              <p class="mt-3 text-xs">Belum ada foto venue yang diunggah</p>
            </div>
          @endif
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-3">
          <h1 class="text-2xl font-black text-slate-900">{{ $venue['name'] }}</h1>
          <span class="rounded-full bg-[#dff5c8] px-3 py-1 text-[11px] font-black text-[#063B00]">{{ $venue['sport'] }}</span>
        </div>
        <p class="mt-1 flex items-center gap-2 text-xs text-slate-500">
          <i class="fa-solid fa-location-dot text-[#063B00]"></i>{{ $venue['address'] }}@if(!empty($venue['city'])), {{ $venue['city'] }}@endif
        </p>
      </main>

      <aside class="flex flex-col gap-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex items-center gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#eef6e7] text-[#063B00]"><i class="fa-regular fa-clock text-xs"></i></span>
            <div>
              <p class="text-[10px] text-slate-500">Jam Operasional Reguler</p>
              <p class="text-sm font-black text-slate-900">{{ str_contains($hours, 'WIB') ? $hours : $hours.' WIB' }}</p>
              <p class="text-[10px] text-slate-400">{{ $venue['hari_buka'] }}</p>
            </div>
          </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-[10px] text-slate-500">Catatan Khusus & Ketentuan</p>
          <p class="mt-1 text-xs font-bold text-slate-900">{{ $venue['unavailability_note'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-[10px] text-slate-500">Fasilitas</p>
          <div class="mt-2 flex flex-wrap gap-2">
            @forelse($facilities as $fac)
              <span class="rounded-lg bg-[#eef6e7] px-2.5 py-1 text-[10px] font-black text-[#063B00]">{{ $fac }}</span>
            @empty
              <span class="text-xs text-slate-400">Belum ada data fasilitas.</span>
            @endforelse
          </div>
        </div>
        <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#dff5c8] text-xs font-black text-[#063B00]">{{ strtoupper(mb_substr($picName, 0, 1)) }}</span>
            <div>
              <p class="text-[10px] text-slate-500">PIC / Pengelola</p>
              <p class="text-xs font-black text-slate-900">{{ $picName }}</p>
              <p class="text-[10px] text-slate-500">{{ $venue['pic_phone'] }}</p>
            </div>
          </div>
          @if($wa !== '')
            <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-[#063B00] px-3 text-[11px] font-black text-[#063B00]"><i class="fa-solid fa-phone text-[10px]"></i>Hubungi</a>
          @endif
        </div>
        <a href="{{ route('venues.court-selection', $venue['id']) }}" class="mt-auto flex h-12 w-full items-center justify-center rounded-xl bg-[#063B00] text-sm font-black text-white shadow-[0_8px_20px_rgba(6,59,0,.20)] hover:bg-[#042a00] lg:mb-6">Lihat Court & Jadwal</a>
      </aside>
    </div>
  </div>
</div>
@endsection