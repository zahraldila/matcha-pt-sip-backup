@extends('layouts.app')

@section('content')
@php
    $gallery = collect($venue['gallery'] ?? [])->filter()->values();
    $mainPhoto = $gallery->first() ?: 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=1200&q=85';
    $availableCourts = collect($venue['courts'] ?? [])->filter(fn ($court) => strtolower((string) ($court['status'] ?? '')) === 'available')->count();
@endphp

<div class="bg-[#f7f8f5] min-h-screen pb-28 md:pb-12">
    <div class="max-w-[1180px] mx-auto px-4 sm:px-6 lg:px-8 pt-4 md:pt-6 xl:px-0">
        <a href="{{ route('venues.index') }}" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-[#063B00] mb-4 md:mb-5">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            Kembali
        </a>

        <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-7 xl:grid-cols-[minmax(0,1fr)_340px] xl:gap-8">
            <main class="min-w-0">
                <section class="relative overflow-hidden rounded-[22px] md:rounded-[24px] bg-slate-900 h-[260px] sm:h-[380px] lg:h-[350px] xl:h-[390px] shadow-[0_16px_40px_rgba(15,23,42,.10)]">
                    <img id="venue-main-photo" src="{{ $mainPhoto }}" alt="{{ $venue['name'] }}" class="h-full w-full object-cover" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=1200&q=85';">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/45 via-transparent to-black/5"></div>

                    <div class="absolute left-4 top-4 md:left-6 md:top-6">
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/95 px-3 py-2 text-xs font-extrabold text-[#063B00] shadow-sm">
                            <i class="fa-solid fa-table-tennis-paddle-ball text-[#7FAF25]"></i>
                            {{ $venue['sport'] }}
                        </span>
                    </div>

                    @if($gallery->count() > 1)
                        <div class="absolute bottom-4 right-4 rounded-full bg-black/65 px-3 py-2 text-xs font-bold text-white backdrop-blur-sm">
                            <i class="fa-solid fa-camera mr-1"></i>{{ $gallery->count() }} Foto
                        </div>
                    @endif
                </section>

                @if($gallery->count() > 1)
                    <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                        @foreach($gallery->take(5) as $index => $photo)
                            <button type="button" onclick="document.getElementById('venue-main-photo').src='{{ $photo }}'" class="h-16 w-24 shrink-0 overflow-hidden rounded-xl border-2 {{ $index === 0 ? 'border-[#063B00]' : 'border-white' }} bg-slate-100 shadow-sm">
                                <img src="{{ $photo }}" alt="Foto venue {{ $index + 1 }}" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif

                <section class="pt-5 md:pt-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h1 class="text-[26px] leading-tight md:text-[32px] xl:text-[34px] font-black tracking-tight text-[#111827]">{{ $venue['name'] }}</h1>
                            <p class="mt-2 flex items-start gap-2 text-sm leading-relaxed text-slate-600">
                                <i class="fa-solid fa-location-dot mt-1 text-[#063B00]"></i>
                                <span>{{ $venue['address'] }}@if($venue['city']), {{ $venue['city'] }}@endif</span>
                            </p>
                        </div>
                        @if(!empty($venue['google_maps_url']))
                            <a href="{{ $venue['google_maps_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-[#063B00] shadow-sm">
                                <i class="fa-solid fa-map-location-dot"></i>
                                Google Maps
                            </a>
                        @endif
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach(array_slice($venue['facilities'] ?? [], 0, 8) as $facility)
                            <span class="rounded-lg border border-[#063B00]/10 bg-[#eef6e7] px-3 py-2 text-xs font-bold text-[#063B00]">
                                {{ $facility }}
                            </span>
                        @endforeach
                    </div>
                </section>

                <section class="mt-6 border-t border-slate-200 pt-6">
                    <h2 class="text-lg font-black text-[#111827]">Tentang Venue</h2>
                    <p class="mt-2 text-sm leading-7 text-slate-600">
                        {{ $venue['catatan'] ?: 'Venue olahraga yang menyediakan lapangan ' . strtolower($venue['sport']) . ' untuk bermain bersama.' }}
                    </p>
                </section>

                <section class="mt-6">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-black text-[#111827]">Pilihan Court</h2>
                            <p class="mt-1 text-xs text-slate-500">{{ $availableCourts }} dari {{ count($venue['courts'] ?? []) }} court tersedia</p>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @forelse($venue['courts'] as $court)
                            @php
                                $isCourtAvailable = strtolower((string) ($court['status'] ?? '')) === 'available';
                            @endphp
                            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_4px_18px_rgba(15,23,42,.04)]">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-black text-sm text-[#111827]">{{ $court['name'] }}</h3>
                                        <p class="mt-1 text-xs text-slate-500">{{ $court['type'] }}</p>
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold {{ $isCourtAvailable ? 'bg-[#eef6e7] text-[#063B00]' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $isCourtAvailable ? 'Tersedia' : $court['status'] }}
                                    </span>
                                </div>
                                @if(!empty($court['harga_per_jam']))
                                    <p class="mt-4 text-sm font-black text-[#063B00]">
                                        Rp {{ number_format($court['harga_per_jam'], 0, ',', '.') }} <span class="text-[11px] font-semibold text-slate-400">/ jam</span>
                                    </p>
                                @endif
                            </article>
                        @empty
                            <div class="sm:col-span-2 rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">
                                Belum ada court yang terdaftar.
                            </div>
                        @endforelse
                    </div>
                </section>
            </main>

            <aside class="hidden lg:block">
                <div class="sticky top-24 rounded-[20px] border border-slate-200 bg-white p-5 shadow-[0_12px_35px_rgba(15,23,42,.08)]">
                    <p class="text-xs font-bold text-slate-500">Mulai dari</p>
                    @php
                        $prices = collect($venue['courts'])->pluck('harga_per_jam')->filter()->values();
                        $lowestPrice = $prices->isNotEmpty() ? $prices->min() : 0;
                    @endphp
                    @if($lowestPrice > 0)
                        <p class="mt-1 text-2xl font-black text-[#063B00]">Rp {{ number_format($lowestPrice, 0, ',', '.') }} <span class="text-xs font-semibold text-slate-400">/ jam</span></p>
                    @else
                        <p class="mt-1 text-lg font-black text-[#063B00]">Lihat ketersediaan</p>
                    @endif

                    <div class="mt-5 space-y-3 border-y border-slate-100 py-4 text-sm">
                        <div class="flex items-start gap-3">
                            <i class="fa-regular fa-clock mt-0.5 text-[#063B00]"></i>
                            <div><p class="text-xs text-slate-500">Jam Operasional</p><p class="mt-0.5 font-bold text-slate-800">{{ $venue['operating_hours'] }}</p></div>
                        </div>
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-table-tennis-paddle-ball mt-0.5 text-[#063B00]"></i>
                            <div><p class="text-xs text-slate-500">Jumlah Court</p><p class="mt-0.5 font-bold text-slate-800">{{ count($venue['courts']) }} Lapangan</p></div>
                        </div>
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-wallet mt-0.5 text-[#063B00]"></i>
                            <div><p class="text-xs text-slate-500">Pembayaran</p><p class="mt-0.5 font-bold text-slate-800">Bayar di Tempat (Cash)</p></div>
                        </div>
                    </div>

                    <a href="{{ route('venues.court-selection', $venue['id']) }}" class="mt-5 flex h-12 w-full items-center justify-center rounded-xl bg-[#063B00] text-sm font-black text-white shadow-[0_8px_20px_rgba(6,59,0,.20)] hover:bg-[#042a00]">
                        Lihat Court & Jadwal
                    </a>
                </div>
            </aside>
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-[68px] z-40 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur-xl lg:hidden">
        <div class="mx-auto flex max-w-md items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] font-bold text-slate-500">Pembayaran</p>
                <p class="truncate text-xs font-black text-slate-800">Bayar di Tempat (Cash)</p>
            </div>
            <a href="{{ route('venues.court-selection', $venue['id']) }}" class="h-11 shrink-0 rounded-xl bg-[#063B00] px-5 inline-flex items-center justify-center text-xs font-black text-white">
                Lihat Court & Jadwal
            </a>
        </div>
    </div>
</div>
@endsection
