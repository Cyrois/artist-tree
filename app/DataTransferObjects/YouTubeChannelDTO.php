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
        public ?YouTubeVideoAnalyticsDTO $videoAnalytics = null,
    ) {}

    /**
     * Create DTO from YouTube API response array.
     */
    public static function fromYouTubeResponse(array $data): self
    {
        $statistics = $data['statistics'] ?? [];
        
        return new self(
            channelId: $data['id'],
            subscriberCount: (int) ($statistics['subscriberCount'] ?? 0),
            videoCount: (int) ($statistics['videoCount'] ?? 0),
            subscriberCountHidden: (bool) ($statistics['hiddenSubscriberCount'] ?? false),
        );
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
            'video_analytics' => $this->videoAnalytics?->toArray(),
        ];
    }
}