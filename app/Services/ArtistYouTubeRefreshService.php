<?php

namespace App\Services;

use App\Exceptions\YouTubeApiException;
use App\Models\Artist;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling YouTube data refresh operations for artists.
 * 
 * Encapsulates the business logic for refreshing YouTube metrics and analytics,
 * including conditional refresh based on data staleness.
 */
class ArtistYouTubeRefreshService
{
    public function __construct(
        private YouTubeService $youtubeService,
    ) {}

    /**
     * Force refresh YouTube data for an artist (both basic metrics and analytics).
     *
     * @param Artist $artist The artist to refresh YouTube data for
     * @return bool True if refresh was successful, false otherwise
     */
    public function forceRefresh(Artist $artist): bool
    {
        // Skip if no YouTube channel ID
        if (!$artist->youtube_channel_id) {
            return false;
        }

        try {
            // Force refresh basic channel metrics
            $channelData = $this->youtubeService->getChannelMetrics($artist->youtube_channel_id);
            
            if ($channelData) {
                $updateData = [
                    'youtube_subscribers' => $channelData->subscriberCount,
                    'youtube_refreshed_at' => now(),
                ];

                // Also refresh video analytics
                $analyticsData = $this->youtubeService->calculateVideoAnalytics($artist->youtube_channel_id);
                
                if ($analyticsData) {
                    $updateData = array_merge($updateData, [
                        'youtube_avg_views' => (int) $analyticsData->averageViews,
                        'youtube_avg_likes' => (int) $analyticsData->averageLikes,
                        'youtube_avg_comments' => (int) $analyticsData->averageComments,
                        'youtube_videos_analyzed' => $analyticsData->videosAnalyzed,
                        'youtube_analytics_refreshed_at' => now(),
                    ]);
                }

                // Ensure metrics exist before updating
                if (!$artist->metrics) {
                    $artist->metrics()->create($updateData);
                } else {
                    $artist->metrics->update($updateData);
                }

                Log::info('YouTube data force refreshed successfully', [
                    'artist_id' => $artist->id,
                    'youtube_channel_id' => $artist->youtube_channel_id,
                    'subscribers' => $channelData->subscriberCount,
                    'has_analytics' => $analyticsData !== null,
                ]);

                return true;
            }

            return false;
        } catch (YouTubeApiException|\Exception $e) {
            $this->handleYouTubeError($e, 'Failed to force refresh YouTube data', [
                'artist_id' => $artist->id,
                'youtube_channel_id' => $artist->youtube_channel_id,
            ]);

            return false;
        }
    }

    /**
     * Refresh YouTube data for an artist if needed (based on staleness).
     *
     * @param Artist $artist The artist to refresh YouTube data for
     * @return bool True if refresh was attempted (regardless of success), false if skipped
     */
    public function refreshIfNeeded(Artist $artist): bool
    {
        // Skip if no YouTube channel ID
        if (!$artist->youtube_channel_id) {
            return false;
        }

        // If no metrics exist, we need to create them and fetch data
        if (!$artist->metrics) {
            return $this->forceRefresh($artist);
        }

        // Skip if existing metrics don't need refresh
        if (!$artist->metrics->needsYouTubeRefresh()) {
            return false;
        }

        try {
            $refreshed = false;

            // Refresh basic channel metrics if stale
            if ($artist->metrics->isYouTubeStale()) {
                $channelData = $this->youtubeService->getChannelMetrics($artist->youtube_channel_id);
                
                if ($channelData) {
                    $artist->metrics->update([
                        'youtube_subscribers' => $channelData->subscriberCount,
                        'youtube_refreshed_at' => now(),
                    ]);
                    $refreshed = true;

                    Log::debug('YouTube basic metrics refreshed', [
                        'artist_id' => $artist->id,
                        'subscribers' => $channelData->subscriberCount,
                    ]);
                }
            }

            // Refresh video analytics if stale
            if ($artist->metrics->isYouTubeAnalyticsStale()) {
                $analyticsData = $this->youtubeService->calculateVideoAnalytics($artist->youtube_channel_id);
                
                if ($analyticsData) {
                    $artist->metrics->update([
                        'youtube_avg_views' => (int) $analyticsData->averageViews,
                        'youtube_avg_likes' => (int) $analyticsData->averageLikes,
                        'youtube_avg_comments' => (int) $analyticsData->averageComments,
                        'youtube_videos_analyzed' => $analyticsData->videosAnalyzed,
                        'youtube_analytics_refreshed_at' => now(),
                    ]);
                    $refreshed = true;

                    Log::debug('YouTube analytics refreshed', [
                        'artist_id' => $artist->id,
                        'videos_analyzed' => $analyticsData->videosAnalyzed,
                        'avg_views' => $analyticsData->averageViews,
                    ]);
                }
            }

            return $refreshed;
        } catch (YouTubeApiException|\Exception $e) {
            $this->handleYouTubeError($e, 'Failed to refresh YouTube data conditionally', [
                'artist_id' => $artist->id,
                'youtube_channel_id' => $artist->youtube_channel_id,
            ]);

            return true; // Return true because we attempted refresh
        }
    }

    /**
     * Refresh only basic YouTube metrics (subscriber count, video count).
     *
     * @param Artist $artist The artist to refresh basic metrics for
     * @return bool True if refresh was successful, false otherwise
     */
    public function refreshBasicMetrics(Artist $artist): bool
    {
        // Skip if no YouTube channel ID
        if (!$artist->youtube_channel_id) {
            return false;
        }

        try {
            $channelData = $this->youtubeService->getChannelMetrics($artist->youtube_channel_id);
            
            if ($channelData) {
                $updateData = [
                    'youtube_subscribers' => $channelData->subscriberCount,
                    'youtube_refreshed_at' => now(),
                ];

                // Ensure metrics exist before updating
                if (!$artist->metrics) {
                    $artist->metrics()->create($updateData);
                } else {
                    $artist->metrics->update($updateData);
                }

                Log::debug('YouTube basic metrics refreshed', [
                    'artist_id' => $artist->id,
                    'subscribers' => $channelData->subscriberCount,
                ]);

                return true;
            }

            return false;
        } catch (YouTubeApiException|\Exception $e) {
            $this->handleYouTubeError($e, 'Failed to refresh YouTube basic metrics', [
                'artist_id' => $artist->id,
                'youtube_channel_id' => $artist->youtube_channel_id,
            ]);

            return false;
        }
    }

    /**
     * Refresh only YouTube video analytics.
     *
     * @param Artist $artist The artist to refresh analytics for
     * @return bool True if refresh was successful, false otherwise
     */
    public function refreshAnalytics(Artist $artist): bool
    {
        // Skip if no YouTube channel ID
        if (!$artist->youtube_channel_id) {
            return false;
        }

        try {
            $analyticsData = $this->youtubeService->calculateVideoAnalytics($artist->youtube_channel_id);
            
            if ($analyticsData) {
                $updateData = [
                    'youtube_avg_views' => (int) $analyticsData->averageViews,
                    'youtube_avg_likes' => (int) $analyticsData->averageLikes,
                    'youtube_avg_comments' => (int) $analyticsData->averageComments,
                    'youtube_videos_analyzed' => $analyticsData->videosAnalyzed,
                    'youtube_analytics_refreshed_at' => now(),
                ];

                // Ensure metrics exist before updating
                if (!$artist->metrics) {
                    // Create with basic structure if doesn't exist
                    $artist->metrics()->create(array_merge($updateData, [
                        'refreshed_at' => now(),
                    ]));
                } else {
                    $artist->metrics->update($updateData);
                }

                Log::debug('YouTube analytics refreshed', [
                    'artist_id' => $artist->id,
                    'videos_analyzed' => $analyticsData->videosAnalyzed,
                    'avg_views' => $analyticsData->averageViews,
                ]);

                return true;
            }

            return false;
        } catch (YouTubeApiException|\Exception $e) {
            $this->handleYouTubeError($e, 'Failed to refresh YouTube analytics', [
                'artist_id' => $artist->id,
                'youtube_channel_id' => $artist->youtube_channel_id,
            ]);

            return false;
        }
    }

    /**
     * Check if an artist needs YouTube data refresh.
     *
     * @param Artist $artist The artist to check
     * @return bool True if refresh is needed
     */
    public function needsRefresh(Artist $artist): bool
    {
        // Skip if no YouTube channel ID
        if (!$artist->youtube_channel_id) {
            return false;
        }

        // Need refresh if no metrics exist
        if (!$artist->metrics) {
            return true;
        }

        return $artist->metrics->needsYouTubeRefresh();
    }

    /**
     * Handle YouTube API errors with standardized logging.
     *
     * @param YouTubeApiException|\Exception $e The exception to handle
     * @param string $context Description of what operation failed
     * @param array<string, mixed> $logData Additional data to include in logs
     * @return void
     */
    private function handleYouTubeError(YouTubeApiException|\Exception $e, string $context, array $logData = []): void
    {
        $isYouTubeError = $e instanceof YouTubeApiException;

        Log::error($context, array_merge($logData, [
            'error' => $e->getMessage(),
            'quota_exhausted' => $isYouTubeError ? $e->isQuotaExhausted : false,
            'status_code' => $isYouTubeError ? $e->statusCode : null,
        ]));
    }
}