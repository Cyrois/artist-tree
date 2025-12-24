<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Artist extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'spotify_id',
        'name',
        'genres',
        'image_url',
    ];

    protected $casts = [
        'genres' => 'array',
    ];

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
     * Scope: Search by name (case-insensitive, cross-DB compatible).
     */
    public function scopeSearch($query, string $term)
    {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return $query->where('name', 'ILIKE', "%{$term}%");
        }

        // SQLite and MySQL: LIKE is case-insensitive by default
        return $query->where('name', 'LIKE', "%{$term}%");
    }

    /**
     * Alias for metrics() to support factory relationship.
     */
    public function artistMetric(): HasOne
    {
        return $this->metrics();
    }

    /**
     * Scope: Filter by genre.
     */
    public function scopeHasGenre($query, string $genre)
    {
        return $query->whereJsonContains('genres', $genre);
    }
}
