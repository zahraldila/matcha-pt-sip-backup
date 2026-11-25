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

        $courts = $allCourts->map(function (Court $court) use ($scheduleService, $selectedDate, $period, $weekday) {
            $court->setAttribute('user_slots', $scheduleService->slotsFor($court, $selectedDate)
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

        $bookingsQuery = CourtBooking::query()
            ->where('created_by', Auth::id())
            ->with(['court.venue', 'court.sport', 'payments', 'cancellations'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('jam_mulai', 'desc');

        if ($statusFilter !== 'all') {
            $mappedStatus = match ($statusFilter) {
                'booked' => 'Booked',
                'pending' => 'Pending Payment',
                'cancelled' => 'Cancelled',
                default => $statusFilter,
            };
            $bookingsQuery->where('status', $mappedStatus);
        }

        $bookings = $bookingsQuery->paginate(10)->withQueryString();

        $counts = [
            'all' => CourtBooking::query()->where('created_by', Auth::id())->count(),
            'booked' => CourtBooking::query()->where('created_by', Auth::id())->where('status', 'Booked')->count(),
            'pending' => CourtBooking::query()->where('created_by', Auth::id())->where('status', 'Pending Payment')->count(),
            'cancelled' => CourtBooking::query()->where('created_by', Auth::id())->where('status', 'Cancelled')->count(),
        ];

        return view('player.my-bookings', compact('bookings', 'counts', 'statusFilter'));
    }
}
