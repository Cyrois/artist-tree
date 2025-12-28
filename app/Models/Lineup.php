<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lineup extends Model
{
    protected $fillable = ['name', 'description'];

    public function artists()
    {
        return $this->belongsToMany(Artist::class, 'lineup_artists')
            ->withPivot('tier', 'suggested_tier')
            ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'lineup_user')
            ->withPivot('role')
            ->withTimestamps();
    }
}
