<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'artist_id',
        'spotify_popularity',
        'spotify_followers',
        'youtube_subscribers',
        'instagram_followers',
        'tiktok_followers',
        'refreshed_at',
    ];

    protected $casts = [
        'spotify_popularity' => 'integer',
        'spotify_followers' => 'integer',
        'youtube_subscribers' => 'integer',
        'instagram_followers' => 'integer',
        'tiktok_followers' => 'integer',
        'refreshed_at' => 'datetime',
    ];

    /**
     * Get the artist that owns these metrics.
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Check if metrics data is stale (older than 24 hours).
     */
    public function isStale(): bool
    {
        if (! $this->refreshed_at) {
            return true;
        }

        return $this->refreshed_at->lt(now()->subHours(24));
    }
}
