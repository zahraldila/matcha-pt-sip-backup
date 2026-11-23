<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtBooking;
use App\Models\CourtSchedule;
use App\Models\CourtSlotBlock;
use App\Models\Sport;
use App\Models\Venue;
use App\Models\VenueFacility;
use App\Services\CourtScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VenueOwnerDashboardController extends Controller
{
    public function index(Request $request, CourtScheduleService $scheduleService)
    {
        abort_unless(Auth::user()?->role === 'venue_owner', 403);

        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'venue_id' => ['nullable', 'integer'],
            'sport' => ['nullable', 'string', 'max:80'],
            'period' => ['nullable', 'in:all,morning,afternoon,evening'],
        ]);

        $venues = Venue::query()
            ->where('owner_user_id', Auth::id())
            ->with(['courts.sport', 'courts.facilities', 'courts.schedules'])
            ->orderBy('nama_venue')
            ->get();
        $venue = $venues->firstWhere('venue_id', (int) $request->query('venue_id')) ?? $venues->first();
        $selectedDate = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        $weekday = (int) $selectedDate->dayOfWeekIso;
        $selectedSport = $request->query('sport', 'all');
        $period = $request->query('period', 'all');

        $courts = collect();
        $facilities = collect();
        $bookings = collect();
        $sports = Sport::query()->orderBy('nama_sport')->get();

        if ($venue) {
            $facilities = VenueFacility::query()
                ->where('venue_id', $venue->venue_id)
                ->with('sport')
                ->orderBy('nama_fasilitas')
                ->get();

            $allCourts = $venue->courts->sortBy('court_id')->values();
            $courtIds = $allCourts->pluck('court_id');
            $bookings = CourtBooking::query()
                ->with('court')
                ->whereIn('court_id', $courtIds)
                ->whereDate('tanggal', $selectedDate->toDateString())
                ->orderBy('jam_mulai')
                ->get();

            $courts = $allCourts
                ->filter(function (Court $court) use ($selectedSport) {
                    return $selectedSport === 'all'
                        || strtolower((string) $court->sport?->nama_sport) === strtolower($selectedSport);
                })
                ->map(function (Court $court) use ($scheduleService, $selectedDate, $period, $weekday) {
                    $court->setAttribute('owner_slots', $scheduleService->slotsFor($court, $selectedDate)
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
                    $court->setAttribute('owner_schedule', $scheduleService->settingsFor($court, $weekday));

                    return $court;
                })
                ->values();
        }

        $days = collect(range(0, 5))->map(fn (int $offset) => now()->startOfDay()->addDays($offset));

        return view('venue-owner.dashboard', compact(
            'venues',
            'venue',
            'courts',
            'facilities',
            'bookings',
            'sports',
            'selectedDate',
            'selectedSport',
            'period',
            'weekday',
            'days'
        ) + ['ownerPortal' => true]);
    }

    public function saveSchedule(Request $request, int $courtId)
    {
        $court = $this->ownedCourt($courtId);
        $validated = $request->validate([
            'weekday' => ['required', 'integer', 'between:1,7'],
            'is_open' => ['required', 'boolean'],
            'jam_buka' => ['required_if:is_open,1', 'date_format:H:i'],
            'jam_tutup' => ['required_if:is_open,1', 'date_format:H:i'],
            'durasi_menit' => ['required', 'integer', 'in:30,60,90,120'],
            'harga_per_slot' => ['required', 'numeric', 'min:0'],
        ]);

        if ($validated['is_open'] && $validated['jam_tutup'] <= $validated['jam_buka']) {
            return back()->withErrors(['jam_tutup' => 'Jam tutup harus setelah jam buka.'])->withInput();
        }

        DB::transaction(function () use ($court, $validated) {
            Court::query()->where('court_id', $court->court_id)->lockForUpdate()->firstOrFail();
            $futureBookings = CourtBooking::query()
                ->where('court_id', $court->court_id)
                ->whereIn('status', ['Booked', 'Pending Payment'])
                ->whereDate('tanggal', '>=', now()->toDateString())
                ->get();
            $futureBlocks = CourtSlotBlock::query()
                ->where('court_id', $court->court_id)
                ->whereDate('tanggal', '>=', now()->toDateString())
                ->get();
            foreach ($futureBookings->concat($futureBlocks) as $reservation) {
                $reservationDate = Carbon::parse($reservation->tanggal);
                if ((int) $reservationDate->dayOfWeekIso !== (int) $validated['weekday']) {
                    continue;
                }
                $start = substr($reservation->jam_mulai, 0, 5);
                $end = substr($reservation->jam_selesai, 0, 5);
                $open = $validated['jam_buka'] ?? '00:00';
                $close = $validated['jam_tutup'] ?? '00:00';
                $openMinutes = $this->minutes($open);
                $startMinutes = $this->minutes($start);
                $endMinutes = $this->minutes($end);
                if (! $validated['is_open'] || $start < $open || $end > $close
                    || ($startMinutes - $openMinutes) % (int) $validated['durasi_menit'] !== 0
                    || ($endMinutes - $startMinutes) % (int) $validated['durasi_menit'] !== 0) {
                    abort(422, 'Jadwal tidak dapat diubah karena bertabrakan dengan booking atau slot blokir yang sudah ada.');
                }
            }

            CourtSchedule::query()->updateOrCreate(
                ['court_id' => $court->court_id, 'weekday' => $validated['weekday']],
                [
                    'is_open' => $validated['is_open'],
                    'jam_buka' => $validated['jam_buka'] ?? '00:00',
                    'jam_tutup' => $validated['jam_tutup'] ?? '00:00',
                    'durasi_menit' => $validated['durasi_menit'],
                    'harga_per_slot' => $validated['harga_per_slot'],
                ]
            );
        });

        return $this->dashboardRedirect($court->venue_id, $request)->with('success', 'Jadwal operasional court diperbarui.');
    }

    public function blockSlot(Request $request, int $courtId, CourtScheduleService $scheduleService)
    {
        $court = $this->ownedCourt($courtId);
        $validated = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'alasan' => ['nullable', 'string', 'max:180'],
        ]);

        DB::transaction(function () use ($court, $validated, $scheduleService) {
            $lockedCourt = Court::query()->where('court_id', $court->court_id)->lockForUpdate()->firstOrFail();
            $scheduleService->assertRangeAvailable($lockedCourt, $validated['tanggal'], $validated['jam_mulai'], $validated['jam_selesai']);
            CourtSlotBlock::query()->create([
                'court_id' => $lockedCourt->court_id,
                'tanggal' => Carbon::parse($validated['tanggal'])->toDateString(),
                'jam_mulai' => $validated['jam_mulai'],
                'jam_selesai' => $validated['jam_selesai'],
                'alasan' => $validated['alasan'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });

        return $this->dashboardRedirect($court->venue_id, $request)->with('success', 'Slot waktu dinonaktifkan.');
    }

    public function unblockSlot(Request $request, int $courtId, int $blockId)
    {
        $court = $this->ownedCourt($courtId);
        CourtSlotBlock::query()
            ->where('court_id', $court->court_id)
            ->where('block_id', $blockId)
            ->firstOrFail()
            ->delete();

        return $this->dashboardRedirect($court->venue_id, $request)->with('success', 'Slot kembali tersedia.');
    }

    private function ownedCourt(int $courtId): Court
    {
        abort_unless(Auth::user()?->role === 'venue_owner', 403);

        return Court::query()
            ->where('court_id', $courtId)
            ->whereHas('venue', fn ($query) => $query->where('owner_user_id', Auth::id()))
            ->firstOrFail();
    }

    private function dashboardRedirect(int $venueId, Request $request)
    {
        return redirect()->route('owner.dashboard', [
            'venue_id' => $venueId,
            'date' => $request->input('date', $request->input('tanggal', now()->toDateString())),
        ]);
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));

        return ($hours * 60) + $minutes;
    }
}
