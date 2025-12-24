<?php

namespace App\Http\Resources;

use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Artist
 */
class ArtistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'spotify_id' => $this->spotify_id,
            'name' => $this->name,
            'genres' => $this->genres,
            'image_url' => $this->image_url,

            // Include metrics if loaded
            'metrics' => $this->when($this->relationLoaded('metrics'), function () {
                return [
                    'spotify_popularity' => $this->metrics->spotify_popularity,
                    'spotify_followers' => $this->metrics->spotify_followers,
                    'youtube_subscribers' => $this->metrics->youtube_subscribers,
                    'refreshed_at' => $this->metrics->refreshed_at?->toISOString(),
                    'is_stale' => $this->metrics->isStale(),
                ];
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
