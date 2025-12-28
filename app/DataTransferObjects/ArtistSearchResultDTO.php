<?php

namespace App\DataTransferObjects;

use App\Models\Artist;

/**
 * Data Transfer Object for unified artist search results.
 *
 * Represents an artist from either local database or Spotify API,
 * with metadata about the source and whether it exists locally.
 */
readonly class ArtistSearchResultDTO
{
    public function __construct(
        public string $spotifyId,
        public string $name,
        public array $genres,
        public ?string $imageUrl,
        public ?int $popularity,
        public ?int $followers,
        public bool $existsInDatabase,
        public ?int $score = null,
        public ?int $databaseId = null,
        public string $source = 'spotify', // 'local' or 'spotify'
    ) {}

    /**
     * Create from local Artist model.
     */
    public static function fromLocalArtist(Artist $artist): self
    {
        $score = app(\App\Services\ArtistScoringService::class)->calculateScore($artist);

        return new self(
            spotifyId: $artist->spotify_id,
            name: $artist->name,
            genres: $artist->genres ?? [],
            imageUrl: $artist->image_url,
            popularity: $artist->metrics?->spotify_popularity,
            followers: $artist->metrics?->spotify_followers,
            existsInDatabase: true,
            score: $score,
            databaseId: $artist->id,
            source: 'local',
        );
    }

    /**
     * Create from Spotify API data.
     */
    public static function fromSpotifyArtist(SpotifyArtistDTO $spotify, ?Artist $localArtist = null): self
    {
        $score = null;

        if ($localArtist) {
            $score = app(\App\Services\ArtistScoringService::class)->calculateScore($localArtist);
        } else {
            $score = app(\App\Services\ArtistScoringService::class)->calculateScoreFromMetrics([
                'spotify_popularity' => $spotify->popularity,
                'spotify_followers' => $spotify->followers,
            ]);
        }

        return new self(
            spotifyId: $spotify->spotifyId,
            name: $spotify->name,
            genres: $spotify->genres,
            imageUrl: $spotify->imageUrl,
            popularity: $spotify->popularity,
            followers: $spotify->followers,
            existsInDatabase: $localArtist !== null,
            score: $score,
            databaseId: $localArtist?->id,
            source: 'spotify',
        );
    }

    /**
     * Convert to array for API responses.
     */
    public function toArray(): array
    {
        return [
            'spotify_id' => $this->spotifyId,
            'name' => $this->name,
            'genres' => $this->genres,
            'image_url' => $this->imageUrl,
            'popularity' => $this->popularity,
            'followers' => $this->followers,
            'exists_in_database' => $this->existsInDatabase,
            'score' => $this->score,
            'database_id' => $this->databaseId,
            'source' => $this->source,
        ];
    }
}
