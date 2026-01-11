<?php

namespace App\DataTransferObjects;

/**
 * Data Transfer Object for YouTube Video Analytics data.
 *
 * Represents aggregated video analytics from YouTube Data API v3 responses.
 */
readonly class YouTubeVideoAnalyticsDTO
{
    public function __construct(
        public int $videosAnalyzed,
        public float $averageViews,
        public float $averageLikes,
        public float $averageComments,
        public array $videoIds,
    ) {}

    /**
     * Create DTO from YouTube API response array.
     */
    public static function fromYouTubeResponse(array $data): self
    {
        return new self(
            videosAnalyzed: $data['videos_analyzed'] ?? 0,
            averageViews: (float) ($data['average_views'] ?? 0.0),
            averageLikes: (float) ($data['average_likes'] ?? 0.0),
            averageComments: (float) ($data['average_comments'] ?? 0.0),
            videoIds: $data['video_ids'] ?? [],
        );
    }

    /**
     * Calculate analytics from array of video data.
     */
    public static function calculateFromVideos(array $videos): self
    {
        if (empty($videos)) {
            return new self(
                videosAnalyzed: 0,
                averageViews: 0.0,
                averageLikes: 0.0,
                averageComments: 0.0,
                videoIds: [],
            );
        }

        $totalViews = 0;
        $totalLikes = 0;
        $totalComments = 0;
        $videoIds = [];

        foreach ($videos as $videoId => $videoData) {
            // Handle both full API response format and simplified format from getVideoAnalytics
            if (isset($videoData['statistics'])) {
                // Full API response format
                $statistics = $videoData['statistics'];
                if (isset($statistics['viewCount'])) {
                    $totalViews += (int) $statistics['viewCount'];
                    $totalLikes += (int) ($statistics['likeCount'] ?? 0);
                    $totalComments += (int) ($statistics['commentCount'] ?? 0);
                    $videoIds[] = $videoData['id'];
                }
            } elseif (isset($videoData['viewCount'])) {
                // Simplified format from getVideoAnalytics
                $totalViews += (int) $videoData['viewCount'];
                $totalLikes += (int) ($videoData['likeCount'] ?? 0);
                $totalComments += (int) ($videoData['commentCount'] ?? 0);
                $videoIds[] = $videoId;
            }
        }

        $videosAnalyzed = count($videoIds);
        
        if ($videosAnalyzed === 0) {
            return new self(
                videosAnalyzed: 0,
                averageViews: 0.0,
                averageLikes: 0.0,
                averageComments: 0.0,
                videoIds: [],
            );
        }

        return new self(
            videosAnalyzed: $videosAnalyzed,
            averageViews: $totalViews / $videosAnalyzed,
            averageLikes: $totalLikes / $videosAnalyzed,
            averageComments: $totalComments / $videosAnalyzed,
            videoIds: $videoIds,
        );
    }

    /**
     * Convert to array for database insertion or API responses.
     */
    public function toArray(): array
    {
        return [
            'videos_analyzed' => $this->videosAnalyzed,
            'average_views' => $this->averageViews,
            'average_likes' => $this->averageLikes,
            'average_comments' => $this->averageComments,
            'video_ids' => $this->videoIds,
        ];
    }
}