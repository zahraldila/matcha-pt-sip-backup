<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtSlotBlock extends Model
{
    protected $table = 'tb_court_slot_block';

    protected $primaryKey = 'block_id';

    protected $fillable = [
        'court_id',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'alasan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function court()
    {
        return $this->belongsTo(Court::class, 'court_id', 'court_id');
    }
}
