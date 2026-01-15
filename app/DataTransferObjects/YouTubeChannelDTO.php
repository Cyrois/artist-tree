<?php

namespace App\DataTransferObjects;

/**
 * Data Transfer Object for YouTube Channel data.
 *
 * Represents channel data from YouTube Data API v3 responses.
 */
class YouTubeChannelDTO
{
    public function __construct(
        public string $channelId,
        public int $subscriberCount,
        public int $videoCount,
        public bool $subscriberCountHidden,
        public ?string $uploadsPlaylistId = null,
        public ?YouTubeVideoAnalyticsDTO $videoAnalytics = null,
        public ?string $title = null,
        public ?string $description = null,
        public bool $hasRecentActivity = false,
        public ?\DateTimeInterface $lastUploadDate = null,
    ) {}

    /**
     * Create DTO from YouTube API response array.
     */
    public static function fromYouTubeResponse(array $data): self
    {
        $statistics = $data['statistics'] ?? [];
        $contentDetails = $data['contentDetails'] ?? [];
        $relatedPlaylists = $contentDetails['relatedPlaylists'] ?? [];
        $snippet = $data['snippet'] ?? [];
        $status = $data['status'] ?? [];
        
        return new self(
            channelId: $data['id'],
            subscriberCount: (int) ($statistics['subscriberCount'] ?? 0),
            videoCount: (int) ($statistics['videoCount'] ?? 0),
            subscriberCountHidden: (bool) ($statistics['hiddenSubscriberCount'] ?? false),
            uploadsPlaylistId: $relatedPlaylists['uploads'] ?? null,
            title: $snippet['title'] ?? null,
            description: $snippet['description'] ?? null,
        );
    }

    /**
     * Create DTO from YouTube Search API response array.
     */
    public static function fromSearchResponse(array $data): self
    {
        $snippet = $data['snippet'] ?? [];
        $channelId = $data['id']['channelId'] ?? $data['id'] ?? '';
        
        return new self(
            channelId: $channelId,
            subscriberCount: 0, // Not available in search results
            videoCount: 0, // Not available in search results
            subscriberCountHidden: false,
            title: $snippet['title'] ?? null,
            description: $snippet['description'] ?? null,
        );
    }




    /**
     * Check if this channel has enough subscribers for automatic replacement.
     */
    public function meetsMinimumSubscriberThreshold(int $threshold = 1000): bool
    {
        return $this->subscriberCount >= $threshold;
    }

    /**
     * Check if this channel has active video content.
     * A channel is considered active if it has uploaded within the specified months.
     */
    public function hasActiveContent(int $monthsThreshold = 12): bool
    {
        if ($this->videoCount === 0) {
            return false;
        }

        if ($this->lastUploadDate === null) {
            // If we don't have upload date info, assume active if has videos
            return $this->videoCount > 0;
        }

        $thresholdDate = now()->subMonths($monthsThreshold);
        return $this->lastUploadDate >= $thresholdDate;
    }

    /**
     * Convert to array for database insertion or API responses.
     */
    public function toArray(): array
    {
        return [
            'channel_id' => $this->channelId,
            'subscriber_count' => $this->subscriberCount,
            'video_count' => $this->videoCount,
            'subscriber_count_hidden' => $this->subscriberCountHidden,
            'uploads_playlist_id' => $this->uploadsPlaylistId,
            'video_analytics' => $this->videoAnalytics?->toArray(),
            'title' => $this->title,
            'description' => $this->description,
            'has_recent_activity' => $this->hasRecentActivity,
            'last_upload_date' => $this->lastUploadDate?->format('Y-m-d H:i:s'),
        ];
    }
}