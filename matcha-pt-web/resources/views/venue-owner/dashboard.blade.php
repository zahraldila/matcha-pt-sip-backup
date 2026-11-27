@extends('layouts.app')

@section('content')
@php
    $greeting = now()->hour < 11 ? 'Good Morning' : (now()->hour < 15 ? 'Good Afternoon' : (now()->hour < 19 ? 'Good Evening' : 'Good Night'));
    $weekdays = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
    $dateQuery = request()->except(['date']);
    $ownerImageFallback = 'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?auto=format&fit=crop&w=1000&q=85';
@endphp

<div class="mx-auto max-w-7xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-800">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800">
            <p class="font-bold">Periksa kembali data yang dimasukkan:</p>
            <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="space-y-1">
        <p class="text-sm font-bold text-[#063B00]">{{ $greeting }}, {{ Auth::user()->nama }}!</p>
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">Dashboard Venue Owner</h1>
        <p class="text-sm text-slate-500">Berikut ringkasan aktivitas venue dan jadwal penyewaan court kamu.</p>
    </section>

    @if(!$venue)
        <section class="rounded-3xl border border-slate-200 bg-white px-6 py-14 text-center shadow-sm">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#EBF8D8] text-xl text-[#063B00]"><i class="fa-solid fa-building-circle-plus"></i></span>
            <h2 class="mt-4 text-xl font-extrabold text-slate-900">Mulai kelola venue kamu</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">Tambahkan venue terlebih dahulu untuk mengatur fasilitas, court, jam operasional, dan booking.</p>
            <a href="{{ route('venues.create') }}" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-[#063B00] px-5 py-3 text-xs font-bold text-white hover:bg-[#042a00]"><i class="fa-solid fa-plus text-[#A8E63A]"></i>Tambah Venue</a>
        </section>
    @else
        <form action="{{ route('owner.dashboard') }}" method="GET" class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <label class="flex items-center gap-2 text-xs font-bold text-slate-600">
                Venue
                <select name="venue_id" onchange="this.form.submit()" class="min-w-52 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-[#063B00] focus:outline-none">
                    @foreach($venues as $ownerVenue)
                        <option value="{{ $ownerVenue->venue_id }}" @selected($venue->venue_id === $ownerVenue->venue_id)>{{ $ownerVenue->nama_venue }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
            </label>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('venues.edit', $venue->venue_id) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-[11px] font-bold text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-pen mr-1"></i>Edit Profil Venue</a>
                <a href="{{ route('venues.show', $venue->venue_id) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-[11px] font-bold text-slate-700 hover:bg-slate-50">Lihat Venue</a>
            </div>
        </form>

        <section id="booking-calendar" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-center gap-2 overflow-x-auto pb-1">
                    @foreach($days as $day)
                        <a href="{{ route('owner.dashboard', array_merge($dateQuery, ['venue_id' => $venue->venue_id, 'date' => $day->toDateString()])) }}"
                           class="min-w-14 rounded-xl border px-3 py-2 text-center transition {{ $selectedDate->isSameDay($day) ? 'border-[#063B00] bg-[#063B00] text-white shadow-sm' : 'border-slate-100 bg-slate-50 text-slate-600 hover:border-[#063B00]/30 hover:bg-[#EBF8D8]/50' }}">
                            <span class="block text-[9px] font-semibold">{{ $day->locale('id')->translatedFormat('D') }}</span>
                            <span class="block text-xs font-extrabold">{{ $day->format('j M') }}</span>
                        </a>
                    @endforeach
                    <form action="{{ route('owner.dashboard') }}" method="GET" class="flex shrink-0 items-center gap-2 border-l border-slate-200 pl-3">
                        <input type="hidden" name="venue_id" value="{{ $venue->venue_id }}">
                        <input type="date" name="date" value="{{ $selectedDate->toDateString() }}" onchange="this.form.submit()" class="rounded-lg border border-slate-200 px-2 py-2 text-[10px] font-semibold text-slate-700">
                    </form>
                </div>
                <form action="{{ route('owner.dashboard') }}" method="GET" class="flex flex-wrap items-end gap-2">
                    <input type="hidden" name="venue_id" value="{{ $venue->venue_id }}">
                    <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                    <label class="text-[9px] font-semibold text-slate-500">Filter Waktu
                        <select name="period" class="mt-1 block rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-[10px] font-semibold text-slate-700">
                            <option value="all" @selected($period === 'all')>Semua Waktu</option>
                            <option value="morning" @selected($period === 'morning')>Pagi</option>
                            <option value="afternoon" @selected($period === 'afternoon')>Siang</option>
                            <option value="evening" @selected($period === 'evening')>Malam</option>
                        </select>
                    </label>
                    <label class="text-[9px] font-semibold text-slate-500">Cabang Olahraga
                        <select name="sport" class="mt-1 block rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-[10px] font-semibold text-slate-700">
                            <option value="all" @selected($selectedSport === 'all')>Semua Cabang</option>
                            @foreach($sports as $sport)
                                <option value="{{ $sport->nama_sport }}" @selected(strtolower($selectedSport) === strtolower($sport->nama_sport))>{{ $sport->nama_sport }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="rounded-lg bg-[#063B00] px-3 py-2 text-[10px] font-bold text-white hover:bg-[#042a00]">Terapkan</button>
                </form>
            </div>
        </section>

        <section id="courts" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#063B00]">{{ $venue->nama_venue }}</p>
                    <h2 class="text-xl font-extrabold text-slate-900">Court Saya <span class="text-sm font-semibold text-slate-400">({{ $courts->count() }})</span></h2>
                </div>
                <details class="group">
                    <summary class="cursor-pointer list-none rounded-lg bg-[#063B00] px-4 py-2.5 text-[11px] font-bold text-white hover:bg-[#042a00]"><i class="fa-solid fa-plus mr-1 text-[#A8E63A]"></i>Tambah Court</summary>
                    <form action="{{ route('venues.courts.store', $venue->venue_id) }}" method="POST" enctype="multipart/form-data" class="mt-3 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-lg sm:grid-cols-2 lg:grid-cols-4">
                        @csrf
                        <input type="hidden" name="owner_dashboard" value="1">
                        <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                        <label class="text-[10px] font-semibold text-slate-600">Nama court<input required name="nama_court" placeholder="Court baru" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></label>
                        <label class="text-[10px] font-semibold text-slate-600">Olahraga<select name="sport_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs">@foreach($sports as $sport)<option value="{{ $sport->sport_id }}">{{ $sport->nama_sport }}</option>@endforeach</select></label>
                        <label class="text-[10px] font-semibold text-slate-600">Tipe court<select name="tipe_court" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"><option>Indoor</option><option>Outdoor</option><option>Semi-Indoor</option></select></label>
                        <label class="text-[10px] font-semibold text-slate-600">Harga per jam<input required type="number" min="0" name="harga_per_jam" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></label>
                        <label class="text-[10px] font-semibold text-slate-600 sm:col-span-2 lg:col-span-4">Foto court<input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-[10px]"></label>
                        @if($facilities->isNotEmpty())
                            <fieldset class="sm:col-span-2 lg:col-span-4">
                                <legend class="mb-1 text-[10px] font-bold text-slate-600">Fasilitas court</legend>
                                <div class="flex flex-wrap gap-3">@foreach($facilities as $facility)<label class="flex items-center gap-1.5 text-[10px] text-slate-600"><input type="checkbox" name="facility_ids[]" value="{{ $facility->facility_id }}" class="accent-[#063B00]">{{ $facility->nama_fasilitas }}</label>@endforeach</div>
                            </fieldset>
                        @endif
                        <button class="rounded-lg bg-[#063B00] px-4 py-2 text-xs font-bold text-white sm:col-span-2 lg:col-span-4">Simpan Court</button>
                    </form>
                </details>
            </div>

            @if($courts->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Belum ada court pada venue ini.</div>
            @else
                <div class="grid gap-4 xl:grid-cols-2">
                    @foreach($courts as $court)
                        @php
                            $courtImage = $court->image_url ?: ($venue->main_photo ?: $ownerImageFallback);
                            $schedule = $court->owner_schedule;
                            $savedDaySchedule = $court->schedules->firstWhere('weekday', $weekday);
                            $slotPrice = $savedDaySchedule
                                ? $schedule['price']
                                : ((float) $court->harga_per_jam * $schedule['duration'] / 60);
                        @endphp
                        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <img src="{{ $courtImage }}" alt="{{ $court->nama_court }}" class="h-48 w-full object-cover sm:h-52">
                            <div class="space-y-4 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-base font-extrabold text-slate-900">{{ $court->nama_court }}</h3>
                                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $court->sport?->nama_sport ?? 'Olahraga' }}{{ $court->tipe_court ? ' · '.$court->tipe_court : '' }}</p>
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-[9px] font-bold {{ ($court->status_ketersediaan ?? 'Available') === 'Available' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $court->status_ketersediaan ?? 'Available' }}</span>
                                </div>

                                @if($court->facilities->isNotEmpty())
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($court->facilities as $facility)
                                            <span class="rounded-md bg-slate-100 px-2 py-1 text-[9px] font-medium text-slate-600">{{ $facility->nama_fasilitas }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                <details class="group border-t border-slate-100 pt-3">
                                    <summary class="flex cursor-pointer list-none items-center justify-between text-[11px] font-bold text-[#063B00]">
                                        <span>Lihat Jadwal · {{ $selectedDate->locale('id')->translatedFormat('l, d M Y') }}</span>
                                        <i class="fa-solid fa-chevron-down transition group-open:rotate-180"></i>
                                    </summary>
                                    <div class="mt-3 space-y-3">
                                        <div class="flex flex-wrap gap-2 text-[9px]">
                                            <span class="rounded-md bg-emerald-50 px-2 py-1 font-bold text-emerald-700">Available</span>
                                            <span class="rounded-md bg-blue-50 px-2 py-1 font-bold text-blue-700">Booked</span>
                                            <span class="rounded-md bg-amber-50 px-2 py-1 font-bold text-amber-700">Pending Payment</span>
                                            <span class="rounded-md bg-slate-100 px-2 py-1 font-bold text-slate-600">Diblokir</span>
                                        </div>
                                        @if($court->owner_slots->isEmpty())
                                            <p class="rounded-lg bg-slate-50 px-3 py-4 text-center text-[11px] text-slate-500">Court tutup pada hari ini atau tidak memiliki slot pada filter waktu ini.</p>
                                        @else
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                @foreach($court->owner_slots as $slot)
                                                    @php
                                                        $slotClass = match($slot['status']) {
                                                            'Booked' => 'border-blue-200 bg-blue-50/70',
                                                            'Pending Payment' => 'border-amber-200 bg-amber-50/70',
                                                            'Unavailable' => 'border-slate-200 bg-slate-100',
                                                            'Elapsed' => 'border-slate-200 bg-slate-50',
                                                            default => 'border-emerald-200 bg-emerald-50/50',
                                                        };
                                                        $statusClass = match($slot['status']) {
                                                            'Booked' => 'text-blue-700',
                                                            'Pending Payment' => 'text-amber-700',
                                                            'Unavailable' => 'text-slate-600',
                                                            'Elapsed' => 'text-slate-500',
                                                            default => 'text-emerald-700',
                                                        };
                                                    @endphp
                                                    <div class="rounded-xl border p-3 {{ $slotClass }}">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="text-xs font-extrabold text-slate-900">{{ $slot['start'] }}–{{ $slot['end'] }}</span>
                                                            <span class="text-[9px] font-extrabold {{ $statusClass }}">{{ $slot['status'] === 'Unavailable' ? ($slot['block'] ? 'Diblokir' : 'Tidak tersedia') : ($slot['status'] === 'Elapsed' ? 'Lewat' : $slot['status']) }}</span>
                                                        </div>
                                                        @if($slot['booking'])
                                                            <p class="mt-1 truncate text-[10px] font-semibold text-slate-700">{{ $slot['booking']->nama_penyewa }}</p>
                                                            <p class="text-[9px] text-slate-500">{{ $slot['booking']->kontak_penyewa ?: 'Kontak tidak tersedia' }} · {{ $slot['booking']->status_pembayaran }}</p>
                                                            @if($slot['booking']->status !== 'Cancelled')
                                                                <form action="{{ route('owner.bookings.cancel', $slot['booking']->booking_id) }}" method="POST" class="mt-2" onsubmit="return confirmBookingCancellation(this)">
                                                                    @csrf
                                                                    <input type="hidden" name="alasan_pembatalan">
                                                                    <button class="text-[9px] font-bold text-rose-700 hover:text-rose-900">Batalkan booking</button>
                                                                </form>
                                                            @endif
                                                        @elseif($slot['block'])
                                                            <p class="mt-1 text-[9px] text-slate-500">{{ $slot['block']->alasan ?: 'Slot tidak tersedia' }}</p>
                                                            <form action="{{ route('owner.courts.blocks.destroy', [$court->court_id, $slot['block']->block_id]) }}" method="POST" class="mt-1">
                                                                @csrf @method('DELETE')
                                                                <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                                                                <button class="text-[9px] font-bold text-[#063B00]">Aktifkan kembali slot</button>
                                                            </form>
                                                        @elseif($slot['status'] === 'Unavailable')
                                                            <p class="mt-1 text-[9px] text-slate-500">Court sedang tidak tersedia.</p>
                                                        @elseif($slot['status'] === 'Elapsed')
                                                            <p class="mt-1 text-[9px] text-slate-500">Waktu sewa ini sudah lewat.</p>
                                                        @else
                                                            <p class="mt-1 text-[10px] text-slate-500">Rp {{ number_format($slot['price'], 0, ',', '.') }}</p>
                                                            @if(($court->status_ketersediaan ?? 'Available') === 'Available')
                                                                <div class="mt-2 flex gap-3">
                                                                    <button type="button" class="text-[9px] font-bold text-[#063B00] hover:underline" onclick="openManualBooking({{ $court->court_id }}, '{{ $selectedDate->toDateString() }}', '{{ $slot['start'] }}', '{{ $slot['end'] }}', {{ $slot['price'] }})">Booking manual</button>
                                                                    <form action="{{ route('owner.courts.blocks.store', $court->court_id) }}" method="POST" class="flex items-center gap-1">
                                                                        @csrf
                                                                        <input type="hidden" name="tanggal" value="{{ $selectedDate->toDateString() }}">
                                                                        <input type="hidden" name="jam_mulai" value="{{ $slot['start'] }}">
                                                                        <input type="hidden" name="jam_selesai" value="{{ $slot['end'] }}">
                                                                        <input type="hidden" name="alasan" value="Ditutup pemilik venue">
                                                                        <button class="text-[9px] font-semibold text-slate-600 hover:underline">Nonaktifkan</button>
                                                                    </form>
                                                                </div>
                                                            @endif
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        <details class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                            <summary class="cursor-pointer text-[10px] font-bold text-slate-700">Atur jam sewa dan harga</summary>
                                            <form action="{{ route('owner.courts.schedule', $court->court_id) }}" method="POST" class="mt-3 grid gap-2 sm:grid-cols-2">
                                                @csrf
                                                <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                                                <input type="hidden" name="venue_id" value="{{ $venue->venue_id }}">
                                                <label class="text-[9px] font-semibold text-slate-600">Hari
                                                    <select name="weekday" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-[10px]">
                                                        @foreach($weekdays as $dayNumber => $dayName)<option value="{{ $dayNumber }}" @selected($weekday === $dayNumber)>{{ $dayName }}</option>@endforeach
                                                    </select>
                                                </label>
                                                <label class="flex items-center gap-2 pt-4 text-[10px] font-semibold text-slate-700"><input type="hidden" name="is_open" value="0"><input type="checkbox" name="is_open" value="1" @checked($schedule['is_open']) class="accent-[#063B00]">Buka pada hari ini</label>
                                                <label class="text-[9px] font-semibold text-slate-600">Mulai operasional<input type="time" name="jam_buka" value="{{ $schedule['start'] }}" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-[10px]"></label>
                                                <label class="text-[9px] font-semibold text-slate-600">Selesai operasional<input type="time" name="jam_tutup" value="{{ $schedule['end'] }}" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-[10px]"></label>
                                                <label class="text-[9px] font-semibold text-slate-600">Durasi slot<select name="durasi_menit" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-[10px]">@foreach([30,60,90,120] as $duration)<option value="{{ $duration }}" @selected($schedule['duration'] === $duration)>{{ $duration }} menit</option>@endforeach</select></label>
                                                <label class="text-[9px] font-semibold text-slate-600">Harga per slot (Rp)<input type="number" name="harga_per_slot" min="0" value="{{ number_format($slotPrice, 0, '.', '') }}" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-[10px]"></label>
                                                <button class="rounded-lg bg-[#063B00] px-3 py-2 text-[10px] font-bold text-white sm:col-span-2">Simpan Jadwal</button>
                                            </form>
                                        </details>
                                    </div>
                                </details>

                                <details class="border-t border-slate-100 pt-3">
                                    <summary class="cursor-pointer text-[10px] font-bold text-slate-600">Edit court / fasilitas</summary>
                                    <form action="{{ route('venues.courts.update', [$venue->venue_id, $court->court_id]) }}" method="POST" enctype="multipart/form-data" class="mt-3 grid gap-2 sm:grid-cols-2">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="facility_ids_present" value="1">
                                        <input type="hidden" name="owner_dashboard" value="1">
                                        <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                                        <label class="text-[9px] font-semibold text-slate-600">Nama court<input required name="nama_court" value="{{ $court->nama_court }}" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-[10px]"></label>
                                        <label class="text-[9px] font-semibold text-slate-600">Olahraga<select name="sport_id" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-[10px]">@foreach($sports as $sport)<option value="{{ $sport->sport_id }}" @selected((string) $court->sport_id === (string) $sport->sport_id)>{{ $sport->nama_sport }}</option>@endforeach</select></label>
                                        <label class="text-[9px] font-semibold text-slate-600">Tipe court<select name="tipe_court" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-[10px]">@foreach(['Indoor','Outdoor','Semi-Indoor'] as $type)<option value="{{ $type }}" @selected($court->tipe_court === $type)>{{ $type }}</option>@endforeach</select></label>
                                        <label class="text-[9px] font-semibold text-slate-600">Harga per jam<input type="number" min="0" name="harga_per_jam" value="{{ $court->harga_per_jam }}" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-[10px]"></label>
                                        <label class="text-[9px] font-semibold text-slate-600">Status<select name="status_ketersediaan" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-[10px]">@foreach(['Available','Maintenance','Inactive'] as $status)<option value="{{ $status }}" @selected(($court->status_ketersediaan ?? 'Available') === $status)>{{ $status }}</option>@endforeach</select></label>
                                        <label class="text-[9px] font-semibold text-slate-600 sm:col-span-2">Ganti foto court<input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-[9px]"></label>
                                        @if($facilities->isNotEmpty())
                                            <fieldset class="sm:col-span-2"><legend class="mb-1 text-[9px] font-bold text-slate-600">Fasilitas court</legend><div class="flex flex-wrap gap-3">@foreach($facilities as $facility)<label class="flex items-center gap-1.5 text-[9px] text-slate-600"><input type="checkbox" name="facility_ids[]" value="{{ $facility->facility_id }}" @checked($court->facilities->contains('facility_id', $facility->facility_id)) class="accent-[#063B00]">{{ $facility->nama_fasilitas }}</label>@endforeach</div></fieldset>
                                        @endif
                                        <button class="rounded-lg bg-[#063B00] px-3 py-2 text-[10px] font-bold text-white sm:col-span-2">Simpan Perubahan</button>
                                    </form>
                                    <form action="{{ route('venues.courts.destroy', [$venue->venue_id, $court->court_id]) }}" method="POST" class="mt-2" onsubmit="return confirm('Hapus court ini? Court yang memiliki riwayat akan dinonaktifkan.')">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="owner_dashboard" value="1">
                                        <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                                        <button class="text-[9px] font-bold text-rose-700">Hapus / nonaktifkan court</button>
                                    </form>
                                </details>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <h2 class="text-base font-extrabold text-slate-900">Informasi Booking · {{ $selectedDate->locale('id')->translatedFormat('d M Y') }}</h2>
                <p class="mt-1 text-[10px] text-slate-500">Riwayat pembatalan tetap tersimpan; slot yang dibatalkan dapat dipilih kembali.</p>
            </div>
            @if($bookings->isEmpty())
                <p class="rounded-lg bg-slate-50 p-4 text-center text-xs text-slate-500">Belum ada booking pada tanggal ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-[10px]">
                        <thead class="border-b border-slate-100 text-slate-500"><tr><th class="py-2 pr-3">Court / Jam</th><th class="py-2 pr-3">Penyewa</th><th class="py-2 pr-3">Pembayaran</th><th class="py-2 pr-3">Status</th><th class="py-2">Info</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($bookings as $booking)
                                @php
                                    $bookingBadge = match($booking->status) {
                                        'Booked' => 'bg-blue-50 text-blue-700',
                                        'Pending Payment' => 'bg-amber-50 text-amber-700',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <tr>
                                    <td class="py-2.5 pr-3 font-semibold text-slate-800">{{ $booking->court?->nama_court }} · {{ substr($booking->jam_mulai, 0, 5) }}–{{ substr($booking->jam_selesai, 0, 5) }}</td>
                                    <td class="py-2.5 pr-3">{{ $booking->nama_penyewa }}<span class="block text-slate-400">{{ $booking->kontak_penyewa ?: 'Tanpa kontak' }}</span></td>
                                    <td class="py-2.5 pr-3">{{ $booking->status_pembayaran }} · Rp {{ number_format($booking->jumlah_bayar, 0, ',', '.') }}</td>
                                    <td class="py-2.5 pr-3"><span class="rounded-full px-2 py-1 text-[9px] font-bold {{ $bookingBadge }}">{{ $booking->status }}</span></td>
                                    <td class="py-2.5 text-slate-500">{{ $booking->status === 'Cancelled' ? $booking->alasan_pembatalan : ($booking->catatan ?: ucfirst($booking->sumber)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section id="venue-profile" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#063B00]">Venue Saya</p>
                    <h2 class="text-xl font-extrabold text-slate-900">Profil & Fasilitas</h2>
                </div>
                <a href="{{ route('venues.edit', $venue->venue_id) }}" class="text-[10px] font-bold text-[#063B00] hover:underline">Edit informasi venue</a>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($facilities as $facility)
                    <details class="rounded-xl border border-slate-200 p-3">
                        <summary class="flex cursor-pointer list-none items-center gap-3">
                            @if($facility->foto)<img src="{{ $facility->foto }}" alt="" class="h-12 w-14 rounded-lg object-cover">@else<span class="flex h-12 w-14 items-center justify-center rounded-lg bg-[#EBF8D8] text-[#063B00]"><i class="fa-solid fa-dumbbell"></i></span>@endif
                            <span class="min-w-0"><span class="block truncate text-xs font-bold text-slate-800">{{ $facility->nama_fasilitas }}</span><span class="block text-[9px] text-slate-500">{{ $facility->sport?->nama_sport ?? 'Beragam olahraga' }}</span></span>
                        </summary>
                        <form action="{{ route('owner.facilities.update', [$venue->venue_id, $facility->facility_id]) }}" method="POST" enctype="multipart/form-data" class="mt-3 space-y-2">
                            @csrf @method('PUT')
                            <label class="block text-[9px] font-semibold text-slate-600">Nama fasilitas<input required name="nama_fasilitas" value="{{ $facility->nama_fasilitas }}" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-[10px]"></label>
                            <label class="block text-[9px] font-semibold text-slate-600">Cabang olahraga<select name="sport_id" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-[10px]"><option value="">Pilih olahraga</option>@foreach($sports as $sport)<option value="{{ $sport->sport_id }}" @selected((string) $facility->sport_id === (string) $sport->sport_id)>{{ $sport->nama_sport }}</option>@endforeach</select></label>
                            <label class="block text-[9px] font-semibold text-slate-600">Deskripsi<textarea name="deskripsi" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-[10px]">{{ $facility->deskripsi }}</textarea></label>
                            <label class="block text-[9px] font-semibold text-slate-600">Ganti foto<input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-[9px]"></label>
                            <button class="w-full rounded-lg bg-[#063B00] px-3 py-2 text-[10px] font-bold text-white">Simpan Fasilitas</button>
                        </form>
                        <form action="{{ route('owner.facilities.destroy', [$venue->venue_id, $facility->facility_id]) }}" method="POST" class="mt-2" onsubmit="return confirm('Hapus fasilitas ini?')">
                            @csrf @method('DELETE')
                            <button class="text-[9px] font-bold text-rose-700">Hapus fasilitas</button>
                        </form>
                    </details>
                @empty
                    <p class="text-xs text-slate-500">Belum ada fasilitas. Tambahkan fasilitas venue di bawah ini.</p>
                @endforelse
            </div>

            <details class="rounded-xl border border-dashed border-slate-300 p-3">
                <summary class="cursor-pointer text-[11px] font-bold text-[#063B00]"><i class="fa-solid fa-plus mr-1"></i>Tambah Fasilitas</summary>
                <form action="{{ route('owner.facilities.store', $venue->venue_id) }}" method="POST" enctype="multipart/form-data" class="mt-3 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <label class="text-[9px] font-semibold text-slate-600">Nama fasilitas<input required name="nama_fasilitas" placeholder="Contoh: Lapangan Indoor" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></label>
                    <label class="text-[9px] font-semibold text-slate-600">Cabang olahraga<select name="sport_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"><option value="">Pilih olahraga</option>@foreach($sports as $sport)<option value="{{ $sport->sport_id }}">{{ $sport->nama_sport }}</option>@endforeach</select></label>
                    <label class="text-[9px] font-semibold text-slate-600">Deskripsi<textarea name="deskripsi" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></textarea></label>
                    <label class="text-[9px] font-semibold text-slate-600">Foto fasilitas<input required type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-[10px]"></label>
                    <button class="rounded-lg bg-[#063B00] px-4 py-2.5 text-[10px] font-bold text-white sm:col-span-2">Simpan Fasilitas</button>
                </form>
            </details>
        </section>
    @endif
</div>

@if($venue)
    <dialog id="manualBookingDialog" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/50">
        <form id="manualBookingForm" method="POST" class="space-y-4 p-5">
            @csrf
            <div class="flex items-start justify-between">
                <div><p class="text-[10px] font-bold uppercase tracking-widest text-[#063B00]">Booking Owner</p><h2 class="text-lg font-extrabold text-slate-900">Tambah Booking Manual</h2></div>
                <button type="button" onclick="document.getElementById('manualBookingDialog').close()" class="text-slate-400 hover:text-slate-700" aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <input type="hidden" name="tanggal" id="manualBookingDate">
            <div class="grid grid-cols-2 gap-3">
                <label class="text-[10px] font-semibold text-slate-600">Tanggal<input required type="date" id="manualBookingDateDisplay" onchange="document.getElementById('manualBookingDate').value=this.value" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></label>
                <label class="text-[10px] font-semibold text-slate-600">Nama penyewa<input required name="nama_penyewa" maxlength="120" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></label>
                <label class="text-[10px] font-semibold text-slate-600">Jam mulai<input required type="time" name="jam_mulai" id="manualBookingStart" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></label>
                <label class="text-[10px] font-semibold text-slate-600">Jam selesai<input required type="time" name="jam_selesai" id="manualBookingEnd" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></label>
                <label class="text-[10px] font-semibold text-slate-600">Kontak<input name="kontak_penyewa" maxlength="80" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></label>
                <label class="text-[10px] font-semibold text-slate-600">Nominal (Rp)<input type="number" min="0" name="jumlah_bayar" id="manualBookingAmount" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></label>
                <label class="text-[10px] font-semibold text-slate-600">Status pembayaran<select name="status_pembayaran" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"><option value="Pending">Pending</option><option value="Paid">Paid</option><option value="Unpaid">Belum Dibayar</option></select></label>
                <label class="text-[10px] font-semibold text-slate-600">Catatan<input name="catatan" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></label>
            </div>
            <p id="manualBookingCourtLabel" class="text-[10px] font-semibold text-slate-500"></p>
            <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                <button type="button" onclick="document.getElementById('manualBookingDialog').close()" class="rounded-lg border border-slate-200 px-4 py-2 text-[10px] font-bold text-slate-600">Batal</button>
                <button class="rounded-lg bg-[#063B00] px-4 py-2 text-[10px] font-bold text-white">Simpan Booking</button>
            </div>
        </form>
    </dialog>

    <script>
        function openManualBooking(courtId, date, start, end, amount) {
            const dialog = document.getElementById('manualBookingDialog');
            document.getElementById('manualBookingForm').action = '{{ url('/owner/courts') }}/' + courtId + '/bookings';
            document.getElementById('manualBookingDate').value = date;
            document.getElementById('manualBookingDateDisplay').value = date;
            document.getElementById('manualBookingStart').value = start;
            document.getElementById('manualBookingEnd').value = end;
            document.getElementById('manualBookingAmount').value = amount;
            document.getElementById('manualBookingCourtLabel').textContent = 'Court dan waktu: ' + start + '–' + end;
            dialog.showModal();
        }

        function confirmBookingCancellation(form) {
            if (!window.confirm('Batalkan booking ini? Slot dapat kembali tersedia setelah pembatalan.')) {
                return false;
            }
            const reason = window.prompt('Masukkan alasan pembatalan:');
            if (!reason || reason.trim().length < 3) {
                window.alert('Alasan pembatalan minimal 3 karakter.');
                return false;
            }
            form.querySelector('[name="alasan_pembatalan"]').value = reason.trim();
            return true;
        }
    </script>
@endif
@endsection
