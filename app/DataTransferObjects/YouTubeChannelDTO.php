<?php

namespace App\DataTransferObjects;

/**
 * Data Transfer Object for YouTube Channel data.
 *
 * Represents channel data from YouTube Data API v3 responses.
 */
readonly class YouTubeChannelDTO
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
        public bool $isVerified = false,
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
            isVerified: self::determineVerificationStatus($status, $snippet),
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
     * Determine if channel is verified based on API response.
     * Note: YouTube API doesn't directly expose verification status,
     * so we use heuristics based on available data.
     */
    private static function determineVerificationStatus(array $status, array $snippet): bool
    {
        // Check for official artist channel indicator
        if (isset($status['isLinked']) && $status['isLinked']) {
            return true;
        }
        
        // Check for custom URL (usually indicates verified/established channel)
        if (!empty($snippet['customUrl'])) {
            return true;
        }
        
        return false;
    }

    /**
     * Create a copy with updated subscriber count and video count.
     */
    public function withMetrics(int $subscriberCount, int $videoCount): self
    {
        return new self(
            channelId: $this->channelId,
            subscriberCount: $subscriberCount,
            videoCount: $videoCount,
            subscriberCountHidden: $this->subscriberCountHidden,
            uploadsPlaylistId: $this->uploadsPlaylistId,
            videoAnalytics: $this->videoAnalytics,
            title: $this->title,
            description: $this->description,
            isVerified: $this->isVerified,
            hasRecentActivity: $this->hasRecentActivity,
            lastUploadDate: $this->lastUploadDate,
        );
    }

    /**
     * Create a copy with recent activity information.
     */
    public function withRecentActivity(bool $hasRecentActivity, ?\DateTimeInterface $lastUploadDate = null): self
    {
        return new self(
            channelId: $this->channelId,
            subscriberCount: $this->subscriberCount,
            videoCount: $this->videoCount,
            subscriberCountHidden: $this->subscriberCountHidden,
            uploadsPlaylistId: $this->uploadsPlaylistId,
            videoAnalytics: $this->videoAnalytics,
            title: $this->title,
            description: $this->description,
            isVerified: $this->isVerified,
            hasRecentActivity: $hasRecentActivity,
            lastUploadDate: $lastUploadDate,
        );
    }

    /**
     * Create a copy with search snippet data (title and description).
     */
    public function withSearchSnippet(?string $title, ?string $description): self
    {
        return new self(
            channelId: $this->channelId,
            subscriberCount: $this->subscriberCount,
            videoCount: $this->videoCount,
            subscriberCountHidden: $this->subscriberCountHidden,
            uploadsPlaylistId: $this->uploadsPlaylistId,
            videoAnalytics: $this->videoAnalytics,
            title: $title ?? $this->title,
            description: $description ?? $this->description,
            isVerified: $this->isVerified,
            hasRecentActivity: $this->hasRecentActivity,
            lastUploadDate: $this->lastUploadDate,
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
            'is_verified' => $this->isVerified,
            'has_recent_activity' => $this->hasRecentActivity,
            'last_upload_date' => $this->lastUploadDate?->format('Y-m-d H:i:s'),
        ];
    }
}