<?php

namespace App\Http\Resources\VenueOwner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueOwnerVenueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->venue_id,
            'name' => $this->nama_venue,
            'address' => $this->alamat,
            'city' => $this->kota,
            'photos' => $this->gallery_photos,
            'sports' => $this->sport_type,
            'operating_hours' => $this->jam_operasional,
            'open_days' => $this->hari_buka,
            'contact_name' => $this->nama_pic,
            'contact_phone' => $this->no_whatsapp,
            'facilities' => $this->whenLoaded('facilities', fn ($facilities) => VenueOwnerFacilityResource::collection($facilities)),
            'courts' => $this->whenLoaded('courts', fn ($courts) => VenueOwnerCourtResource::collection($courts)),
        ];
    }
}
