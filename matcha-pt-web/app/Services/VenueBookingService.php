<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VenueBookingService
{
    public function getSlots(Court $court, string $date, ?int $userId = null): array
    {
        $operatingHours = $this->getOperatingHours($court, $date);
        if ($operatingHours === null) {
            return [];
        }

        [$open, $close] = $operatingHours;
        $occupied = BookingSlot::query()
            ->with('booking')
            ->where('court_id', $court->court_id)
            ->whereDate('slot_date', $date)
            ->get()
            ->keyBy(fn (BookingSlot $slot) => substr((string) $slot->slot_start, 0, 5));

        $slots = [];
        $cursor = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$open);
        $closing = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$close);

        while ($cursor->lt($closing)) {
            $start = $cursor->format('H:i');
            $occupiedSlot = $occupied->get($start);
            $status = 'Tersedia';

            if ($occupiedSlot) {
                $booking = $occupiedSlot->booking;
                $status = $userId && $booking && (int) $booking->user_id === $userId
                    ? 'Jadwal Saya'
                    : 'Terisi';
            }

            $slots[] = [
                'court_id' => $court->court_id,
                'date' => $date,
                'start' => $start,
                'end' => $cursor->copy()->addHour()->format('H:i'),
                'status' => $status,
            ];
            $cursor->addHour();
        }

        return $slots;
    }

    public function createBooking(array $data, int $userId): Booking
    {
        $court = Court::query()->findOrFail((int) $data['court_id']);
        $bookingDate = (string) $data['booking_date'];
        $startTime = $this->normalizeTime((string) $data['start_time']);
        $endTime = $this->normalizeTime((string) $data['end_time']);

        $date = Carbon::createFromFormat('Y-m-d', $bookingDate)->startOfDay();
        if ($date->lt(Carbon::today())) {
            throw ValidationException::withMessages(['booking_date' => 'Tanggal booking tidak boleh lewat.']);
        }

        $operatingHours = $this->getOperatingHours($court, $bookingDate);
        if ($operatingHours === null) {
            throw ValidationException::withMessages(['booking_date' => 'Venue tutup pada tanggal yang dipilih.']);
        }

        [$open, $close] = $operatingHours;
        if ($startTime < $open || $endTime > $close || $startTime >= $endTime) {
            throw ValidationException::withMessages([
                'start_time' => "Jam harus berada dalam jam operasional {$open} - {$close} WIB dan berurutan.",
            ]);
        }

        $start = Carbon::createFromFormat('Y-m-d H:i', $bookingDate.' '.$startTime);
        $end = Carbon::createFromFormat('Y-m-d H:i', $bookingDate.' '.$endTime);
        $minutes = $start->diffInMinutes($end);
        if ($minutes <= 0 || $minutes % 60 !== 0) {
            throw ValidationException::withMessages(['start_time' => 'Booking hanya dapat dibuat dalam slot berurutan berdurasi satu jam.']);
        }

        $durationHours = (int) ($minutes / 60);
        $slotTimes = [];
        $cursor = $start->copy();
        while ($cursor->lt($end)) {
            $slotTimes[] = $cursor->format('H:i');
            $cursor->addHour();
        }

        $hourlyPrice = (float) ($court->harga_per_jam ?? 0);
        $totalPrice = $hourlyPrice * $durationHours;

        try {
            return DB::transaction(function () use ($court, $bookingDate, $startTime, $endTime, $durationHours, $totalPrice, $data, $userId, $slotTimes) {
                $sequence = Booking::query()
                    ->whereDate('booking_date', $bookingDate)
                    ->lockForUpdate()
                    ->count() + 1;
                $bookingCode = 'MT-'.Carbon::parse($bookingDate)->format('Ymd').'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

                $booking = Booking::create([
                    'booking_code' => $bookingCode,
                    'user_id' => $userId,
                    'court_id' => $court->court_id,
                    'booking_date' => $bookingDate,
                    'start_time' => $startTime.':00',
                    'end_time' => $endTime.':00',
                    'duration_hours' => $durationHours,
                    'total_price' => $totalPrice,
                    'customer_name' => (string) $data['customer_name'],
                    'customer_phone' => (string) $data['customer_phone'],
                    'booking_status' => 'confirmed',
                    'payment_method' => 'cash',
                    'payment_status' => 'unpaid',
                ]);

                foreach ($slotTimes as $slotTime) {
                    BookingSlot::create([
                        'booking_id' => $booking->booking_id,
                        'court_id' => $court->court_id,
                        'slot_date' => $bookingDate,
                        'slot_start' => $slotTime.':00',
                    ]);
                }

                return $booking->load(['court', 'user', 'slots']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'slot' => 'Slot sudah dipesan orang lain',
            ]);
        } catch (QueryException $exception) {
            if ($exception->errorInfo[1] === 19 || str_contains(strtolower($exception->getMessage()), 'unique constraint')) {
                throw ValidationException::withMessages(['slot' => 'Slot sudah dipesan orang lain']);
            }
            throw $exception;
        }
    }

    public function getDetail(int $bookingId): Booking
    {
        return Booking::query()
            ->with(['court.venue', 'user', 'slots'])
            ->findOrFail($bookingId);
    }

    public function cancelBooking(int $bookingId, int $userId): Booking
    {
        return DB::transaction(function () use ($bookingId, $userId) {
            $booking = Booking::query()
                ->where('booking_id', $bookingId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->booking_status !== 'cancelled') {
                $booking->booking_status = 'cancelled';
                $booking->save();
            }

            BookingSlot::query()->where('booking_id', $booking->booking_id)->delete();

            return $booking->load(['court', 'user', 'slots']);
        });
    }

    private function getOperatingHours(Court $court, string $date): ?array
    {
        $court->loadMissing('venue.availabilities');
        $venue = $court->venue;
        if (! $venue) {
            return null;
        }

        $dayName = $this->indonesianDayName(Carbon::parse($date)->dayOfWeek);
        $availability = $venue->availabilities
            ->first(fn ($item) => str_contains(strtolower((string) $item->hari), strtolower($dayName)));

        if ($availability && $availability->jam_buka && $availability->jam_tutup) {
            return [
                $this->normalizeTime((string) $availability->jam_buka),
                $this->normalizeTime((string) $availability->jam_tutup),
            ];
        }

        $operatingHours = (string) ($venue->jam_operasional ?? '');
        if (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $operatingHours, $matches)) {
            return [$this->normalizeTime($matches[1]), $this->normalizeTime($matches[2])];
        }

        return null;
    }

    private function indonesianDayName(int $dayOfWeek): string
    {
        return [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ][$dayOfWeek];
    }

    private function normalizeTime(string $time): string
    {
        $time = trim($time);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?/', $time, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return $time;
    }
}
