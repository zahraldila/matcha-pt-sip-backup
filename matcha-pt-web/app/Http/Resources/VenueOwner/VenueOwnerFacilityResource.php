<?php

namespace App\Http\Resources\VenueOwner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueOwnerFacilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->facility_id,
            'venue_id' => $this->venue_id,
            'name' => $this->nama_fasilitas,
            'description' => $this->deskripsi,
            'photo' => $this->foto,
            'sport' => $this->whenLoaded('sport', fn ($sport) => [
                'id' => $sport?->sport_id,
                'name' => $sport?->nama_sport,
            ]),
        ];
    }
}
