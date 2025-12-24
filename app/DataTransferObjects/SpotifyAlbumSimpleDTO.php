<?php

namespace App\DataTransferObjects;

class SpotifyAlbumSimpleDTO
{
    public function __construct(
        public readonly string $spotifyId,
        public readonly string $name,
        public readonly string $albumType,
        public readonly string $releaseDate,
        public readonly int $totalTracks,
        public readonly string $imageUrl,
        public readonly string $externalUrl,
    ) {}

    public static function fromSpotifyResponse(array $album): self
    {
        return new self(
            spotifyId: $album['id'],
            name: $album['name'],
            albumType: $album['album_type'] ?? 'album',
            releaseDate: $album['release_date'] ?? '',
            totalTracks: $album['total_tracks'] ?? 0,
            imageUrl: $album['images'][0]['url'] ?? '',
            externalUrl: $album['external_urls']['spotify'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'spotify_id' => $this->spotifyId,
            'name' => $this->name,
            'album_type' => $this->albumType,
            'release_date' => $this->releaseDate,
            'total_tracks' => $this->totalTracks,
            'image_url' => $this->imageUrl,
            'external_url' => $this->externalUrl,
        ];
    }
}
