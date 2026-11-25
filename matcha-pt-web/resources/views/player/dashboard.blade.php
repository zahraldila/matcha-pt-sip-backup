@extends('layouts.app')

@section('content')
@php
    $greeting = now()->hour < 11 ? 'Good Morning' : (now()->hour < 15 ? 'Good Afternoon' : (now()->hour < 19 ? 'Good Evening' : 'Good Night'));
    $weekdays = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
    $dateQuery = request()->except(['date']);
    $courtImageFallback = 'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?auto=format&fit=crop&w=1000&q=85';
@endphp

<div class="mx-auto max-w-7xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">
            <i class="fa-solid fa-circle-check mr-1.5 text-emerald-600"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-800">
            <i class="fa-solid fa-triangle-exclamation mr-1.5 text-rose-600"></i>{{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800">
            <p class="font-bold flex items-center gap-1.5"><i class="fa-solid fa-circle-exclamation text-rose-600"></i> Periksa kembali data booking Anda:</p>
            <ul class="mt-1 list-inside list-disc pl-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Header & Greeting Section -->
    <section class="space-y-1">
        <p class="text-sm font-bold text-[#063B00]">{{ $greeting }}, {{ Auth::check() ? Auth::user()->nama : 'Pemain' }}!</p>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">Dashboard Sewa Court</h1>
                <p class="mt-1 text-sm text-slate-500">Pilih court favoritmu, cek ketersediaan slot waktu secara real-time, dan pesan langsung tanpa repot.</p>
            </div>
            @auth
                <a href="{{ route('player.bookings.index') }}" class="inline-flex items-center gap-2 self-start rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 sm:self-auto">
                    <i class="fa-solid fa-calendar-check text-[#063B00]"></i>
                    <span>Booking Saya</span>
                    @if($activeBookingsCount > 0)
                        <span class="rounded-full bg-[#EBF8D8] px-2 py-0.5 text-[10px] font-black text-[#063B00]">{{ $activeBookingsCount }}</span>
                    @endif
                </a>
            @else
                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-[#063B00] px-3.5 py-2 text-xs font-bold text-white hover:bg-[#042a00]">
                        Daftar
                    </a>
                </div>
            @endauth
        </div>
    </section>

    <!-- Date Selector & Filters (Identik dengan Dashboard Owner) -->
    <section id="booking-calendar" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <!-- Tanggal Horizontal Picker -->
            <div class="flex min-w-0 items-center gap-2 overflow-x-auto pb-1">
                @foreach($days as $day)
                    <a href="{{ route('user.dashboard', array_merge($dateQuery, ['date' => $day->toDateString()])) }}"
                       class="min-w-14 rounded-xl border px-3 py-2 text-center transition {{ $selectedDate->isSameDay($day) ? 'border-[#063B00] bg-[#063B00] text-white shadow-sm' : 'border-slate-100 bg-slate-50 text-slate-600 hover:border-[#063B00]/30 hover:bg-[#EBF8D8]/50' }}">
                        <span class="block text-[9px] font-semibold">{{ $day->locale('id')->translatedFormat('D') }}</span>
                        <span class="block text-xs font-extrabold">{{ $day->format('j M') }}</span>
                    </a>
                @endforeach
                <form action="{{ route('user.dashboard') }}" method="GET" class="flex shrink-0 items-center gap-2 border-l border-slate-200 pl-3">
                    @foreach(request()->except(['date']) as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <input type="date" name="date" value="{{ $selectedDate->toDateString() }}" onchange="this.form.submit()" class="rounded-lg border border-slate-200 px-2 py-2 text-[10px] font-semibold text-slate-700">
                </form>
            </div>

            <!-- Filter Controls -->
            <form action="{{ route('user.dashboard') }}" method="GET" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                
                <label class="text-[9px] font-semibold text-slate-500">Filter Waktu
                    <select name="period" class="mt-1 block rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-[10px] font-semibold text-slate-700">
                        <option value="all" @selected($period === 'all')>Semua Waktu</option>
                        <option value="morning" @selected($period === 'morning')>Pagi (< 12:00)</option>
                        <option value="afternoon" @selected($period === 'afternoon')>Siang (12:00 - 17:00)</option>
                        <option value="evening" @selected($period === 'evening')>Malam (>= 17:00)</option>
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

                <label class="text-[9px] font-semibold text-slate-500">Venue
                    <select name="venue_id" class="mt-1 block max-w-44 rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-[10px] font-semibold text-slate-700">
                        <option value="">Semua Venue</option>
                        @foreach($venues as $venue)
                            <option value="{{ $venue->venue_id }}" @selected((string)$selectedVenueId === (string)$venue->venue_id)>{{ $venue->nama_venue }}</option>
                        @endforeach
                    </select>
                </label>

                <button type="submit" class="rounded-lg bg-[#063B00] px-3.5 py-2 text-[10px] font-bold text-white hover:bg-[#042a00] transition">
                    <i class="fa-solid fa-filter mr-1 text-[#A8E63A]"></i>Terapkan
                </button>

                @if($selectedSport !== 'all' || $period !== 'all' || $selectedVenueId || request('date'))
                    <a href="{{ route('user.dashboard') }}" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-[10px] font-bold text-slate-600 hover:bg-slate-100" title="Reset Filter">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                @endif
            </form>
        </div>
    </section>

    <!-- Court Cards Section -->
    <section id="courts" class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-[#063B00]">Katalog Lapangan</p>
                <h2 class="text-xl font-extrabold text-slate-900">Daftar Court Tersedia <span class="text-sm font-semibold text-slate-400">({{ $courts->count() }})</span></h2>
            </div>
            <div class="flex items-center gap-2 text-[10px] font-semibold text-slate-500">
                <span>Tanggal Terpilih:</span>
                <span class="rounded-md bg-[#EBF8D8] px-2 py-0.5 font-extrabold text-[#063B00]">{{ $selectedDate->locale('id')->translatedFormat('l, d F Y') }}</span>
            </div>
        </div>

        @if($courts->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center shadow-xs">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-xl text-slate-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <h3 class="mt-4 text-base font-extrabold text-slate-900">Tidak ada court yang cocok</h3>
                <p class="mx-auto mt-1 max-w-sm text-xs text-slate-500">Coba ubah tanggal, filter waktu, atau pilih cabang olahraga yang lain.</p>
                <a href="{{ route('user.dashboard') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-[#063B00] px-4 py-2 text-xs font-bold text-white hover:bg-[#042a00]">
                    <i class="fa-solid fa-rotate-left text-[#A8E63A]"></i> Reset Filter
                </a>
            </div>
        @else
            <div class="grid gap-5 xl:grid-cols-2">
                @foreach($courts as $court)
                    @php
                        $courtImage = $court->image_url ?: ($court->venue?->main_photo ?: $courtImageFallback);
                        $schedule = $court->schedule_settings;
                        $savedDaySchedule = $court->schedules->firstWhere('weekday', $weekday);
                        $slotPrice = $savedDaySchedule
                            ? $schedule['price']
                            : ((float) ($court->harga_per_jam ?? 0) * $schedule['duration'] / 60);
                        
                        $availableSlotsCount = $court->user_slots->where('status', 'Available')->count();
                    @endphp
                    <article class="court-card overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md" data-court-id="{{ $court->court_id }}">
                        <div class="relative">
                            <img src="{{ $courtImage }}" alt="{{ $court->nama_court }}" class="h-48 w-full object-cover sm:h-52">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                            <div class="absolute bottom-3 left-4 right-4 flex items-end justify-between text-white">
                                <div>
                                    <span class="rounded-md bg-[#063B00]/90 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-[#A8E63A] backdrop-blur-xs">
                                        {{ $court->sport?->nama_sport ?? 'Olahraga' }}
                                    </span>
                                    <h3 class="mt-1 text-lg font-black tracking-tight text-white sm:text-xl">{{ $court->nama_court }}</h3>
                                    <p class="text-xs font-medium text-slate-200"><i class="fa-solid fa-location-dot mr-1 text-[#A8E63A]"></i>{{ $court->venue?->nama_venue ?? 'Matcha Arena' }} &bull; {{ $court->venue?->kota ?? 'Indonesia' }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] text-slate-300">Mulai dari</span>
                                    <p class="text-base font-black text-[#A8E63A]">Rp {{ number_format($slotPrice, 0, ',', '.') }}<span class="text-[10px] font-normal text-slate-300">/slot</span></p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 p-4 sm:p-5">
                            <!-- Court Details & Facilities -->
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3 text-xs">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-700">
                                        <i class="fa-solid fa-cube mr-1 text-slate-400"></i>{{ $court->tipe_court ?: 'Standard' }}
                                    </span>
                                    <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-700">
                                        <i class="fa-regular fa-clock mr-1 text-slate-400"></i>{{ $schedule['duration'] }} menit/slot
                                    </span>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $availableSlotsCount > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500' }}">
                                    <i class="fa-solid {{ $availableSlotsCount > 0 ? 'fa-circle-check text-emerald-500' : 'fa-circle-xmark text-slate-400' }} mr-1"></i>
                                    {{ $availableSlotsCount > 0 ? $availableSlotsCount.' Slot Tersedia' : 'Penuh / Tidak Tersedia' }}
                                </span>
                            </div>

                            <!-- Fasilitas -->
                            @if($court->facilities->isNotEmpty())
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Fasilitas Court:</p>
                                    <div class="mt-1 flex flex-wrap gap-1.5">
                                        @foreach($court->facilities as $facility)
                                            <span class="rounded-md bg-[#EBF8D8] px-2 py-0.5 text-[10px] font-semibold text-[#063B00]">
                                                <i class="fa-solid fa-check text-[9px] mr-1 text-[#063B00]"></i>{{ $facility->nama_fasilitas }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- Accordion Schedule Slot (Default Terbuka agar interaktif) -->
                            <details class="group rounded-xl border border-slate-200 bg-slate-50/60 p-3 transition" open>
                                <summary class="flex cursor-pointer items-center justify-between text-xs font-extrabold text-slate-800 hover:text-[#063B00]">
                                    <span class="flex items-center gap-1.5">
                                        <i class="fa-solid fa-calendar-days text-[#063B00]"></i>
                                        <span>Jadwal & Ketersediaan Slot ({{ $selectedDate->format('j M Y') }})</span>
                                    </span>
                                    <span class="text-[10px] font-bold text-slate-500 group-open:rotate-180 transition-transform">
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </span>
                                </summary>

                                <div class="mt-3.5 space-y-3 pt-2 border-t border-slate-200/70">
                                    <!-- Status Legend (Sama persis dengan Owner Dashboard) -->
                                    <div class="flex flex-wrap items-center gap-1.5 text-[9px]">
                                        <span class="font-bold text-slate-400 mr-1">Keterangan:</span>
                                        <span class="rounded-md bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700 border border-emerald-200">Available</span>
                                        <span class="rounded-md bg-blue-50 px-2 py-0.5 font-bold text-blue-700 border border-blue-200">Booked</span>
                                        <span class="rounded-md bg-amber-50 px-2 py-0.5 font-bold text-amber-700 border border-amber-200">Pending Payment</span>
                                        <span class="rounded-md bg-slate-100 px-2 py-0.5 font-bold text-slate-600 border border-slate-200">Tidak Tersedia</span>
                                    </div>

                                    @if($court->user_slots->isEmpty())
                                        <p class="rounded-lg bg-white px-3 py-4 text-center text-xs text-slate-500 border border-slate-200">Court tutup pada hari ini atau tidak memiliki slot pada filter waktu ini.</p>
                                    @else
                                        <!-- Slot Grid -->
                                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            @foreach($court->user_slots as $slot)
                                                @php
                                                    $isAvailable = $slot['status'] === 'Available';
                                                    $slotClass = match($slot['status']) {
                                                        'Booked' => 'border-blue-200 bg-blue-50/70 text-blue-700 cursor-not-allowed opacity-90',
                                                        'Pending Payment' => 'border-amber-200 bg-amber-50/70 text-amber-700 cursor-not-allowed opacity-90',
                                                        'Unavailable' => 'border-slate-200 bg-slate-100 text-slate-500 cursor-not-allowed opacity-75',
                                                        'Elapsed' => 'border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed opacity-60',
                                                        default => 'border-emerald-200 bg-emerald-50/50 hover:bg-emerald-100/70 hover:border-[#063B00] text-emerald-800 cursor-pointer transition active:scale-98',
                                                    };
                                                    $statusBadge = match($slot['status']) {
                                                        'Booked' => 'Booked',
                                                        'Pending Payment' => 'Pending Payment',
                                                        'Unavailable' => 'Tutup / Blokir',
                                                        'Elapsed' => 'Lewat',
                                                        default => 'Available',
                                                    };
                                                @endphp
                                                <button type="button"
                                                        @if($isAvailable)
                                                            onclick="toggleSlotSelection({{ $court->court_id }}, '{{ $court->nama_court }}', '{{ $court->venue?->nama_venue }}', '{{ $selectedDate->toDateString() }}', '{{ $selectedDate->locale('id')->translatedFormat('l, d M Y') }}', '{{ $slot['start'] }}', '{{ $slot['end'] }}', {{ $slot['price'] }})"
                                                            data-court-id="{{ $court->court_id }}"
                                                            data-start="{{ $slot['start'] }}"
                                                            data-end="{{ $slot['end'] }}"
                                                            data-price="{{ $slot['price'] }}"
                                                            class="slot-btn text-left rounded-xl border p-2.5 transition {{ $slotClass }}"
                                                        @else
                                                            disabled
                                                            class="text-left rounded-xl border p-2.5 {{ $slotClass }}"
                                                        @endif>
                                                    <div class="flex items-center justify-between gap-1">
                                                        <span class="text-xs font-black tracking-tight text-slate-900">{{ $slot['start'] }}–{{ $slot['end'] }}</span>
                                                        <span class="text-[9px] font-extrabold uppercase">{{ $statusBadge }}</span>
                                                    </div>
                                                    <div class="mt-1 flex items-center justify-between">
                                                        <span class="text-[10px] font-bold text-slate-600">Rp {{ number_format($slot['price'], 0, ',', '.') }}</span>
                                                        @if($isAvailable)
                                                            <span class="slot-check-icon hidden text-[11px] text-[#063B00] font-black"><i class="fa-solid fa-circle-check"></i></span>
                                                        @endif
                                                    </div>
                                                </button>
                                            @endforeach
                                        </div>

                                        <!-- Selected Slots Action Bar for this court -->
                                        <div id="selection-bar-{{ $court->court_id }}" class="hidden items-center justify-between gap-3 rounded-xl border border-[#063B00]/30 bg-[#EBF8D8] p-3 text-xs">
                                            <div>
                                                <p class="font-extrabold text-[#063B00]"><span class="selected-count">0</span> Slot Terpilih (<span class="selected-time-range"></span>)</p>
                                                <p class="text-[10px] text-slate-600">Total Biaya: <span class="font-black text-slate-900 selected-total-price">Rp 0</span></p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button type="button" onclick="clearCourtSelection({{ $court->court_id }})" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-[10px] font-bold text-slate-600 hover:bg-slate-50">Batal</button>
                                                <button type="button" onclick="openBookingModal({{ $court->court_id }})" class="rounded-lg bg-[#063B00] px-3.5 py-1.5 text-[10px] font-extrabold text-white shadow-xs hover:bg-[#042a00] transition">
                                                    Sewa Sekarang &rarr;
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </details>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>

<!-- Booking & Payment Dialog Modal (Identik dengan Modal Owner) -->
<dialog id="userBookingDialog" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/50">
    <form id="userBookingForm" method="POST" action="{{ route('player.bookings.store') }}" class="space-y-4 p-5 sm:p-6" onsubmit="handleBookingSubmit(event)">
        @csrf
        <div class="flex items-start justify-between border-b border-slate-100 pb-3">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-[#063B00]">Konfirmasi Booking Court</p>
                <h2 class="text-lg font-black text-slate-900">Sewa Lapangan & Pembayaran</h2>
            </div>
            <button type="button" onclick="document.getElementById('userBookingDialog').close()" class="text-slate-400 hover:text-slate-700" aria-label="Tutup">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <input type="hidden" name="court_id" id="modalCourtId">
        <input type="hidden" name="tanggal" id="modalBookingDate">
        <input type="hidden" name="jam_mulai" id="modalBookingStart">
        <input type="hidden" name="jam_selesai" id="modalBookingEnd">

        <!-- Booking Summary Card -->
        <div class="rounded-xl border border-[#063B00]/20 bg-[#EBF8D8]/50 p-3.5 space-y-2 text-xs">
            <div class="flex items-center justify-between text-slate-600">
                <span>Venue:</span>
                <span id="modalVenueNameDisplay" class="font-bold text-slate-900"></span>
            </div>
            <div class="flex items-center justify-between text-slate-600">
                <span>Court:</span>
                <span id="modalCourtNameDisplay" class="font-bold text-slate-900"></span>
            </div>
            <div class="flex items-center justify-between text-slate-600">
                <span>Tanggal:</span>
                <span id="modalDateDisplay" class="font-bold text-slate-900"></span>
            </div>
            <div class="flex items-center justify-between text-slate-600">
                <span>Slot Waktu:</span>
                <span id="modalTimeRangeDisplay" class="font-black text-[#063B00]"></span>
            </div>
            <div class="flex items-center justify-between border-t border-[#063B00]/15 pt-2 text-sm font-extrabold text-slate-900">
                <span>Total Biaya Sewa:</span>
                <span id="modalTotalAmountDisplay" class="text-base text-[#063B00]"></span>
            </div>
        </div>

        <!-- Form Pemesan -->
        <div class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="text-[10px] font-semibold text-slate-600">Nama Lengkap Pemesan
                    <input required name="nama_penyewa" maxlength="120" value="{{ Auth::check() ? Auth::user()->nama : '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-900 focus:border-[#063B00] focus:outline-none">
                </label>
                <label class="text-[10px] font-semibold text-slate-600">Nomor WhatsApp / HP
                    <input required name="kontak_penyewa" maxlength="80" value="{{ Auth::check() ? Auth::user()->no_hp : '' }}" placeholder="08123456789" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-900 focus:border-[#063B00] focus:outline-none">
                </label>
            </div>

            <!-- Metode Pembayaran -->
            <label class="block text-[10px] font-semibold text-slate-600">Pilih Metode Pembayaran
                <select name="metode_pembayaran" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-[#063B00] focus:outline-none">
                    <option value="QRIS">QRIS (Gopay, OVO, Dana, ShopeePay)</option>
                    <option value="Transfer Bank - BCA">Transfer Bank - BCA</option>
                    <option value="Transfer Bank - Mandiri">Transfer Bank - Mandiri</option>
                    <option value="Transfer Bank - BNI">Transfer Bank - BNI</option>
                    <option value="Virtual Account">Virtual Account</option>
                    <option value="Cash di Lokasi">Bayar Tunai di Venue</option>
                </select>
            </label>

            <label class="block text-[10px] font-semibold text-slate-600">Catatan Khusus (Opsional)
                <input name="catatan" maxlength="500" placeholder="Misal: butuh raket tambahan atau bola sewa" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-900 focus:border-[#063B00] focus:outline-none">
            </label>
        </div>

        <div class="rounded-lg bg-slate-50 p-2.5 text-[10px] text-slate-500 flex items-start gap-2">
            <i class="fa-solid fa-shield-halved text-[#063B00] mt-0.5"></i>
            <span>Slot akan langsung dikunci otomatis di sistem kalender owner dan pemain lain begitu booking dikonfirmasi.</span>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <button type="button" onclick="document.getElementById('userBookingDialog').close()" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">
                Batal
            </button>
            <button type="submit" id="submitBookingBtn" class="rounded-lg bg-[#063B00] px-5 py-2 text-xs font-extrabold text-white shadow-xs hover:bg-[#042a00] transition">
                Bayar &amp; Konfirmasi Booking
            </button>
        </div>
    </form>
</dialog>

<!-- Guest Login Required Dialog -->
<dialog id="guestLoginPromptDialog" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/50">
    <div class="p-6 text-center space-y-4">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#EBF8D8] text-2xl text-[#063B00]">
            <i class="fa-solid fa-user-lock"></i>
        </span>
        <div class="space-y-1">
            <h3 class="text-lg font-black text-slate-900">Masuk untuk Menyewa Court</h3>
            <p class="text-xs text-slate-500">Silakan masuk atau daftar akun Matcha terlebih dahulu untuk melanjutkan proses booking lapangan.</p>
        </div>
        <div class="flex gap-2.5 pt-2">
            <a href="{{ route('register') }}" class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-bold text-xs hover:bg-slate-50 transition">
                Daftar Akun
            </a>
            <a href="{{ route('login') }}" class="flex-1 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition">
                Masuk (Login)
            </a>
        </div>
        <button type="button" onclick="document.getElementById('guestLoginPromptDialog').close()" class="text-xs font-semibold text-slate-400 hover:text-slate-600">
            Tutup
        </button>
    </div>
</dialog>

@push('scripts')
<script>
    // State storage for selected slots per court
    const courtSelections = {};

    function toggleSlotSelection(courtId, courtName, venueName, date, dateFormatted, start, end, price) {
        if (!courtSelections[courtId]) {
            courtSelections[courtId] = {
                courtId: courtId,
                courtName: courtName,
                venueName: venueName,
                date: date,
                dateFormatted: dateFormatted,
                slots: []
            };
        }

        const courtData = courtSelections[courtId];
        const existingIndex = courtData.slots.findIndex(s => s.start === start && s.end === end);

        if (existingIndex > -1) {
            // Unselect slot
            courtData.slots.splice(existingIndex, 1);
        } else {
            // Add slot
            courtData.slots.push({ start, end, price });
        }

        // Sort slots chronologically
        courtData.slots.sort((a, b) => a.start.localeCompare(b.start));

        // Validate continuity: if non-consecutive, keep only the latest clicked slot
        if (courtData.slots.length > 1) {
            let isContinuous = true;
            for (let i = 0; i < courtData.slots.length - 1; i++) {
                if (courtData.slots[i].end !== courtData.slots[i + 1].start) {
                    isContinuous = false;
                    break;
                }
            }
            if (!isContinuous) {
                courtData.slots = [{ start, end, price }];
            }
        }

        updateCourtUI(courtId);
    }

    function updateCourtUI(courtId) {
        const courtData = courtSelections[courtId];
        const card = document.querySelector(`.court-card[data-court-id="${courtId}"]`);
        if (!card) return;

        const allButtons = card.querySelectorAll('.slot-btn');
        allButtons.forEach(btn => {
            const btnStart = btn.getAttribute('data-start');
            const btnEnd = btn.getAttribute('data-end');
            const isSelected = courtData && courtData.slots.some(s => s.start === btnStart && s.end === btnEnd);
            const checkIcon = btn.querySelector('.slot-check-icon');

            if (isSelected) {
                btn.classList.add('ring-2', 'ring-[#063B00]', 'bg-emerald-100', 'font-bold');
                btn.classList.remove('bg-emerald-50/50');
                if (checkIcon) checkIcon.classList.remove('hidden');
            } else {
                btn.classList.remove('ring-2', 'ring-[#063B00]', 'bg-emerald-100', 'font-bold');
                btn.classList.add('bg-emerald-50/50');
                if (checkIcon) checkIcon.classList.add('hidden');
            }
        });

        // Update bottom action bar
        const bar = document.getElementById(`selection-bar-${courtId}`);
        if (!bar) return;

        if (courtData && courtData.slots.length > 0) {
            const total = courtData.slots.reduce((sum, s) => sum + s.price, 0);
            const earliest = courtData.slots[0].start;
            const latest = courtData.slots[courtData.slots.length - 1].end;

            bar.querySelector('.selected-count').textContent = courtData.slots.length;
            bar.querySelector('.selected-time-range').textContent = `${earliest} – ${latest}`;
            bar.querySelector('.selected-total-price').textContent = 'Rp ' + total.toLocaleString('id-ID');
            bar.classList.remove('hidden');
            bar.classList.add('flex');
        } else {
            bar.classList.add('hidden');
            bar.classList.remove('flex');
        }
    }

    function clearCourtSelection(courtId) {
        if (courtSelections[courtId]) {
            courtSelections[courtId].slots = [];
            updateCourtUI(courtId);
        }
    }

    function openBookingModal(courtId) {
        @guest
            const promptDialog = document.getElementById('guestLoginPromptDialog');
            if (promptDialog) {
                promptDialog.showModal();
                return;
            } else {
                window.location.href = "{{ route('login') }}";
                return;
            }
        @endguest

        const courtData = courtSelections[courtId];
        if (!courtData || courtData.slots.length === 0) return;

        const earliest = courtData.slots[0].start;
        const latest = courtData.slots[courtData.slots.length - 1].end;
        const total = courtData.slots.reduce((sum, s) => sum + s.price, 0);

        document.getElementById('modalCourtId').value = courtData.courtId;
        document.getElementById('modalBookingDate').value = courtData.date;
        document.getElementById('modalBookingStart').value = earliest;
        document.getElementById('modalBookingEnd').value = latest;

        document.getElementById('modalCourtNameDisplay').textContent = courtData.courtName;
        document.getElementById('modalVenueNameDisplay').textContent = courtData.venueName || 'Matcha Arena';
        document.getElementById('modalDateDisplay').textContent = courtData.dateFormatted;
        document.getElementById('modalTimeRangeDisplay').textContent = `${earliest} – ${latest} (${courtData.slots.length} slot)`;
        document.getElementById('modalTotalAmountDisplay').textContent = 'Rp ' + total.toLocaleString('id-ID');

        const dialog = document.getElementById('userBookingDialog');
        if (dialog) {
            dialog.showModal();
        }
    }

    function handleBookingSubmit(e) {
        const submitBtn = document.getElementById('submitBookingBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i>Memproses Booking...';
        }
    }
</script>
@endpush
@endsection
