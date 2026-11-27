<?php

namespace App\Http\Resources\VenueOwner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueOwnerCourtResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->court_id,
            'venue_id' => $this->venue_id,
            'name' => $this->nama_court,
            'description' => $this->deskripsi,
            'photo' => $this->image_url,
            'type' => $this->tipe_court,
            'availability_status' => $this->status_ketersediaan,
            'hourly_price' => $this->harga_per_jam,
            'sport' => $this->whenLoaded('sport', fn ($sport) => [
                'id' => $sport?->sport_id,
                'name' => $sport?->nama_sport,
            ]),
            'facilities' => $this->whenLoaded('facilities', fn ($facilities) => VenueOwnerFacilityResource::collection($facilities)),
            'schedules' => $this->whenLoaded('schedules', fn ($schedules) => $schedules->map(fn ($schedule) => [
                'weekday' => $schedule->weekday,
                'is_open' => $schedule->is_open,
                'opens_at' => substr((string) $schedule->jam_buka, 0, 5),
                'closes_at' => substr((string) $schedule->jam_tutup, 0, 5),
                'slot_duration_minutes' => $schedule->durasi_menit,
                'price_per_slot' => $schedule->harga_per_slot,
            ])),
        ];
    }
}
