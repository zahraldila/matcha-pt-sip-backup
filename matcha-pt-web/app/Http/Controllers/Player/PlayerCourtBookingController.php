<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtBooking;
use App\Models\CourtBookingPayment;
use App\Models\Sport;
use App\Models\Venue;
use App\Services\CourtScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlayerCourtBookingController extends Controller
{
    public function dashboard(Request $request, CourtScheduleService $scheduleService)
    {
        @set_time_limit(300);
        if (Auth::user()?->role === 'venue_owner') {
            return redirect()->route('owner.dashboard');
        }

        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'sport' => ['nullable', 'string', 'max:80'],
            'period' => ['nullable', 'in:all,morning,afternoon,evening'],
            'venue_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $selectedDate = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        $weekday = (int) $selectedDate->dayOfWeekIso;
        $selectedSport = $request->query('sport', 'all');
        $period = $request->query('period', 'all');
        $selectedVenueId = $request->query('venue_id');
        $search = $request->query('search');

        $sports = Sport::query()->orderBy('nama_sport')->get();
        $venues = Venue::query()->orderBy('nama_venue')->get();

        $courtsQuery = Court::query()
            ->with(['venue', 'sport', 'facilities', 'schedules'])
            ->where('status_ketersediaan', '!=', 'Inactive')
            ->whereHas('venue')
            ->orderBy('nama_court');

        if ($selectedSport && $selectedSport !== 'all') {
            $courtsQuery->whereHas('sport', fn ($q) => $q->whereRaw('LOWER(nama_sport) = ?', [strtolower($selectedSport)]));
        }

        if ($selectedVenueId) {
            $courtsQuery->where('venue_id', $selectedVenueId);
        }

        if ($search) {
            $courtsQuery->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(nama_court) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhereHas('venue', function ($vq) use ($search) {
                        $vq->whereRaw('LOWER(nama_venue) LIKE ?', ['%'.strtolower($search).'%'])
                            ->orWhereRaw('LOWER(alamat) LIKE ?', ['%'.strtolower($search).'%'])
                            ->orWhereRaw('LOWER(kota) LIKE ?', ['%'.strtolower($search).'%']);
                    });
            });
        }

        $allCourts = $courtsQuery->get();

        $courtIds = $allCourts->pluck('court_id')->all();
        $dateStr = $selectedDate->toDateString();
        $bookingsByCourt = \App\Models\CourtBooking::query()
            ->whereIn('court_id', $courtIds)
            ->whereDate('tanggal', $dateStr)
            ->whereIn('status', ['Booked', 'Pending Payment'])
            ->get()
            ->groupBy('court_id');
        $blocksByCourt = \App\Models\CourtSlotBlock::query()
            ->whereIn('court_id', $courtIds)
            ->whereDate('tanggal', $dateStr)
            ->get()
            ->groupBy('court_id');

        $courts = $allCourts->map(function (Court $court) use ($scheduleService, $selectedDate, $period, $weekday, $bookingsByCourt, $blocksByCourt) {
            $court->setAttribute('user_slots', $scheduleService->slotsFor($court, $selectedDate, $bookingsByCourt->get($court->court_id, collect()), $blocksByCourt->get($court->court_id, collect()))
                ->filter(function (array $slot) use ($period) {
                    $hour = (int) substr($slot['start'], 0, 2);

                    return match ($period) {
                        'morning' => $hour < 12,
                        'afternoon' => $hour >= 12 && $hour < 17,
                        'evening' => $hour >= 17,
                        default => true,
                    };
                })
                ->values());
            $court->setAttribute('schedule_settings', $scheduleService->settingsFor($court, $weekday));

            return $court;
        });

        $days = collect(range(0, 5))->map(fn (int $offset) => now()->startOfDay()->addDays($offset));

        $activeBookingsCount = Auth::check() ? CourtBooking::query()
            ->where('created_by', Auth::id())
            ->whereIn('status', ['Booked', 'Pending Payment'])
            ->whereDate('tanggal', '>=', now()->toDateString())
            ->count() : 0;

        return view('player.dashboard', compact(
            'courts',
            'sports',
            'venues',
            'selectedDate',
            'selectedSport',
            'period',
            'selectedVenueId',
            'search',
            'weekday',
            'days',
            'activeBookingsCount'
        ));
    }

    public function store(Request $request, CourtScheduleService $scheduleService)
    {
        $validated = $request->validate([
            'court_id' => ['required', 'integer', 'exists:tb_court,court_id'],
            'tanggal' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'nama_penyewa' => ['required', 'string', 'max:120'],
            'kontak_penyewa' => ['nullable', 'string', 'max:80'],
            'metode_pembayaran' => ['nullable', 'string', 'max:40'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $booking = DB::transaction(function () use ($validated, $scheduleService) {
                $court = Court::query()
                    ->where('court_id', $validated['court_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $expectedAmount = $scheduleService->assertRangeAvailable(
                    $court,
                    $validated['tanggal'],
                    $validated['jam_mulai'],
                    $validated['jam_selesai']
                );

                $startsAt = Carbon::parse($validated['tanggal'].' '.$validated['jam_mulai']);
                if (! $startsAt->isFuture()) {
                    throw ValidationException::withMessages([
                        'jam_mulai' => 'Booking tidak dapat dibuat untuk slot waktu yang sudah lewat.',
                    ]);
                }

                $booking = CourtBooking::query()->create([
                    'court_id' => $court->court_id,
                    'tanggal' => $validated['tanggal'],
                    'jam_mulai' => $validated['jam_mulai'],
                    'jam_selesai' => $validated['jam_selesai'],
                    'nama_penyewa' => $validated['nama_penyewa'],
                    'kontak_penyewa' => $validated['kontak_penyewa'] ?? Auth::user()?->no_hp,
                    'jumlah_bayar' => $expectedAmount,
                    'status' => 'Booked',
                    'status_pembayaran' => 'Paid',
                    'sumber' => 'online',
                    'catatan' => $validated['catatan'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                CourtBookingPayment::query()->create([
                    'booking_id' => $booking->booking_id,
                    'provider' => 'online',
                    'metode_pembayaran' => $validated['metode_pembayaran'] ?? 'QRIS',
                    'jumlah' => $expectedAmount,
                    'status' => 'Paid',
                    'paid_at' => now(),
                ]);

                return $booking;
            });
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            return back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', collect($e->errors())->flatten()->first());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Booking berhasil dikonfirmasi! Lapangan berhasil dipesan.',
                'data' => $booking->load(['court.venue', 'payments']),
            ], 201);
        }

        return redirect()->route('player.bookings.index')
            ->with('success', 'Booking court berhasil dikonfirmasi! Selamat bermain.');
    }

    public function myBookings(Request $request)
    {
        $statusFilter = $request->query('status', 'all');
        $statusMap = ['booked' => 'Booked', 'pending' => 'Pending Payment', 'cancelled' => 'Cancelled'];
        $wanted = $statusMap[$statusFilter] ?? null;

        // Booking lama (CourtBooking)
        $old = CourtBooking::query()
            ->where('created_by', Auth::id())
            ->with(['court.venue', 'court.sport', 'payments', 'cancellations'])
            ->get();

        // Booking dari alur baru (tb_booking), dipetakan ke bentuk yang dipakai view
        $new = \App\Models\Booking::query()
            ->where('user_id', Auth::id())
            ->with(['court.venue', 'court.sport'])
            ->get()
            ->map(function ($b) {
                $status = match ($b->booking_status) {
                    'confirmed' => 'Booked',
                    'pending' => 'Pending Payment',
                    'cancelled' => 'Cancelled',
                    default => ucfirst((string) $b->booking_status),
                };

                return (object) [
                    'status' => $status,
                    'court' => $b->court,
                    'payments' => collect(),
                    'cancellations' => collect(),
                    'sumber' => 'online',
                    'tanggal' => \Carbon\Carbon::parse($b->booking_date)->startOfDay(),
                    'jam_mulai' => substr((string) $b->start_time, 0, 8),
                    'jam_selesai' => substr((string) $b->end_time, 0, 8),
                    'jumlah_bayar' => (float) $b->total_price,
                    'status_pembayaran' => strtolower((string) $b->payment_status) === 'paid' ? 'Paid' : 'Unpaid',
                    'catatan' => null,
                    'alasan_pembatalan' => null,
                    'cancelled_at' => null,
                ];
            });

        $all = $old->concat($new);
        $counts = [
            'all' => $all->count(),
            'booked' => $all->where('status', 'Booked')->count(),
            'pending' => $all->where('status', 'Pending Payment')->count(),
            'cancelled' => $all->where('status', 'Cancelled')->count(),
        ];

        if ($wanted) {
            $all = $all->where('status', $wanted);
        }

        $all = $all->sortByDesc(fn ($b) => $b->tanggal->format('Y-m-d').' '.substr((string) $b->jam_mulai, 0, 5))->values();

        $perPage = 10;
        $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $bookings = new \Illuminate\Pagination\LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('player.my-bookings', compact('bookings', 'counts', 'statusFilter'));
    }
}