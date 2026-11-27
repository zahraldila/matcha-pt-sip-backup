<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtBookingCancellation extends Model
{
    protected $table = 'tb_court_booking_cancellation';

    protected $primaryKey = 'cancellation_id';

    protected $fillable = [
        'booking_id',
        'cancelled_by',
        'previous_status',
        'previous_payment_status',
        'reason',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CourtBooking::class, 'booking_id', 'booking_id');
    }
}
