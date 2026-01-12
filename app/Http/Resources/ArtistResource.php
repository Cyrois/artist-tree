<?php

namespace App\Http\Resources;

use App\Models\Artist;
use App\Services\ArtistScoringService;
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
        $scoringService = app(ArtistScoringService::class);

        return [
            'id' => $this->id,
            'spotify_id' => $this->spotify_id,
            'name' => $this->name,
            'genres' => $this->genres->pluck('name')->toArray(),
            'country' => $this->country?->name,
            'image_url' => $this->image_url,
            'score' => $scoringService->calculateScore($this->resource),
            'links' => $this->links->map(fn ($link) => [
                'platform' => $link->platform->value,
                'url' => $link->url,
            ]),

            // Include metrics if loaded
            'metrics' => $this->when($this->relationLoaded('metrics') && $this->metrics, function () {
                return [
                    'spotify_popularity' => $this->metrics->spotify_popularity,
                    'spotify_followers' => $this->metrics->spotify_followers,
                    'youtube_subscribers' => $this->metrics->youtube_subscribers,
                    'youtube_avg_views' => $this->metrics->youtube_avg_views,
                    'youtube_avg_likes' => $this->metrics->youtube_avg_likes,
                    'youtube_avg_comments' => $this->metrics->youtube_avg_comments,
                    'youtube_videos_analyzed' => $this->metrics->youtube_videos_analyzed,
                    'instagram_followers' => $this->metrics->instagram_followers,
                    'tiktok_followers' => $this->metrics->tiktok_followers,
                    'refreshed_at' => $this->metrics->refreshed_at?->toISOString(),
                    'youtube_refreshed_at' => $this->metrics->youtube_refreshed_at?->toISOString(),
                    'youtube_analytics_refreshed_at' => $this->metrics->youtube_analytics_refreshed_at?->toISOString(),
                    'is_stale' => $this->metrics->isStale(),
                ];
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
