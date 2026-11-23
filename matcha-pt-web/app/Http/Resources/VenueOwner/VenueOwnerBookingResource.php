<?php

namespace App\Http\Resources\VenueOwner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueOwnerBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->booking_id,
            'court_id' => $this->court_id,
            'date' => $this->tanggal?->toDateString(),
            'starts_at' => substr((string) $this->jam_mulai, 0, 5),
            'ends_at' => substr((string) $this->jam_selesai, 0, 5),
            'renter_name' => $this->nama_penyewa,
            'renter_contact' => $this->kontak_penyewa,
            'amount' => $this->jumlah_bayar,
            'status' => $this->status,
            'payment_status' => $this->status_pembayaran,
            'source' => $this->sumber,
            'notes' => $this->catatan,
            'cancellation_reason' => $this->alasan_pembatalan,
            'cancelled_at' => $this->cancelled_at,
            'payments' => $this->whenLoaded('payments', fn ($payments) => VenueOwnerPaymentResource::collection($payments)),
            'cancellations' => $this->whenLoaded('cancellations', fn ($cancellations) => $cancellations->map(fn ($cancellation) => [
                'id' => $cancellation->cancellation_id,
                'reason' => $cancellation->reason,
                'previous_status' => $cancellation->previous_status,
                'previous_payment_status' => $cancellation->previous_payment_status,
                'cancelled_at' => $cancellation->cancelled_at,
            ])),
            'court' => $this->whenLoaded('court', fn ($court) => new VenueOwnerCourtResource($court)),
        ];
    }
}
