<?php

namespace App\Http\Resources;

use App\DataTransferObjects\ArtistSearchResultDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ArtistSearchResultDTO
 */
class ArtistSearchResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->databaseId,
            'name' => $this->name,
            'genres' => $this->genres,
            'image_url' => $this->imageUrl,
            'exists_in_database' => $this->existsInDatabase,
            'source' => $this->source,

            // Human-readable follower count
            'followers_formatted' => $this->formatFollowers($this->followers),
        ];
    }

    /**
     * Format follower count for display (e.g., "1.2M", "45.3K").
     */
    private function formatFollowers(?int $count): ?string
    {
        if ($count === null) {
            return null;
        }

        if ($count >= 1_000_000) {
            return round($count / 1_000_000, 1).'M';
        }

        if ($count >= 1_000) {
            return round($count / 1_000, 1).'K';
        }

        return (string) $count;
    }
}
