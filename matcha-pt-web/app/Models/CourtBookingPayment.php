<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtBookingPayment extends Model
{
    protected $table = 'tb_court_booking_payment';

    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'booking_id',
        'provider',
        'provider_reference',
        'metode_pembayaran',
        'jumlah',
        'status',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CourtBooking::class, 'booking_id', 'booking_id');
    }
}
