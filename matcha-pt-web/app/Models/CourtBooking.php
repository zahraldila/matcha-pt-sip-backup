<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtBooking extends Model
{
    protected $table = 'tb_court_booking';

    protected $primaryKey = 'booking_id';

    protected $fillable = [
        'court_id',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'nama_penyewa',
        'kontak_penyewa',
        'jumlah_bayar',
        'status',
        'status_pembayaran',
        'sumber',
        'catatan',
        'alasan_pembatalan',
        'cancelled_by',
        'cancelled_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah_bayar' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function court()
    {
        return $this->belongsTo(Court::class, 'court_id', 'court_id');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by', 'user_id');
    }
}
