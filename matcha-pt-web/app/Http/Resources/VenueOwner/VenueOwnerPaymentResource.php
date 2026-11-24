<?php

namespace App\Http\Resources\VenueOwner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueOwnerPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->payment_id,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'method' => $this->metode_pembayaran,
            'amount' => $this->jumlah,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
        ];
    }
}
