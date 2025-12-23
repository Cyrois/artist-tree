<?php

namespace App\DataTransferObjects;

/**
 * Data Transfer Object for Spotify Artist data.
 *
 * Represents artist data from Spotify API responses.
 */
readonly class SpotifyArtistDTO
{
    public function __construct(
        public string $spotifyId,
        public string $name,
        public array $genres,
        public ?string $imageUrl,
        public int $popularity,
        public int $followers,
    ) {}

    /**
     * Create DTO from Spotify API response array.
     */
    public static function fromSpotifyResponse(array $data): self
    {
        return new self(
            spotifyId: $data['id'],
            name: $data['name'],
            genres: $data['genres'] ?? [],
            imageUrl: $data['images'][0]['url'] ?? null,
            popularity: $data['popularity'] ?? 0,
            followers: $data['followers']['total'] ?? 0,
        );
    }

    /**
     * Convert to array for database insertion or API responses.
     */
    public function toArray(): array
    {
        return [
            'spotify_id' => $this->spotifyId,
            'name' => $this->name,
            'genres' => $this->genres,
            'image_url' => $this->imageUrl,
            'spotify_popularity' => $this->popularity,
            'spotify_followers' => $this->followers,
        ];
    }
}
