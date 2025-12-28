<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lineup extends Model
{
    protected $fillable = ['user_id', 'name', 'description'];

    public function artists()
    {
        return $this->belongsToMany(Artist::class, 'lineup_artists')
            ->withPivot('tier', 'suggested_tier', 'tier_override')
            ->withTimestamps();
    }
}
