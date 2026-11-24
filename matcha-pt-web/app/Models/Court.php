<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    use HasFactory;

    protected $table = 'tb_court';

    protected $primaryKey = 'court_id';

    protected $fillable = [
        'venue_id',
        'sport_id',
        'nama_court',
        'status_ketersediaan',
        'image_url',
        'deskripsi',
        'tipe_court',
        'harga_per_jam',
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class, 'venue_id', 'venue_id');
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class, 'sport_id', 'sport_id');
    }

    public function sessions()
    {
        return $this->belongsToMany(SessionModel::class, 'tb_session_court', 'court_id', 'session_id');
    }

    public function matches()
    {
        return $this->hasMany(GameMatch::class, 'court_id', 'court_id');
    }

    public function facilities()
    {
        return $this->belongsToMany(VenueFacility::class, 'tb_court_facility', 'court_id', 'facility_id');
    }

    public function schedules()
    {
        return $this->hasMany(CourtSchedule::class, 'court_id', 'court_id');
    }

    public function bookings()
    {
        return $this->hasMany(CourtBooking::class, 'court_id', 'court_id');
    }

    public function slotBlocks()
    {
        return $this->hasMany(CourtSlotBlock::class, 'court_id', 'court_id');
    }
    }
}
