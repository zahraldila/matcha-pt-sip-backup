<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Http\Resources\VenueOwner\VenueOwnerBookingResource;
use App\Models\Court;
use App\Models\CourtBooking;
use App\Models\CourtBookingCancellation;
use App\Models\CourtBookingPayment;
use App\Services\CourtScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourtBookingController extends Controller
{
    public function storeManual(Request $request, int $courtId, CourtScheduleService $scheduleService)
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'nama_penyewa' => ['required', 'string', 'max:120'],
            'kontak_penyewa' => ['nullable', 'string', 'max:80'],
            'status_pembayaran' => ['required', 'in:Paid,Pending,Unpaid'],
            'jumlah_bayar' => ['nullable', 'numeric', 'min:0'],
            'metode_pembayaran' => ['nullable', 'string', 'max:40'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $court = $this->ownedCourt($courtId);
        $booking = DB::transaction(function () use ($court, $validated, $scheduleService) {
            $lockedCourt = Court::query()
                ->where('court_id', $court->court_id)
                ->lockForUpdate()
                ->firstOrFail();
            $expectedAmount = $scheduleService->assertRangeAvailable(
                $lockedCourt,
                $validated['tanggal'],
                $validated['jam_mulai'],
                $validated['jam_selesai']
            );
            $startsAt = Carbon::parse($validated['tanggal'].' '.$validated['jam_mulai']);
            if (! $startsAt->isFuture()) {
                throw ValidationException::withMessages([
                    'jam_mulai' => 'Booking tidak dapat dibuat untuk waktu yang sudah lewat.',
                ]);
            }

            $amount = $validated['jumlah_bayar'] ?? $expectedAmount;
            $booking = CourtBooking::query()->create([
                'court_id' => $lockedCourt->court_id,
                'tanggal' => $validated['tanggal'],
                'jam_mulai' => $validated['jam_mulai'],
                'jam_selesai' => $validated['jam_selesai'],
                'nama_penyewa' => $validated['nama_penyewa'],
                'kontak_penyewa' => $validated['kontak_penyewa'] ?? null,
                'jumlah_bayar' => $amount,
                'status' => $validated['status_pembayaran'] === 'Pending' ? 'Pending Payment' : 'Booked',
                'status_pembayaran' => $validated['status_pembayaran'],
                'sumber' => 'manual',
                'catatan' => $validated['catatan'] ?? null,
                'created_by' => Auth::id(),
            ]);
            CourtBookingPayment::query()->create([
                'booking_id' => $booking->booking_id,
                'provider' => 'manual',
                'metode_pembayaran' => $validated['metode_pembayaran'] ?? null,
                'jumlah' => $amount,
                'status' => $validated['status_pembayaran'],
                'paid_at' => $validated['status_pembayaran'] === 'Paid' ? now() : null,
            ]);

            return $booking;
        });

        if ($request->expectsJson()) {
            return (new VenueOwnerBookingResource($booking->load(['court', 'payments'])))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()->route('owner.dashboard', [
            'venue_id' => $court->venue_id,
            'date' => $booking->tanggal->toDateString(),
        ])->with('success', 'Booking manual berhasil disimpan.');
    }

    public function cancel(Request $request, int $bookingId)
    {
        abort_unless(Auth::user()?->role === 'venue_owner', 403);

        $validated = $request->validate([
            'alasan_pembatalan' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $booking = CourtBooking::query()
            ->with('court.venue')
            ->where('booking_id', $bookingId)
            ->whereHas('court.venue', fn ($query) => $query->where('owner_user_id', Auth::id()))
            ->firstOrFail();

        DB::transaction(function () use ($booking, $validated) {
            Court::query()->where('court_id', $booking->court_id)->lockForUpdate()->firstOrFail();
            $lockedBooking = CourtBooking::query()->where('booking_id', $booking->booking_id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedBooking->status, ['Booked', 'Pending Payment'], true)) {
                abort(422, 'Booking ini sudah dibatalkan.');
            }

            CourtBookingCancellation::query()->create([
                'booking_id' => $lockedBooking->booking_id,
                'cancelled_by' => Auth::id(),
                'previous_status' => $lockedBooking->status,
                'previous_payment_status' => $lockedBooking->status_pembayaran,
                'reason' => $validated['alasan_pembatalan'],
                'cancelled_at' => now(),
            ]);

            $lockedBooking->update([
                'status' => 'Cancelled',
                'alasan_pembatalan' => $validated['alasan_pembatalan'],
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
            ]);
        });

        if ($request->expectsJson()) {
            return (new VenueOwnerBookingResource($booking->fresh()->load(['court', 'payments', 'cancellations'])))
                ->response();
        }

        return redirect()->route('owner.dashboard', [
            'venue_id' => $booking->court->venue_id,
            'date' => $booking->tanggal->toDateString(),
        ])->with('success', 'Booking dibatalkan dan riwayatnya tersimpan.');
    }

    private function ownedCourt(int $courtId): Court
    {
        abort_unless(Auth::user()?->role === 'venue_owner', 403);

        return Court::query()
            ->where('court_id', $courtId)
            ->whereHas('venue', fn ($query) => $query->where('owner_user_id', Auth::id()))
            ->firstOrFail();
    }
}
