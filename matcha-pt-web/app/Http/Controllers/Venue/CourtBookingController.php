<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtBooking;
use App\Services\CourtScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            if ($startsAt->isPast()) {
                abort(422, 'Booking tidak dapat dibuat untuk waktu yang sudah lewat.');
            }

            return CourtBooking::query()->create([
                'court_id' => $lockedCourt->court_id,
                'tanggal' => $validated['tanggal'],
                'jam_mulai' => $validated['jam_mulai'],
                'jam_selesai' => $validated['jam_selesai'],
                'nama_penyewa' => $validated['nama_penyewa'],
                'kontak_penyewa' => $validated['kontak_penyewa'] ?? null,
                'jumlah_bayar' => $validated['jumlah_bayar'] ?? $expectedAmount,
                'status' => $validated['status_pembayaran'] === 'Pending' ? 'Pending Payment' : 'Booked',
                'status_pembayaran' => $validated['status_pembayaran'],
                'sumber' => 'manual',
                'catatan' => $validated['catatan'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });

        return redirect()->route('owner.dashboard', [
            'venue_id' => $court->venue_id,
            'date' => $booking->tanggal->toDateString(),
        ])->with('success', 'Booking manual berhasil disimpan.');
    }

    public function cancel(Request $request, int $bookingId)
    {
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

            $lockedBooking->update([
                'status' => 'Cancelled',
                'alasan_pembatalan' => $validated['alasan_pembatalan'],
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
            ]);
        });

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
