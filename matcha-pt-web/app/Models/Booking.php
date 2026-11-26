<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    protected $table = 'tb_booking';

    protected $primaryKey = 'booking_id';

    protected $fillable = [
        'booking_code',
        'user_id',
        'court_id',
        'booking_date',
        'start_time',
        'end_time',
        'duration_hours',
        'total_price',
        'customer_name',
        'customer_phone',
        'booking_status',
        'payment_method',
        'payment_status',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'duration_hours' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'court_id', 'court_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(BookingSlot::class, 'booking_id', 'booking_id');
    }
}
