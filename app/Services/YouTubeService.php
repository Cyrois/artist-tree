<?php

namespace App\Services;

use App\DataTransferObjects\YouTubeChannelDTO;
use App\DataTransferObjects\YouTubeVideoAnalyticsDTO;
use App\Exceptions\YouTubeApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * YouTube Data API v3 integration service.
 *
 * Handles channel metrics fetching, video analytics, quota tracking, and rate limiting.
 */
class YouTubeService
{
    private const QUOTA_CACHE_KEY = 'youtube_quota_usage';

    private const QUOTA_EXHAUSTED_KEY = 'youtube_quota_exhausted';

    private string $baseUrl;

    private string $apiKey;

    private int $quotaLimit;

    private int $cacheTtl;

    private int $videoAnalyticsCacheTtl;

    public function __construct()
    {
        $baseUrl = config('services.youtube.base_url');
        $apiKey = config('services.youtube.api_key');

        if (empty($baseUrl)) {
            throw new \RuntimeException('YouTube API base URL not configured');
        }

        if (empty($apiKey)) {
            throw new \RuntimeException('YouTube API key not configured');
        }

        $this->baseUrl = $baseUrl;
        $this->apiKey = $apiKey;
        $this->quotaLimit = config('services.youtube.quota_limit', 10000);
        $this->cacheTtl = config('services.youtube.cache_ttl', 86400); // 24 hours
        $this->videoAnalyticsCacheTtl = config('services.youtube.video_analytics_cache_ttl', 604800); // 7 days
    }

    /**
     * Get channel metrics for a single channel.
     *
     * @param  string  $channelId  YouTube channel ID
     * @return YouTubeChannelDTO|null Channel data or null if not found
     *
     * @throws YouTubeApiException
     */
    public function getChannelMetrics(string $channelId): ?YouTubeChannelDTO
    {
        $results = $this->getMultipleChannelMetrics([$channelId]);

        return $results[$channelId] ?? null;
    }

    /**
     * Get channel metrics for multiple channels in batch.
     *
     * @param  array<string>  $channelIds  Array of YouTube channel IDs (max 50)
     * @return array<string, YouTubeChannelDTO> Keyed by channel ID
     *
     * @throws YouTubeApiException
     */
    public function getMultipleChannelMetrics(array $channelIds): array
    {
        if (empty($channelIds)) {
            return [];
        }

        if (count($channelIds) > 50) {
            throw new \InvalidArgumentException('YouTube API limit is 50 channel IDs per request');
        }

        // Check cache first
        $results = [];
        $uncachedIds = [];

        foreach ($channelIds as $channelId) {
            $cacheKey = "youtube_channel:{$channelId}";
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $results[$channelId] = $cached;
            } else {
                $uncachedIds[] = $channelId;
            }
        }

        // If all channels are cached, return cached results
        if (empty($uncachedIds)) {
            return $results;
        }

        // Apply graceful degradation only when quota is exhausted, not just low
        if (Cache::has(self::QUOTA_EXHAUSTED_KEY)) {
            Log::info('YouTube API graceful degradation: quota exhausted, returning cached data only', [
                'requested_channels' => count($channelIds),
                'cached_channels' => count($results),
                'uncached_channels' => count($uncachedIds),
                'quota_status' => $this->getQuotaStatus(),
            ]);

            // Return only cached results when quota is exhausted
            return array_filter($results);
        }

        try {
            // Fetch uncached channels from API with error handling
            $data = $this->makeApiRequest('/channels', [
                'part' => 'statistics,contentDetails',
                'id' => implode(',', $uncachedIds),
            ], 1);

            $channels = $data['items'] ?? [];

            // Process API results
            foreach ($channels as $channelData) {
                $channelId = $channelData['id'];

                Log::debug('YouTube getMultipleChannelMetrics: raw channel data', [
                    'channel_id' => $channelId,
                    'raw_data' => $channelData,
                ]);

                $dto = YouTubeChannelDTO::fromYouTubeResponse($channelData);

                // Cache the result
                $cacheKey = "youtube_channel:{$channelId}";
                Cache::put($cacheKey, $dto, $this->cacheTtl);

                $results[$channelId] = $dto;
            }

            // Log channels that were not found
            $foundIds = array_column($channels, 'id');
            $notFoundIds = array_diff($uncachedIds, $foundIds);

            foreach ($notFoundIds as $channelId) {
                Log::info('YouTube channel not found or private', ['channel_id' => $channelId]);

                // Cache null result to prevent repeated API calls
                $cacheKey = "youtube_channel:{$channelId}";
                Cache::put($cacheKey, null, 3600); // Cache for 1 hour

                $results[$channelId] = null;
            }

        } catch (YouTubeApiException $e) {
            // Handle graceful degradation for API errors
            if ($e->isQuotaExhausted) {
                Log::warning('YouTube quota exhausted, returning cached data only', [
                    'requested_channels' => count($channelIds),
                    'cached_channels' => count($results),
                    'uncached_channels' => count($uncachedIds),
                ]);
            } else {
                Log::error('YouTube API error during channel metrics fetch', [
                    'error' => $e->getMessage(),
                    'status_code' => $e->statusCode,
                    'requested_channels' => count($uncachedIds),
                ]);
            }

            // Return only cached results when API fails
            return array_filter($results);
        }

        return array_filter($results); // Remove null values
    }

    /**
     * Get recent videos from a channel's uploads playlist.
     *
     * @param  string  $channelId  YouTube channel ID
     * @param  int  $limit  Maximum number of videos to fetch (default 15)
     * @return array<string> Array of video IDs
     *
     * @throws YouTubeApiException
     */
    public function getChannelVideos(string $channelId, int $limit = 15): array
    {
        $limit = min(max($limit, 1), 50); // Clamp between 1-50
        $cacheKey = "youtube_videos:{$channelId}:{$limit}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($channelId, $limit) {
            try {
                // First, get the uploads playlist ID
                $channelData = $this->getChannelMetrics($channelId);

                Log::debug('YouTube getChannelVideos: channel data', [
                    'channel_id' => $channelId,
                    'channel_data' => $channelData ? [
                        'subscriberCount' => $channelData->subscriberCount,
                        'uploadsPlaylistId' => $channelData->uploadsPlaylistId,
                    ] : null,
                ]);

                if (! $channelData || ! $channelData->uploadsPlaylistId) {
                    Log::debug('YouTube getChannelVideos: no uploads playlist ID', [
                        'channel_id' => $channelId,
                        'has_channel_data' => $channelData !== null,
                        'uploads_playlist_id' => $channelData?->uploadsPlaylistId,
                    ]);

                    return [];
                }

                // Fetch playlist items with error handling
                $data = $this->makeApiRequest('/playlistItems', [
                    'part' => 'contentDetails',
                    'playlistId' => $channelData->uploadsPlaylistId,
                    'maxResults' => $limit,
                ], 1);

                $items = $data['items'] ?? [];

                Log::debug('YouTube getChannelVideos: playlist items', [
                    'channel_id' => $channelId,
                    'playlist_id' => $channelData->uploadsPlaylistId,
                    'items_count' => count($items),
                    'limit' => $limit,
                ]);

                return array_map(
                    fn ($item) => $item['contentDetails']['videoId'],
                    $items
                );

            } catch (YouTubeApiException $e) {
                // Handle graceful degradation for API errors
                Log::warning('YouTube API error during channel videos fetch', [
                    'channel_id' => $channelId,
                    'error' => $e->getMessage(),
                    'status_code' => $e->statusCode,
                ]);

                // Return empty array when API fails
                return [];
            }
        });
    }

    /**
     * Get video analytics for multiple videos.
     *
     * @param  array<string>  $videoIds  Array of video IDs (max 50)
     * @return array<string, array> Video statistics keyed by video ID
     *
     * @throws YouTubeApiException
     */
    public function getVideoAnalytics(array $videoIds): array
    {
        if (empty($videoIds)) {
            return [];
        }

        if (count($videoIds) > 50) {
            throw new \InvalidArgumentException('YouTube API limit is 50 video IDs per request');
        }

        try {
            // Fetch video analytics with error handling
            $data = $this->makeApiRequest('/videos', [
                'part' => 'statistics',
                'id' => implode(',', $videoIds),
            ], 1);

            $videos = $data['items'] ?? [];
            $results = [];

            foreach ($videos as $video) {
                $videoId = $video['id'];
                $statistics = $video['statistics'] ?? [];

                // Only include videos with available statistics
                if (! empty($statistics['viewCount'])) {
                    $results[$videoId] = [
                        'viewCount' => (int) ($statistics['viewCount'] ?? 0),
                        'likeCount' => (int) ($statistics['likeCount'] ?? 0),
                        'commentCount' => (int) ($statistics['commentCount'] ?? 0),
                    ];
                }
            }

            return $results;

        } catch (YouTubeApiException $e) {
            // Handle graceful degradation for API errors
            Log::warning('YouTube API error during video analytics fetch', [
                'video_count' => count($videoIds),
                'error' => $e->getMessage(),
                'status_code' => $e->statusCode,
            ]);

            // Return empty array when API fails
            return [];
        }
    }

    /**
     * Calculate video analytics for a channel.
     *
     * @param  string  $channelId  YouTube channel ID
     * @return YouTubeVideoAnalyticsDTO|null Analytics data or null if unavailable
     *
     * @throws YouTubeApiException
     */
    public function calculateVideoAnalytics(string $channelId): ?YouTubeVideoAnalyticsDTO
    {
        $cacheKey = "youtube_analytics:{$channelId}";

        return Cache::remember($cacheKey, $this->videoAnalyticsCacheTtl, function () use ($channelId) {
            try {
                // Get recent videos
                $videoIds = $this->getChannelVideos($channelId, 15);

                if (empty($videoIds)) {
                    return null;
                }

                // Get video analytics
                $videoAnalytics = $this->getVideoAnalytics($videoIds);

                if (empty($videoAnalytics)) {
                    return null;
                }

                return YouTubeVideoAnalyticsDTO::calculateFromVideos($videoAnalytics);

            } catch (YouTubeApiException $e) {
                // Handle graceful degradation for API errors
                Log::warning('YouTube API error during video analytics calculation', [
                    'channel_id' => $channelId,
                    'error' => $e->getMessage(),
                    'status_code' => $e->statusCode,
                ]);

                // Return null when API fails
                return null;
            }
        });
    }

    /**
     * Check if quota is available for API requests.
     *
     * @param  int  $requiredQuota  Optional quota amount to check (default 1)
     * @return bool True if quota is available, false if exhausted
     */
    public function checkQuotaAvailability(int $requiredQuota = 1): bool
    {
        // Check if quota is marked as exhausted
        if (Cache::has(self::QUOTA_EXHAUSTED_KEY)) {
            return false;
        }

        $currentUsage = $this->getCurrentQuotaUsage();
        $wouldExceedLimit = ($currentUsage + $requiredQuota) > $this->quotaLimit;

        if ($wouldExceedLimit) {
            Log::warning('YouTube API quota would be exceeded by request', [
                'current_usage' => $currentUsage,
                'required_quota' => $requiredQuota,
                'limit' => $this->quotaLimit,
                'would_exceed_by' => ($currentUsage + $requiredQuota) - $this->quotaLimit,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Check if quota is running low (above 90% usage).
     *
     * @return bool True if quota is low
     */
    public function isQuotaLow(): bool
    {
        $currentUsage = $this->getCurrentQuotaUsage();
        $lowThreshold = (int) ($this->quotaLimit * 0.9);

        return $currentUsage >= $lowThreshold;
    }

    /**
     * Determine if graceful degradation should be applied.
     * This happens when quota is low or exhausted.
     *
     * @return bool True if graceful degradation should be used
     */
    public function shouldUseGracefulDegradation(): bool
    {
        return Cache::has(self::QUOTA_EXHAUSTED_KEY) || $this->isQuotaLow();
    }

    /**
     * Get remaining quota for today.
     *
     * @return int Remaining quota units
     */
    public function getRemainingQuota(): int
    {
        $currentUsage = $this->getCurrentQuotaUsage();

        return max(0, $this->quotaLimit - $currentUsage);
    }

    /**
     * Get quota usage percentage for today.
     *
     * @return float Quota usage as percentage (0-100)
     */
    public function getQuotaUsagePercentage(): float
    {
        $currentUsage = $this->getCurrentQuotaUsage();

        return round(($currentUsage / $this->quotaLimit) * 100, 2);
    }

    /**
     * Get detailed quota status information.
     *
     * @return array Quota status details
     */
    public function getQuotaStatus(): array
    {
        $currentUsage = $this->getCurrentQuotaUsage();
        $remaining = $this->getRemainingQuota();
        $percentage = $this->getQuotaUsagePercentage();
        $isExhausted = Cache::has(self::QUOTA_EXHAUSTED_KEY);

        return [
            'used' => $currentUsage,
            'remaining' => $remaining,
            'limit' => $this->quotaLimit,
            'percentage_used' => $percentage,
            'is_exhausted' => $isExhausted,
            'date' => now()->utc()->format('Y-m-d'),
            'resets_at' => now()->utc()->endOfDay()->toISOString(),
        ];
    }

    /**
     * Reset quota tracking (primarily for testing).
     * In production, quota resets automatically at midnight UTC.
     */
    public function resetQuotaTracking(): void
    {
        $today = now()->utc()->format('Y-m-d');
        $key = self::QUOTA_CACHE_KEY.":{$today}";

        Cache::forget($key);
        Cache::forget(self::QUOTA_EXHAUSTED_KEY);

        Log::info('YouTube API quota tracking reset', [
            'date' => $today,
            'reset_at' => now()->utc()->toISOString(),
        ]);
    }

    /**
     * Track quota usage for an API request.
     *
     * @param  int  $cost  Quota cost of the request
     */
    private function trackQuotaUsage(int $cost): void
    {
        $today = now()->utc()->format('Y-m-d');
        $key = self::QUOTA_CACHE_KEY.":{$today}";

        $currentUsage = Cache::get($key, 0);
        $newUsage = $currentUsage + $cost;

        // Cache until end of day (midnight UTC)
        $endOfDay = now()->utc()->endOfDay();
        Cache::put($key, $newUsage, $endOfDay);

        Log::info('YouTube API quota usage tracked', [
            'cost' => $cost,
            'total_usage' => $newUsage,
            'remaining' => $this->quotaLimit - $newUsage,
            'date' => $today,
            'percentage_used' => round(($newUsage / $this->quotaLimit) * 100, 1),
        ]);

        // Mark quota as exhausted if we've hit the limit
        if ($newUsage >= $this->quotaLimit) {
            Cache::put(self::QUOTA_EXHAUSTED_KEY, true, $endOfDay);
            Log::warning('YouTube API quota exhausted', [
                'usage' => $newUsage,
                'limit' => $this->quotaLimit,
                'date' => $today,
            ]);
        }

        // Enhanced quota monitoring with multiple warning thresholds
        $this->checkQuotaWarnings($currentUsage, $newUsage);
    }

    /**
     * Check and log quota warnings at various thresholds.
     *
     * @param  int  $previousUsage  Previous quota usage
     * @param  int  $currentUsage  Current quota usage after request
     */
    private function checkQuotaWarnings(int $previousUsage, int $currentUsage): void
    {
        $warningThresholds = [
            50 => 'YouTube API quota at 50% usage',
            75 => 'YouTube API quota at 75% usage',
            90 => 'YouTube API quota approaching limit (90%)',
            95 => 'YouTube API quota critically low (95%)',
        ];

        foreach ($warningThresholds as $threshold => $message) {
            $thresholdUsage = (int) ($this->quotaLimit * ($threshold / 100));

            // Log warning if we've crossed this threshold with this request
            if ($currentUsage >= $thresholdUsage && $previousUsage < $thresholdUsage) {
                Log::warning($message, [
                    'usage' => $currentUsage,
                    'limit' => $this->quotaLimit,
                    'percentage' => round(($currentUsage / $this->quotaLimit) * 100, 1),
                    'remaining' => $this->quotaLimit - $currentUsage,
                    'threshold' => $threshold,
                ]);
            }
        }
    }

    /**
     * Get current quota usage for today.
     *
     * @return int Current quota usage
     */
    private function getCurrentQuotaUsage(): int
    {
        $today = now()->utc()->format('Y-m-d');
        $key = self::QUOTA_CACHE_KEY.":{$today}";

        return Cache::get($key, 0);
    }

    /**
     * Create HTTP client for YouTube API requests with retry logic.
     */
    private function makeRequest(): PendingRequest
    {
        return Http::timeout(10)
            ->retry(3, function ($attempt, $exception) {
                // Exponential backoff: 1s, 2s, 4s
                $delay = 1000 * (2 ** ($attempt - 1));

                Log::info('YouTube API request retry', [
                    'attempt' => $attempt,
                    'delay_ms' => $delay,
                    'exception' => $exception?->getMessage(),
                ]);

                return $delay;
            }, function ($exception, $request) {
                // Retry on network errors and 5xx responses, but not on 4xx
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    $response = $exception->response;

                    // Don't retry on quota exhaustion (403) or not found (404)
                    if ($response && in_array($response->status(), [403, 404])) {
                        return false;
                    }

                    return $response && $response->status() >= 500;
                }

                return true; // Retry on connection failures
            });
    }

    /**
     * Make a YouTube API request with comprehensive error handling.
     *
     * @param  string  $endpoint  API endpoint path
     * @param  array  $params  Query parameters
     * @param  int  $quotaCost  Quota cost of this request
     * @return array API response data
     *
     * @throws YouTubeApiException
     */
    private function makeApiRequest(string $endpoint, array $params, int $quotaCost = 1): array
    {
        // Check quota availability before making request
        if (! $this->checkQuotaAvailability($quotaCost)) {
            throw YouTubeApiException::quotaExhausted($quotaCost);
        }

        try {
            // Track quota usage before request
            $this->trackQuotaUsage($quotaCost);

            $response = $this->makeRequest()
                ->get($this->baseUrl.$endpoint, array_merge($params, ['key' => $this->apiKey]));

            if (! $response->successful()) {
                $this->handleApiError($response, $endpoint, $quotaCost);
            }

            return $response->json();

        } catch (YouTubeApiException $e) {
            // Re-throw YouTube API exceptions
            throw $e;
        } catch (\Exception $e) {
            // Handle unexpected errors with graceful degradation
            Log::error('Unexpected YouTube API error', [
                'endpoint' => $endpoint,
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new YouTubeApiException(
                "YouTube API request failed: {$e->getMessage()}",
                null,
                500,
                $quotaCost,
                false
            );
        }
    }

    /**
     * Handle API error responses with appropriate exception types.
     *
     * @param  \Illuminate\Http\Client\Response  $response
     *
     * @throws YouTubeApiException
     */
    private function handleApiError($response, string $endpoint, int $quotaCost): void
    {
        $statusCode = $response->status();
        $body = $response->json();

        Log::error('YouTube API error response', [
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'response_body' => $body,
            'quota_cost' => $quotaCost,
        ]);

        // Handle quota exhaustion
        if ($statusCode === 403) {
            $errorReason = $body['error']['errors'][0]['reason'] ?? '';

            if (in_array($errorReason, ['quotaExceeded', 'dailyLimitExceeded'])) {
                // Mark quota as exhausted
                $endOfDay = now()->endOfDay();
                Cache::put(self::QUOTA_EXHAUSTED_KEY, true, $endOfDay);

                throw YouTubeApiException::quotaExhausted($quotaCost);
            }
        }

        // Handle rate limiting
        if ($statusCode === 429) {
            $retryAfter = (int) $response->header('Retry-After', 60);
            throw YouTubeApiException::rateLimited($retryAfter);
        }

        // Handle other errors
        throw YouTubeApiException::fromResponse($response, "YouTube API {$endpoint} request failed");
    }
}
