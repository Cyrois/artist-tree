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
        'youtube_refreshed_at',
        'youtube_avg_views',
        'youtube_avg_likes',
        'youtube_avg_comments',
        'youtube_videos_analyzed',
        'youtube_analytics_refreshed_at',
        'instagram_followers',
        'tiktok_followers',
        'refreshed_at',
    ];

    protected $casts = [
        'spotify_popularity' => 'integer',
        'spotify_followers' => 'integer',
        'youtube_subscribers' => 'integer',
        'youtube_refreshed_at' => 'datetime',
        'youtube_avg_views' => 'integer',
        'youtube_avg_likes' => 'integer',
        'youtube_avg_comments' => 'integer',
        'youtube_videos_analyzed' => 'integer',
        'youtube_analytics_refreshed_at' => 'datetime',
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

    /**
     * Check if YouTube basic metrics are stale (older than 24 hours).
     */
    public function isYouTubeStale(): bool
    {
        if (! $this->youtube_refreshed_at) {
            return true;
        }

        return $this->youtube_refreshed_at->lt(now()->subHours(24));
    }

    /**
     * Check if YouTube analytics are stale (older than 7 days).
     */
    public function isYouTubeAnalyticsStale(): bool
    {
        if (! $this->youtube_analytics_refreshed_at) {
            return true;
        }

        return $this->youtube_analytics_refreshed_at->lt(now()->subDays(7));
    }

    /**
     * Check if YouTube data needs refresh (either basic metrics or analytics).
     */
    public function needsYouTubeRefresh(): bool
    {
        return $this->isYouTubeStale() || $this->isYouTubeAnalyticsStale();
    }

    /**
     * Check if this artist has YouTube data available.
     */
    public function hasYouTubeData(): bool
    {
        return ! is_null($this->youtube_subscribers);
    }

    /**
     * Check if this artist has YouTube analytics data available.
     */
    public function hasYouTubeAnalytics(): bool
    {
        return ! is_null($this->youtube_avg_views) && 
               ! is_null($this->youtube_videos_analyzed);
    }
}
