<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArtistLinkVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'artist_link_id',
        'vote',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function link()
    {
        return $this->belongsTo(ArtistLink::class, 'artist_link_id');
    }
}
