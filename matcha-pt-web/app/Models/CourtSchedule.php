<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtSchedule extends Model
{
    protected $table = 'tb_court_schedule';

    protected $primaryKey = 'schedule_id';

    protected $fillable = [
        'court_id',
        'weekday',
        'is_open',
        'jam_buka',
        'jam_tutup',
        'durasi_menit',
        'harga_per_slot',
    ];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'harga_per_slot' => 'decimal:2',
        ];
    }

    public function court()
    {
        return $this->belongsTo(Court::class, 'court_id', 'court_id');
    }
}
