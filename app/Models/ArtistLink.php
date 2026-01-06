<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArtistLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'artist_id',
        'platform',
        'url',
        'vote_score',
    ];

    protected $casts = [
        'platform' => \App\Enums\SocialPlatform::class,
    ];

    public function artist()
    {
        return $this->belongsTo(Artist::class);
    }

    public function votes()
    {
        return $this->hasMany(ArtistLinkVote::class);
    }
}