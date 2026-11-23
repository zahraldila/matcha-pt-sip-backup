<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSlot extends Model
{
    use HasFactory;

    protected $table = 'tb_booking_slot';

    protected $primaryKey = 'booking_slot_id';

    protected $fillable = [
        'booking_id',
        'court_id',
        'slot_date',
        'slot_start',
    ];

    protected function casts(): array
    {
        return [
            'slot_date' => 'date',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'court_id', 'court_id');
    }
}
