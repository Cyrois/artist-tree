<?php

namespace App\Http\Resources;

use App\Enums\ArtistTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LineupResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'updated_at_human' => $this->updated_at->diffForHumans(),
            'artist_count' => $this->whenCounted('artists'),
            'avg_score' => $this->avg_score,
            // Include first few artists for preview if loaded
            'preview_artists' => $this->whenLoaded('artists', function() {
                return $this->artists->take(4)->map(function($artist) {
                    return [
                        'id' => $artist->id,
                        'name' => $artist->name,
                        'image_url' => $artist->image_url,
                        'tier' => $artist->pivot->tier ?? ArtistTier::Undercard->value,
                    ];
                });
            }),
        ];
    }
}
