<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Services\VenueBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function __construct(private VenueBookingService $bookingService) {}

    public function slots(Request $request, int $courtId): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $court = Court::query()->with('venue.availabilities')->findOrFail($courtId);

        return response()->json([
            'slots' => $this->bookingService->getSlots(
                $court,
                $validated['date'],
                Auth::id()
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'court_id' => ['required', 'integer', 'exists:tb_court,court_id'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
        ]);

        $booking = $this->bookingService->createBooking($validated, (int) Auth::id());

        return response()->json([
            'booking' => $booking,
        ], 201);
    }

    public function show(int $bookingId): JsonResponse
    {
        $booking = $this->bookingService->getDetail($bookingId);

        if ((int) $booking->user_id !== (int) Auth::id()) {
            abort(403);
        }

        return response()->json(['booking' => $booking]);
    }

    public function cancel(int $bookingId): JsonResponse
    {
        $booking = $this->bookingService->cancelBooking($bookingId, (int) Auth::id());

        return response()->json(['booking' => $booking]);
    }

    public function confirm(int $bookingId): JsonResponse
    {
        $booking = $this->bookingService->confirmBooking($bookingId, (int) Auth::id());

        return response()->json(['booking' => $booking]);
    }
}
