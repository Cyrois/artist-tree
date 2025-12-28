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
            'spotify_id' => $this->spotifyId,
            'name' => $this->name,
            'genres' => $this->genres,
            'image_url' => $this->imageUrl,
            'spotify_popularity' => $this->popularity,
            'exists_in_database' => $this->existsInDatabase,
            'source' => $this->source,
        ];
    }
}
