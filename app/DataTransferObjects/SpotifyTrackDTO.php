<?php

namespace App\DataTransferObjects;

class SpotifyTrackDTO
{
    public function __construct(
        public readonly string $spotifyId,
        public readonly string $name,
        public readonly string $albumName,
        public readonly string $albumImageUrl,
        public readonly int $durationMs,
        public readonly string $previewUrl,
        public readonly string $externalUrl,
        public readonly array $artists,
    ) {}

    public static function fromSpotifyResponse(array $track): self
    {
        return new self(
            spotifyId: $track['id'],
            name: $track['name'],
            albumName: $track['album']['name'] ?? 'Unknown Album',
            albumImageUrl: $track['album']['images'][0]['url'] ?? '',
            durationMs: $track['duration_ms'] ?? 0,
            previewUrl: $track['preview_url'] ?? '',
            externalUrl: $track['external_urls']['spotify'] ?? '',
            artists: array_map(fn ($a) => [
                'name' => $a['name'],
                'spotify_id' => $a['id'],
            ], $track['artists'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'spotify_id' => $this->spotifyId,
            'name' => $this->name,
            'album_name' => $this->albumName,
            'album_image_url' => $this->albumImageUrl,
            'duration_ms' => $this->durationMs,
            'preview_url' => $this->previewUrl,
            'external_url' => $this->externalUrl,
            'artists' => $this->artists,
        ];
    }
}
