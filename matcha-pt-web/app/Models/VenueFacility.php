<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VenueFacility extends Model
{
    protected $table = 'tb_venue_facility';

    protected $primaryKey = 'facility_id';

    protected $fillable = [
        'venue_id',
        'nama_fasilitas',
        'deskripsi',
        'foto',
        'sport_id',
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class, 'venue_id', 'venue_id');
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class, 'sport_id', 'sport_id');
    }

    public function courts()
    {
        return $this->belongsToMany(Court::class, 'tb_court_facility', 'facility_id', 'court_id');
    }
}
