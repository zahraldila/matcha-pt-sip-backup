<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Http\Resources\VenueOwner\VenueOwnerBookingResource;
use App\Http\Resources\VenueOwner\VenueOwnerVenueResource;
use App\Models\Court;
use App\Models\CourtBooking;
use App\Models\Sport;
use App\Models\Venue;
use App\Services\CourtScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class VenueOwnerApiController extends Controller
{
    public function sports(): JsonResponse
    {
        return response()->json([
            'data' => Sport::query()
                ->orderBy('nama_sport')
                ->get(['sport_id', 'nama_sport'])
                ->map(fn (Sport $sport) => [
                    'id' => $sport->sport_id,
                    'name' => $sport->nama_sport,
                ]),
        ]);
    }

    public function venues(): AnonymousResourceCollection
    {
        $venues = Venue::query()
            ->where('owner_user_id', Auth::id())
            ->with([
                'facilities.sport',
                'courts.sport',
                'courts.facilities.sport',
                'courts.schedules',
            ])
            ->orderBy('nama_venue')
            ->get();

        return VenueOwnerVenueResource::collection($venues);
    }

    public function venue(int $venueId): VenueOwnerVenueResource
    {
        $venue = Venue::query()
            ->where('owner_user_id', Auth::id())
            ->with([
                'facilities.sport',
                'courts.sport',
                'courts.facilities.sport',
                'courts.schedules',
            ])
            ->findOrFail($venueId);

        return new VenueOwnerVenueResource($venue);
    }

    public function schedule(Request $request, int $courtId, CourtScheduleService $scheduleService): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        $court = $this->ownedCourt($courtId);
        $date = Carbon::parse($validated['date']);
        $settings = $scheduleService->settingsFor($court, (int) $date->dayOfWeekIso);
        $slots = $scheduleService->slotsFor($court, $date)->map(fn (array $slot) => [
            'starts_at' => $slot['start'],
            'ends_at' => $slot['end'],
            'price' => $slot['price'],
            'status' => $slot['status'],
            'booking' => $slot['booking']
                ? (new VenueOwnerBookingResource($slot['booking']))->resolve($request)
                : null,
        ]);

        return response()->json([
            'data' => [
                'court_id' => $court->court_id,
                'date' => $date->toDateString(),
                'settings' => [
                    'is_open' => $settings['is_open'],
                    'opens_at' => $settings['start'],
                    'closes_at' => $settings['end'],
                    'slot_duration_minutes' => $settings['duration'],
                    'price_per_slot' => $settings['price'],
                ],
                'slots' => $slots->values(),
            ],
        ]);
    }

    public function bookings(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:Pending Payment,Booked,Cancelled'],
        ]);

        $bookings = CourtBooking::query()
            ->whereHas('court.venue', fn ($query) => $query->where('owner_user_id', Auth::id()))
            ->when($validated['date'] ?? null, fn ($query, $date) => $query->whereDate('tanggal', $date))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->with(['court.sport', 'payments', 'cancellations'])
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->get();

        return VenueOwnerBookingResource::collection($bookings);
    }

    private function ownedCourt(int $courtId): Court
    {
        return Court::query()
            ->where('court_id', $courtId)
            ->whereHas('venue', fn ($query) => $query->where('owner_user_id', Auth::id()))
            ->with('venue')
            ->firstOrFail();
    }
}
