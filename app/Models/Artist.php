<?php

namespace App\Models;

use App\Enums\ArtistDeleteReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Artist extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'musicbrainz_id',
        'spotify_id',
        'name',
        'image_url',
        'youtube_channel_id',
        'country_id',
        'deleted_reason',
    ];

    protected $casts = [
        'deleted_reason' => ArtistDeleteReason::class,
    ];

    /**
     * Get the country associated with the artist.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the genres for this artist.
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'artist_genre');
    }

    /**
     * Get the social/platform links for this artist.
     */
    public function links(): HasMany
    {
        return $this->hasMany(ArtistLink::class);
    }

    /**
     * Get the aliases for this artist.
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(ArtistAlias::class);
    }

    /**
     * Get the metrics for this artist.
     */
    public function metrics(): HasOne
    {
        return $this->hasOne(ArtistMetric::class);
    }

    /**
     * Check if metrics are stale (older than 24 hours).
     */
    public function hasStaleMetrics(): bool
    {
        if (! $this->metrics || ! $this->metrics->refreshed_at) {
            return true;
        }

        return $this->metrics->refreshed_at->lt(now()->subHours(24));
    }

    /**
     * Check if the artist should receive a YouTube data refresh.
     *
     * Returns true if:
     * 1. The artist has a YouTube channel ID
     * 2. AND (Metrics record is missing OR YouTube metrics are stale)
     */
    public function shouldRefreshYouTube(): bool
    {
        if (! $this->youtube_channel_id) {
            return false;
        }

        if (! $this->metrics) {
            return true;
        }

        return $this->metrics->needsYouTubeRefresh();
    }

    /**
     * Scope: Search by name or alias (case-insensitive).
     */
    public function scopeSearch($query, string $term)
    {
        $driver = $query->getConnection()->getDriverName();
        $like = ($driver === 'pgsql') ? 'ILIKE' : 'LIKE';

        return $query->where(function ($q) use ($term, $like) {
            $q->where('artists.name', $like, "%{$term}%")
              ->orWhereHas('aliases', function ($sub) use ($term, $like) {
                  $sub->where('name', $like, "%{$term}%");
              });
        });
    }

    /**
     * Alias for metrics() to support factory relationship.
     */
    public function artistMetric(): HasOne
    {
        return $this->metrics();
    }

    /**
     * Get the YouTube link for this artist.
     */
    public function youtubeLink(): ?ArtistLink
    {
        return $this->links()
            ->where('platform', \App\Enums\SocialPlatform::YOUTUBE)
            ->first();
    }

    /**
     * Check if this artist needs VEVO detection check.
     */
    public function needsVevoCheck(): bool
    {
        if (!$this->youtube_channel_id) {
            return false;
        }

        $youtubeLink = $this->youtubeLink();
        
        if (!$youtubeLink) {
            return true;
        }

        return $youtubeLink->needsVevoCheck();
    }
}