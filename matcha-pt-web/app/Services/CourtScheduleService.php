<?php

namespace App\Services;

use App\Models\Court;
use App\Models\CourtBooking;
use App\Models\CourtSchedule;
use App\Models\CourtSlotBlock;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CourtScheduleService
{
    /**
     * @return array{is_open: bool, start: string, end: string, duration: int, price: float}
     */
    public function settingsFor(Court $court, int $weekday): array
    {
        $schedule = CourtSchedule::query()
            ->where('court_id', $court->court_id)
            ->where('weekday', $weekday)
            ->first();

        if ($schedule) {
            return [
                'is_open' => $schedule->is_open,
                'start' => substr((string) $schedule->jam_buka, 0, 5),
                'end' => substr((string) $schedule->jam_tutup, 0, 5),
                'duration' => (int) $schedule->durasi_menit,
                'price' => (float) $schedule->harga_per_slot,
            ];
        }

        $hours = (string) ($court->venue?->jam_operasional ?? '');
        preg_match('/(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})/', $hours, $matches);

        return [
            'is_open' => true,
            'start' => $matches[1] ?? '07:00',
            'end' => $matches[2] ?? '22:00',
            'duration' => 60,
            'price' => (float) ($court->harga_per_jam ?? 0),
        ];
    }

    /**
     * @return Collection<int, array{
     *     start: string,
     *     end: string,
     *     price: float,
     *     status: string,
     *     booking: ?CourtBooking,
     *     block: ?CourtSlotBlock
     * }>
     */
    public function slotsFor(Court $court, CarbonInterface $date): Collection
    {
        $settings = $this->settingsFor($court, (int) $date->dayOfWeekIso);
        if (! $settings['is_open'] || $settings['duration'] < 1) {
            return collect();
        }

        $bookings = CourtBooking::query()
            ->where('court_id', $court->court_id)
            ->whereDate('tanggal', $date->toDateString())
            ->whereIn('status', ['Booked', 'Pending Payment'])
            ->get();
        $blocks = CourtSlotBlock::query()
            ->where('court_id', $court->court_id)
            ->whereDate('tanggal', $date->toDateString())
            ->get();

        $openMinutes = $this->minutes($settings['start']);
        $closeMinutes = $this->minutes($settings['end']);
        $slots = collect();

        for ($minute = $openMinutes; $minute + $settings['duration'] <= $closeMinutes; $minute += $settings['duration']) {
            $start = $this->time($minute);
            $end = $this->time($minute + $settings['duration']);
            $booking = $bookings->first(fn (CourtBooking $item) => $this->overlaps($item->jam_mulai, $item->jam_selesai, $start, $end));
            $block = $blocks->first(fn (CourtSlotBlock $item) => $this->overlaps($item->jam_mulai, $item->jam_selesai, $start, $end));

            $isCourtAvailable = ($court->status_ketersediaan ?? 'Available') === 'Available';
            $slotStartsAt = Carbon::parse($date->toDateString().' '.$start);
            $status = $booking?->status
                ?? ($block || ! $isCourtAvailable
                    ? 'Unavailable'
                    : ($slotStartsAt->isFuture() ? 'Available' : 'Elapsed'));

            $slots->push([
                'start' => $start,
                'end' => $end,
                'price' => $settings['price'],
                'status' => $status,
                'booking' => $booking,
                'block' => $block,
            ]);
        }

        return $slots;
    }

    public function assertRangeAvailable(Court $court, string $date, string $start, string $end): float
    {
        if (($court->status_ketersediaan ?? 'Available') !== 'Available') {
            throw ValidationException::withMessages(['jam_mulai' => 'Court sedang tidak tersedia untuk disewa.']);
        }

        $startAt = Carbon::parse($date.' '.$start);
        $endAt = Carbon::parse($date.' '.$end);
        if (! $endAt->gt($startAt)) {
            throw ValidationException::withMessages(['jam_selesai' => 'Jam selesai harus setelah jam mulai.']);
        }

        $settings = $this->settingsFor($court, (int) $startAt->dayOfWeekIso);
        $openAt = Carbon::parse($date.' '.$settings['start']);
        $closeAt = Carbon::parse($date.' '.$settings['end']);
        $duration = $settings['duration'];
        $startOffset = (int) $openAt->diffInMinutes($startAt, false);
        $length = (int) $startAt->diffInMinutes($endAt, false);

        if (! $settings['is_open'] || $startAt->lt($openAt) || $endAt->gt($closeAt)
            || $startOffset < 0 || $startOffset % $duration !== 0 || $length % $duration !== 0) {
            throw ValidationException::withMessages([
                'jam_mulai' => 'Waktu booking harus mengikuti jam operasional dan kelipatan durasi slot court.',
            ]);
        }

        $hasBlock = CourtSlotBlock::query()
            ->where('court_id', $court->court_id)
            ->whereDate('tanggal', $date)
            ->where('jam_mulai', '<', $end)
            ->where('jam_selesai', '>', $start)
            ->exists();
        if ($hasBlock) {
            throw ValidationException::withMessages(['jam_mulai' => 'Slot tersebut sedang dinonaktifkan.']);
        }

        $hasBooking = CourtBooking::query()
            ->where('court_id', $court->court_id)
            ->whereDate('tanggal', $date)
            ->whereIn('status', ['Booked', 'Pending Payment'])
            ->where('jam_mulai', '<', $end)
            ->where('jam_selesai', '>', $start)
            ->exists();
        if ($hasBooking) {
            throw ValidationException::withMessages(['jam_mulai' => 'Slot sudah dipesan. Silakan pilih waktu lain.']);
        }

        return $settings['price'] * ($length / $duration);
    }

    private function overlaps(string $existingStart, string $existingEnd, string $start, string $end): bool
    {
        return substr($existingStart, 0, 5) < $end && substr($existingEnd, 0, 5) > $start;
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));

        return ($hours * 60) + $minutes;
    }

    private function time(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
