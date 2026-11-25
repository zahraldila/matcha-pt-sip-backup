@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-5xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
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

    <!-- Header Section -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-[#063B00]">Aktivitas Saya</p>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Booking Saya</h1>
            <p class="mt-1 text-xs text-slate-500">Kelola dan pantau seluruh riwayat sewa lapangan serta status pemesanan court kamu.</p>
        </div>
        <a href="{{ route('user.dashboard') }}" class="inline-flex items-center gap-2 self-start rounded-xl bg-[#063B00] px-4 py-2.5 text-xs font-extrabold text-white shadow-xs hover:bg-[#042a00] transition sm:self-auto">
            <i class="fa-solid fa-plus text-[#A8E63A]"></i>
            <span>Sewa Court Baru</span>
        </a>
    </div>

    <!-- Filter Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200">
        <a href="{{ route('player.bookings.index') }}"
           class="whitespace-nowrap px-3.5 py-2 text-xs font-extrabold border-b-2 transition {{ $statusFilter === 'all' ? 'border-[#063B00] text-[#063B00]' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
            Semua ({{ $counts['all'] }})
        </a>
        <a href="{{ route('player.bookings.index', ['status' => 'booked']) }}"
           class="whitespace-nowrap px-3.5 py-2 text-xs font-extrabold border-b-2 transition {{ $statusFilter === 'booked' ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
            Booked ({{ $counts['booked'] }})
        </a>
        <a href="{{ route('player.bookings.index', ['status' => 'pending']) }}"
           class="whitespace-nowrap px-3.5 py-2 text-xs font-extrabold border-b-2 transition {{ $statusFilter === 'pending' ? 'border-amber-600 text-amber-700' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
            Pending Payment ({{ $counts['pending'] }})
        </a>
        <a href="{{ route('player.bookings.index', ['status' => 'cancelled']) }}"
           class="whitespace-nowrap px-3.5 py-2 text-xs font-extrabold border-b-2 transition {{ $statusFilter === 'cancelled' ? 'border-rose-600 text-rose-700' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
            Dibatalkan ({{ $counts['cancelled'] }})
        </a>
    </div>

    <!-- List of Bookings -->
    @if($bookings->isEmpty())
        <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-xs">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400">
                <i class="fa-regular fa-calendar-xmark"></i>
            </span>
            <h3 class="mt-4 text-base font-extrabold text-slate-900">Belum ada riwayat booking</h3>
            <p class="mx-auto mt-1 max-w-sm text-xs text-slate-500">
                @if($statusFilter !== 'all')
                    Tidak ada booking dengan status <strong>{{ ucfirst($statusFilter) }}</strong> saat ini.
                @else
                    Kamu belum memiliki pemesanan court. Mulai cari venue dan sewa lapangan sekarang!
                @endif
            </p>
            <div class="mt-6 flex justify-center gap-3">
                @if($statusFilter !== 'all')
                    <a href="{{ route('player.bookings.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        Lihat Semua Status
                    </a>
                @endif
                <a href="{{ route('user.dashboard') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-[#063B00] px-4 py-2 text-xs font-bold text-white hover:bg-[#042a00]">
                    <i class="fa-solid fa-magnifying-glass text-[#A8E63A]"></i> Cari &amp; Sewa Court
                </a>
            </div>
        </div>
    @else
        <div class="space-y-4">
            @foreach($bookings as $booking)
                @php
                    $statusClass = match($booking->status) {
                        'Booked' => 'border-blue-200 bg-blue-50 text-blue-700',
                        'Pending Payment' => 'border-amber-200 bg-amber-50 text-amber-700',
                        'Cancelled' => 'border-rose-200 bg-rose-50 text-rose-700',
                        default => 'border-slate-200 bg-slate-100 text-slate-700',
                    };
                    $paymentMethod = $booking->payments->first()?->metode_pembayaran ?: ($booking->sumber === 'manual' ? 'Manual Owner' : 'Online');
                    $isCancelled = $booking->status === 'Cancelled';
                    
                    $startObj = \Carbon\Carbon::parse($booking->tanggal->format('Y-m-d').' '.$booking->jam_mulai);
                    $endObj = \Carbon\Carbon::parse($booking->tanggal->format('Y-m-d').' '.$booking->jam_selesai);
                    $durationHours = $endObj->diffInHours($startObj);
                    $durationMinutes = $endObj->diffInMinutes($startObj) % 60;
                    $durationText = ($durationHours > 0 ? $durationHours.' jam ' : '') . ($durationMinutes > 0 ? $durationMinutes.' menit' : '');
                @endphp
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition hover:border-slate-300 hover:shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EBF8D8] text-base text-[#063B00]">
                                <i class="fa-solid fa-table-tennis-paddle-ball"></i>
                            </span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-sm font-black text-slate-900">{{ $booking->court?->nama_court ?? 'Court' }}</h2>
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                                        {{ $booking->court?->sport?->nama_sport ?? 'Olahraga' }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 font-medium">
                                    <i class="fa-solid fa-location-dot mr-1 text-[#063B00]"></i>{{ $booking->court?->venue?->nama_venue ?? 'Matcha Arena' }} &bull; {{ $booking->court?->venue?->kota ?? 'Indonesia' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 self-start sm:self-auto">
                            <span class="rounded-full border px-3 py-1 text-xs font-black {{ $statusClass }}">
                                {{ $booking->status }}
                            </span>
                        </div>
                    </div>

                    <!-- Detail Grid -->
                    <div class="mt-3.5 grid grid-cols-2 gap-3 sm:grid-cols-4 text-xs">
                        <div class="rounded-xl bg-slate-50 p-2.5">
                            <span class="block text-[10px] font-bold text-slate-400">Tanggal Sewa</span>
                            <span class="mt-0.5 block font-extrabold text-slate-900">{{ $booking->tanggal->locale('id')->translatedFormat('l, d M Y') }}</span>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-2.5">
                            <span class="block text-[10px] font-bold text-slate-400">Jam & Durasi</span>
                            <span class="mt-0.5 block font-extrabold text-slate-900">
                                {{ substr((string)$booking->jam_mulai, 0, 5) }}–{{ substr((string)$booking->jam_selesai, 0, 5) }}
                                <span class="text-[10px] text-slate-500 font-normal">({{ $durationText ?: '1 slot' }})</span>
                            </span>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-2.5">
                            <span class="block text-[10px] font-bold text-slate-400">Total Biaya</span>
                            <span class="mt-0.5 block font-extrabold text-[#063B00]">Rp {{ number_format($booking->jumlah_bayar, 0, ',', '.') }}</span>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-2.5">
                            <span class="block text-[10px] font-bold text-slate-400">Metode & Status Bayar</span>
                            <span class="mt-0.5 block font-bold text-slate-900 truncate">
                                {{ $paymentMethod }} &bull; <span class="{{ $booking->status_pembayaran === 'Paid' ? 'text-emerald-700' : 'text-amber-700' }}">{{ $booking->status_pembayaran }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Catatan Booking jika ada -->
                    @if($booking->catatan)
                        <div class="mt-3 text-[11px] text-slate-500 bg-slate-50/70 p-2.5 rounded-lg border border-slate-100">
                            <span class="font-bold text-slate-600">Catatan Pemesan:</span> {{ $booking->catatan }}
                        </div>
                    @endif

                    <!-- Detail Alasan Pembatalan (Jika dibatalkan oleh Owner) -->
                    @if($isCancelled)
                        <div class="mt-3.5 rounded-xl border border-rose-200 bg-rose-50/70 p-3.5 text-xs">
                            <div class="flex items-center gap-2 font-black text-rose-800">
                                <i class="fa-solid fa-triangle-exclamation text-rose-600"></i>
                                <span>Booking Ini Telah Dibatalkan oleh Pengelola Venue</span>
                            </div>
                            <div class="mt-1.5 pl-5 text-slate-700">
                                <p><span class="font-bold">Alasan Pembatalan:</span> {{ $booking->alasan_pembatalan ?: 'Tidak disertakan alasan spesifik oleh pengelola.' }}</p>
                                @if($booking->cancelled_at)
                                    <p class="mt-0.5 text-[10px] text-slate-400">Dibatalkan pada: {{ $booking->cancelled_at->translatedFormat('l, d F Y - H:i') }} WIB</p>
                                @endif
                            </div>
                        </div>
                    @endif
                </article>
            @endforeach

            <!-- Pagination -->
            <div class="mt-4">
                {{ $bookings->links() }}
            </div>
        </div>
    @endif
</div>
@endsection
