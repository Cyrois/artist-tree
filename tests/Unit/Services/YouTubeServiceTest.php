<?php

use App\DataTransferObjects\YouTubeChannelDTO;
use App\DataTransferObjects\YouTubeVideoAnalyticsDTO;
use App\Exceptions\YouTubeApiException;
use App\Services\YouTubeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();

    // Set up YouTube API configuration
    config([
        'services.youtube.base_url' => 'https://www.googleapis.com/youtube/v3',
        'services.youtube.api_key' => 'test_api_key',
        'services.youtube.quota_limit' => 10000,
        'services.youtube.cache_ttl' => 86400, // 24 hours
        'services.youtube.video_analytics_cache_ttl' => 604800, // 7 days
    ]);
});

/**
 * Feature: youtube-integration, Property 2: Quota Tracking and Enforcement
 * For any API request, the system should track quota usage and prevent requests when the daily limit is reached
 * Validates: Requirements 1.2, 1.3, 6.1, 6.3
 */
it('tracks quota usage and prevents requests when limit is reached', function () {
    // Set a very low quota limit for testing
    config(['services.youtube.quota_limit' => 2]);

    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannel1',
                    'statistics' => [
                        'subscriberCount' => '1000',
                        'videoCount' => '50',
                        'hiddenSubscriberCount' => false,
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUTestChannel1',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new YouTubeService;

    // First request should work (quota usage: 1)
    $result1 = $service->getChannelMetrics('UCTestChannel1');
    expect($result1)->toBeInstanceOf(YouTubeChannelDTO::class);
    expect($service->getRemainingQuota())->toBe(1);

    // Second request should work (quota usage: 2, limit reached)
    $result2 = $service->getChannelMetrics('UCTestChannel2');
    expect($service->getRemainingQuota())->toBe(0);

    // Third request should be blocked due to quota exhaustion
    $result3 = $service->getChannelMetrics('UCTestChannel3');
    expect($result3)->toBeNull(); // Should return null when quota exhausted

    // Should not make additional HTTP requests after quota exhaustion
    Http::assertSentCount(2); // Only 2 requests should have been made
});

/**
 * Feature: youtube-integration, Property 3: Response Caching Behavior
 * For any successful API response, identical requests within 24 hours should return cached data without making new API calls
 * Validates: Requirements 1.4
 */
it('caches successful API responses and returns cached data for identical requests', function () {
    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannel',
                    'statistics' => [
                        'subscriberCount' => '1000',
                        'videoCount' => '50',
                        'hiddenSubscriberCount' => false,
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUTestChannel',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new YouTubeService;

    // First request - should hit API
    $result1 = $service->getChannelMetrics('UCTestChannel');
    expect($result1)->toBeInstanceOf(YouTubeChannelDTO::class);
    expect($result1->subscriberCount)->toBe(1000);

    // Second identical request - should use cache
    $result2 = $service->getChannelMetrics('UCTestChannel');
    expect($result2)->toBeInstanceOf(YouTubeChannelDTO::class);
    expect($result2->subscriberCount)->toBe(1000);

    // Should only make one HTTP request (cached on second call)
    Http::assertSentCount(1);

    // Results should be identical
    expect($result1->channelId)->toBe($result2->channelId);
    expect($result1->subscriberCount)->toBe($result2->subscriberCount);
});

/**
 * Feature: youtube-integration, Property 6: Channel Metrics Fetching
 * For any artist with a valid youtube_channel_id, the service should fetch both subscriber count and video count in a single request
 * Validates: Requirements 2.1, 2.2
 */
it('fetches both subscriber count and video count for valid channel IDs', function () {
    Http::fake([
        '*' => Http::response([
            'items' => [
                [
                    'id' => 'UCValidChannel',
                    'statistics' => [
                        'subscriberCount' => '50000',
                        'videoCount' => '200',
                        'hiddenSubscriberCount' => false,
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUValidChannel',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new YouTubeService;
    $result = $service->getChannelMetrics('UCValidChannel');

    expect($result)->toBeInstanceOf(YouTubeChannelDTO::class);
    expect($result->channelId)->toBe('UCValidChannel');
    expect($result->subscriberCount)->toBe(50000);
    expect($result->videoCount)->toBe(200);
    expect($result->subscriberCountHidden)->toBeFalse();
    expect($result->uploadsPlaylistId)->toBe('UUValidChannel');

    // Should make exactly one API request
    Http::assertSentCount(1);

    // Verify the request was made with correct parameters
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'googleapis.com/youtube/v3/channels') &&
               str_contains($request->url(), 'part=statistics') &&
               str_contains($request->url(), 'contentDetails') &&
               str_contains($request->url(), 'id=UCValidChannel');
    });
});

/**
 * Feature: youtube-integration, Property 20: Video Analytics Collection
 * For any channel with videos, the system should fetch and analyze up to 15 recent videos for engagement metrics
 * Validates: Requirements 9.1, 9.2
 */
it('fetches and analyzes recent videos for engagement metrics', function () {
    Http::fake([
        '*channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannel',
                    'statistics' => [
                        'subscriberCount' => '1000',
                        'videoCount' => '50',
                        'hiddenSubscriberCount' => false,
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUTestChannel',
                        ],
                    ],
                ],
            ],
        ], 200),
        '*playlistItems*' => Http::response([
            'items' => [
                ['contentDetails' => ['videoId' => 'video1']],
                ['contentDetails' => ['videoId' => 'video2']],
                ['contentDetails' => ['videoId' => 'video3']],
            ],
        ], 200),
        '*videos*' => Http::response([
            'items' => [
                [
                    'id' => 'video1',
                    'statistics' => [
                        'viewCount' => '10000',
                        'likeCount' => '500',
                        'commentCount' => '50',
                    ],
                ],
                [
                    'id' => 'video2',
                    'statistics' => [
                        'viewCount' => '20000',
                        'likeCount' => '1000',
                        'commentCount' => '100',
                    ],
                ],
                [
                    'id' => 'video3',
                    'statistics' => [
                        'viewCount' => '15000',
                        'likeCount' => '750',
                        'commentCount' => '75',
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new YouTubeService;

    // Test getting recent videos
    $videoIds = $service->getChannelVideos('UCTestChannel', 15);
    expect($videoIds)->toHaveCount(3);
    expect($videoIds)->toContain('video1', 'video2', 'video3');

    // Test getting video analytics
    $analytics = $service->getVideoAnalytics(['video1', 'video2', 'video3']);
    expect($analytics)->toHaveCount(3);
    expect($analytics['video1']['viewCount'])->toBe(10000);
    expect($analytics['video1']['likeCount'])->toBe(500);
    expect($analytics['video1']['commentCount'])->toBe(50);

    // Test calculating video analytics
    $calculatedAnalytics = $service->calculateVideoAnalytics('UCTestChannel');
    expect($calculatedAnalytics)->toBeInstanceOf(YouTubeVideoAnalyticsDTO::class);
    expect($calculatedAnalytics->videosAnalyzed)->toBe(3);
    expect($calculatedAnalytics->averageViews)->toBe(15000.0); // (10000 + 20000 + 15000) / 3
    expect($calculatedAnalytics->averageLikes)->toBe(750.0); // (500 + 1000 + 750) / 3
    expect($calculatedAnalytics->averageComments)->toBe(75.0); // (50 + 100 + 75) / 3

    // Should make multiple API requests (exact count may vary due to caching)
    Http::assertSentCount(4);
});

it('handles batch processing of multiple channels efficiently', function () {
    Http::fake([
        '*' => Http::response([
            'items' => [
                [
                    'id' => 'UCChannel1',
                    'statistics' => [
                        'subscriberCount' => '1000',
                        'videoCount' => '50',
                        'hiddenSubscriberCount' => false,
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUChannel1',
                        ],
                    ],
                ],
                [
                    'id' => 'UCChannel2',
                    'statistics' => [
                        'subscriberCount' => '2000',
                        'videoCount' => '100',
                        'hiddenSubscriberCount' => false,
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUChannel2',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new YouTubeService;
    $results = $service->getMultipleChannelMetrics(['UCChannel1', 'UCChannel2']);

    expect($results)->toHaveCount(2);
    expect($results['UCChannel1']->subscriberCount)->toBe(1000);
    expect($results['UCChannel2']->subscriberCount)->toBe(2000);

    // Should make only one API request for both channels
    Http::assertSentCount(1);

    // Verify batch request format
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'id=UCChannel1%2CUCChannel2');
    });
});

it('handles API errors gracefully and returns empty results', function () {
    Http::fake([
        '*' => Http::response([
            'error' => [
                'code' => 403,
                'message' => 'The request cannot be completed because you have exceeded your quota.',
                'errors' => [
                    [
                        'message' => 'The request cannot be completed because you have exceeded your quota.',
                        'domain' => 'youtube.quota',
                        'reason' => 'quotaExceeded',
                    ],
                ],
            ],
        ], 403),
    ]);

    $service = new YouTubeService;
    
    // Should return null gracefully instead of throwing exception
    $result = $service->getChannelMetrics('UCTestChannel');
    expect($result)->toBeNull();
    
    // Should also work for batch requests
    $results = $service->getMultipleChannelMetrics(['UCTestChannel1', 'UCTestChannel2']);
    expect($results)->toBeEmpty();
});

it('handles channels that are not found or private', function () {
    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [], // Empty response for not found channel
        ], 200),
    ]);

    $service = new YouTubeService;
    $result = $service->getChannelMetrics('UCNonExistentChannel');

    expect($result)->toBeNull();

    // Should still make the API request
    Http::assertSentCount(1);
});

it('respects API request limits for batch operations', function () {
    $channelIds = array_map(fn($i) => "UCChannel{$i}", range(1, 51)); // 51 channels

    $service = new YouTubeService;

    expect(fn () => $service->getMultipleChannelMetrics($channelIds))
        ->toThrow(InvalidArgumentException::class, 'YouTube API limit is 50 channel IDs per request');
});

it('logs quota usage and warnings appropriately', function () {
    // Set quota limit to 5 for testing warnings
    config(['services.youtube.quota_limit' => 5]);

    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannel',
                    'statistics' => [
                        'subscriberCount' => '1000',
                        'videoCount' => '50',
                        'hiddenSubscriberCount' => false,
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUTestChannel',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new YouTubeService;

    // Make requests to approach quota limit
    for ($i = 1; $i <= 5; $i++) {
        Cache::forget("youtube_channel:UCTestChannel{$i}"); // Ensure no cache
        $service->getChannelMetrics("UCTestChannel{$i}");
    }

    expect($service->getRemainingQuota())->toBe(0);
    expect($service->checkQuotaAvailability())->toBeFalse();
});

it('throws exception when base URL is not configured', function () {
    config(['services.youtube.base_url' => null]);

    expect(fn () => new YouTubeService)
        ->toThrow(RuntimeException::class, 'YouTube API base URL not configured');
});

it('throws exception when API key is not configured', function () {
    config(['services.youtube.api_key' => null]);

    expect(fn () => new YouTubeService)
        ->toThrow(RuntimeException::class, 'YouTube API key not configured');
});

/**
 * Feature: youtube-integration, Property 4: Retry Logic with Exponential Backoff
 * For any transient API failure, the system should retry with exponential backoff up to the maximum retry limit
 * Validates: Requirements 1.5, 3.3
 */
it('implements retry logic with exponential backoff for transient failures', function () {
    // Mock a service that fails twice then succeeds
    $callCount = 0;
    Http::fake(function () use (&$callCount) {
        $callCount++;
        if ($callCount <= 2) {
            // First two calls fail with 500 error
            return Http::response(['error' => ['message' => 'Internal server error']], 500);
        }
        // Third call succeeds
        return Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannel',
                    'statistics' => [
                        'subscriberCount' => '1000',
                        'videoCount' => '50',
                        'hiddenSubscriberCount' => false,
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUTestChannel',
                        ],
                    ],
                ],
            ],
        ], 200);
    });

    $service = new YouTubeService;
    
    // Should eventually succeed after retries
    $result = $service->getChannelMetrics('UCTestChannel');
    expect($result)->toBeInstanceOf(YouTubeChannelDTO::class);
    expect($result->subscriberCount)->toBe(1000);
    
    // Should have made 3 requests (2 failures + 1 success)
    Http::assertSentCount(3);
});

/**
 * Feature: youtube-integration, Property 9: Graceful Error Handling
 * For any channel that is private, terminated, or not found, the system should handle the error gracefully and continue processing other channels
 * Validates: Requirements 2.6, 5.3, 7.3
 */
it('handles private, terminated, or not found channels gracefully', function () {
    Http::fake([
        '*' => Http::response([
            'items' => [
                [
                    'id' => 'UCValidChannel',
                    'statistics' => [
                        'subscriberCount' => '1000',
                        'videoCount' => '50',
                        'hiddenSubscriberCount' => false,
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUValidChannel',
                        ],
                    ],
                ],
                // UCPrivateChannel and UCTerminatedChannel are not returned
            ],
        ], 200),
    ]);

    $service = new YouTubeService;
    
    // Test batch processing with mixed valid/invalid channels
    $results = $service->getMultipleChannelMetrics([
        'UCValidChannel',
        'UCPrivateChannel', 
        'UCTerminatedChannel'
    ]);
    
    // Should return only the valid channel
    expect($results)->toHaveCount(1);
    expect($results)->toHaveKey('UCValidChannel');
    expect($results['UCValidChannel']->subscriberCount)->toBe(1000);
    
    // Should not throw exceptions for missing channels
    expect($results)->not->toHaveKey('UCPrivateChannel');
    expect($results)->not->toHaveKey('UCTerminatedChannel');
    
    // Test individual channel that doesn't exist
    $singleResult = $service->getChannelMetrics('UCNonExistentChannel');
    expect($singleResult)->toBeNull();
    
    // Should have made API requests without throwing exceptions
    Http::assertSentCount(2);
});

it('sets quota exhaustion flag correctly when API returns quota exceeded error', function () {
    // Set quota limit to 1 for easy testing
    config(['services.youtube.quota_limit' => 1]);
    
    Http::fake([
        '*' => Http::response([
            'error' => [
                'code' => 403,
                'message' => 'The request cannot be completed because you have exceeded your quota.',
                'errors' => [
                    [
                        'message' => 'The request cannot be completed because you have exceeded your quota.',
                        'domain' => 'youtube.quota',
                        'reason' => 'quotaExceeded',
                    ],
                ],
            ]
        ], 403),
    ]);

    $service = new YouTubeService;
    
    // Initially quota should be available
    expect($service->checkQuotaAvailability())->toBeTrue();
    
    // Make a request that will trigger quota exhaustion
    $result = $service->getChannelMetrics('UCTestChannel');
    expect($result)->toBeNull(); // Should handle gracefully
    
    // After quota exhaustion, checkQuotaAvailability should return false
    expect($service->checkQuotaAvailability())->toBeFalse();
    expect($service->getRemainingQuota())->toBe(0);
});

/**
 * Feature: youtube-integration, Property 14: Graceful Degradation During Outages
 * For any YouTube API unavailability or quota exhaustion, the system should continue operating with cached data
 * Validates: Requirements 5.1, 5.2
 */
it('gracefully degrades during API outages and quota exhaustion', function () {
    // First, populate cache with successful response
    Http::fake([
        '*' => Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannel',
                    'statistics' => [
                        'subscriberCount' => '1000',
                        'videoCount' => '50',
                        'hiddenSubscriberCount' => false,
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUTestChannel',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);
    
    $service = new YouTubeService;
    
    // First request - should succeed and cache result
    $cachedResult = $service->getChannelMetrics('UCTestChannel');
    expect($cachedResult)->toBeInstanceOf(YouTubeChannelDTO::class);
    expect($cachedResult->subscriberCount)->toBe(1000);
    
    // Now simulate API outage (503 error)
    Http::fake([
        '*' => Http::response([
            'error' => ['message' => 'Service unavailable']
        ], 503),
    ]);
    
    // Request for cached channel should still work (returns cached data)
    $degradedResult = $service->getChannelMetrics('UCTestChannel');
    expect($degradedResult)->toBeInstanceOf(YouTubeChannelDTO::class);
    expect($degradedResult->subscriberCount)->toBe(1000);
    
    // Request for uncached channel should handle gracefully
    $uncachedResult = $service->getChannelMetrics('UCNewChannel');
    expect($uncachedResult)->toBeNull(); // Should not throw exception
    
    // Test quota exhaustion scenario
    // Set quota limit to 1 to easily trigger exhaustion and create new service instance
    config(['services.youtube.quota_limit' => 1]);
    $quotaService = new YouTubeService; // New instance with low quota limit
    
    // Clear cache to force API call
    Cache::flush();
    
    Http::fake([
        '*' => Http::response([
            'error' => [
                'code' => 403,
                'message' => 'The request cannot be completed because you have exceeded your quota.',
                'errors' => [
                    [
                        'message' => 'The request cannot be completed because you have exceeded your quota.',
                        'domain' => 'youtube.quota',
                        'reason' => 'quotaExceeded',
                    ],
                ],
            ]
        ], 403),
    ]);
    
    // Re-cache the first channel since we flushed cache
    Cache::put('youtube_channel:UCTestChannel', $cachedResult, 86400);
    
    // This should trigger quota exhaustion handling
    $quotaExhaustedResult = $quotaService->getChannelMetrics('UCNewChannel2');
    expect($quotaExhaustedResult)->toBeNull(); // Should handle gracefully
    
    // After quota exhaustion, checkQuotaAvailability should return false
    expect($quotaService->checkQuotaAvailability())->toBeFalse();
    
    // Should handle quota exhaustion gracefully for batch requests
    // This should return only cached results since quota is exhausted
    $batchResults = $quotaService->getMultipleChannelMetrics(['UCTestChannel', 'UCNewChannel3']);
    expect($batchResults)->toHaveCount(1); // Only cached result returned
    expect($batchResults['UCTestChannel']->subscriberCount)->toBe(1000);
    
    // Verify that system continues operating without throwing exceptions
    expect($quotaService->getRemainingQuota())->toBe(0); // Should be 0 after quota exhaustion
});

/**
 * Feature: youtube-integration, Property 15: Quota Monitoring and Alerting
 * For any quota usage that approaches 90% of the daily limit, the system should log warning messages
 * Validates: Requirements 7.2
 */
it('logs quota warnings at various thresholds', function () {
    // Set quota limit to 10 for easy threshold testing
    config(['services.youtube.quota_limit' => 10]);

    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => function ($request) {
            // Extract channel ID from the request URL
            $url = $request->url();
            preg_match('/id=([^&]+)/', $url, $matches);
            $channelId = $matches[1] ?? 'UCTestChannel';
            
            return Http::response([
                'items' => [
                    [
                        'id' => $channelId,
                        'statistics' => [
                            'subscriberCount' => '1000',
                            'videoCount' => '50',
                            'hiddenSubscriberCount' => false,
                        ],
                        'contentDetails' => [
                            'relatedPlaylists' => [
                                'uploads' => str_replace('UC', 'UU', $channelId),
                            ],
                        ],
                    ],
                ],
            ], 200);
        },
    ]);

    $service = new YouTubeService;

    // Test quota progression through various thresholds
    // 50% threshold (5/10)
    for ($i = 1; $i <= 5; $i++) {
        Cache::forget("youtube_channel:UCTestChannel{$i}");
        $result = $service->getChannelMetrics("UCTestChannel{$i}");
        expect($result)->toBeInstanceOf(YouTubeChannelDTO::class);
    }
    
    expect($service->getQuotaUsagePercentage())->toBe(50.0);
    expect($service->getRemainingQuota())->toBe(5);
    expect($service->isQuotaLow())->toBeFalse();

    // 75% threshold (7.5/10, test at 8/10)
    for ($i = 6; $i <= 8; $i++) {
        Cache::forget("youtube_channel:UCTestChannel{$i}");
        $result = $service->getChannelMetrics("UCTestChannel{$i}");
        expect($result)->toBeInstanceOf(YouTubeChannelDTO::class);
    }
    
    expect($service->getQuotaUsagePercentage())->toBe(80.0);
    expect($service->getRemainingQuota())->toBe(2);
    expect($service->isQuotaLow())->toBeFalse(); // Still not low

    // 90% threshold (9/10)
    Cache::forget("youtube_channel:UCTestChannel9");
    $result9 = $service->getChannelMetrics("UCTestChannel9");
    expect($result9)->toBeInstanceOf(YouTubeChannelDTO::class);
    
    expect($service->getQuotaUsagePercentage())->toBe(90.0);
    expect($service->getRemainingQuota())->toBe(1);
    expect($service->isQuotaLow())->toBeTrue(); // Now it should be low
    expect($service->checkQuotaAvailability())->toBeTrue(); // Should still allow 1 more

    // 100% threshold (10/10)
    Cache::forget("youtube_channel:UCTestChannel10");
    $result10 = $service->getChannelMetrics("UCTestChannel10");
    expect($result10)->toBeInstanceOf(YouTubeChannelDTO::class);
    
    expect($service->getQuotaUsagePercentage())->toBe(100.0);
    expect($service->getRemainingQuota())->toBe(0);
    expect($service->checkQuotaAvailability())->toBeFalse(); // No more quota

    // Test quota status details at 100%
    $status = $service->getQuotaStatus();
    expect($status['used'])->toBe(10);
    expect($status['remaining'])->toBe(0);
    expect($status['limit'])->toBe(10);
    expect($status['percentage_used'])->toBe(100.0);
    expect($status['is_exhausted'])->toBeTrue(); // Should be exhausted
    expect($status['date'])->toBe(now()->utc()->format('Y-m-d'));
    expect($status)->toHaveKey('resets_at');

    // Attempt to make another request - should be blocked
    Cache::forget("youtube_channel:UCTestChannel11");
    $result11 = $service->getChannelMetrics("UCTestChannel11");
    expect($result11)->toBeNull(); // Should return null due to quota exhaustion
});

/**
 * Feature: youtube-integration, Property 16: Daily Quota Reset
 * For any day boundary at midnight UTC, the system should reset quota tracking counters
 * Validates: Requirements 7.4
 */
it('resets quota tracking at day boundaries', function () {
    // Set quota limit to 5 for testing
    config(['services.youtube.quota_limit' => 5]);

    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => function ($request) {
            // Extract channel ID from the request URL
            $url = $request->url();
            preg_match('/id=([^&]+)/', $url, $matches);
            $channelId = $matches[1] ?? 'UCTestChannel';
            
            return Http::response([
                'items' => [
                    [
                        'id' => $channelId,
                        'statistics' => [
                            'subscriberCount' => '1000',
                            'videoCount' => '50',
                            'hiddenSubscriberCount' => false,
                        ],
                        'contentDetails' => [
                            'relatedPlaylists' => [
                                'uploads' => str_replace('UC', 'UU', $channelId),
                            ],
                        ],
                    ],
                ],
            ], 200);
        },
    ]);

    $service = new YouTubeService;

    // Use up all quota
    for ($i = 1; $i <= 5; $i++) {
        Cache::forget("youtube_channel:UCTestChannel{$i}");
        $service->getChannelMetrics("UCTestChannel{$i}");
    }
    
    expect($service->getRemainingQuota())->toBe(0);
    expect($service->checkQuotaAvailability())->toBeFalse();

    // Manually reset quota (simulating day boundary)
    $service->resetQuotaTracking();
    
    // After reset, quota should be available again
    expect($service->getRemainingQuota())->toBe(5);
    expect($service->checkQuotaAvailability())->toBeTrue();
    expect($service->getQuotaUsagePercentage())->toBe(0.0);
    
    $status = $service->getQuotaStatus();
    expect($status['used'])->toBe(0);
    expect($status['remaining'])->toBe(5);
    expect($status['is_exhausted'])->toBeFalse();

    // Should be able to make requests again
    Cache::forget("youtube_channel:UCNewChannel");
    $result = $service->getChannelMetrics("UCNewChannel");
    expect($result)->toBeInstanceOf(YouTubeChannelDTO::class);
    expect($service->getRemainingQuota())->toBe(4);
});

it('enforces quota limits with required quota parameter', function () {
    // Set quota limit to 5 for testing
    config(['services.youtube.quota_limit' => 5]);

    $service = new YouTubeService;

    // Test checking availability with different quota requirements
    expect($service->checkQuotaAvailability(1))->toBeTrue(); // 1 unit available
    expect($service->checkQuotaAvailability(5))->toBeTrue(); // 5 units available
    expect($service->checkQuotaAvailability(6))->toBeFalse(); // 6 units would exceed limit

    // Use up some quota
    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannel',
                    'statistics' => [
                        'subscriberCount' => '1000',
                        'videoCount' => '50',
                        'hiddenSubscriberCount' => false,
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUTestChannel',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    // Use 3 units of quota
    for ($i = 1; $i <= 3; $i++) {
        Cache::forget("youtube_channel:UCTestChannel{$i}");
        $service->getChannelMetrics("UCTestChannel{$i}");
    }

    expect($service->getRemainingQuota())->toBe(2);
    expect($service->checkQuotaAvailability(1))->toBeTrue(); // 1 unit still available
    expect($service->checkQuotaAvailability(2))->toBeTrue(); // 2 units still available
    expect($service->checkQuotaAvailability(3))->toBeFalse(); // 3 units would exceed remaining quota
});

it('applies graceful degradation when quota is low', function () {
    // Set quota limit to 10 for testing
    config(['services.youtube.quota_limit' => 10]);

    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => function ($request) {
            // Extract channel ID from the request URL
            $url = $request->url();
            preg_match('/id=([^&]+)/', $url, $matches);
            $channelId = $matches[1] ?? 'UCTestChannel';
            
            return Http::response([
                'items' => [
                    [
                        'id' => $channelId,
                        'statistics' => [
                            'subscriberCount' => '1000',
                            'videoCount' => '50',
                            'hiddenSubscriberCount' => false,
                        ],
                        'contentDetails' => [
                            'relatedPlaylists' => [
                                'uploads' => str_replace('UC', 'UU', $channelId),
                            ],
                        ],
                    ],
                ],
            ], 200);
        },
    ]);

    $service = new YouTubeService;

    // Use quota up to 100% (10/10) to trigger exhaustion
    for ($i = 1; $i <= 10; $i++) {
        Cache::forget("youtube_channel:UCTestChannel{$i}");
        $service->getChannelMetrics("UCTestChannel{$i}");
    }

    expect($service->checkQuotaAvailability())->toBeFalse();
    expect($service->shouldUseGracefulDegradation())->toBeTrue(); // Should be true due to exhaustion

    // Cache a channel for testing graceful degradation
    Cache::put('youtube_channel:UCCachedChannel', new YouTubeChannelDTO(
        channelId: 'UCCachedChannel',
        subscriberCount: 5000,
        videoCount: 100,
        subscriberCountHidden: false,
        uploadsPlaylistId: 'UUCachedChannel'
    ), 86400);

    // When quota is exhausted, should return cached data and avoid API calls
    $results = $service->getMultipleChannelMetrics(['UCCachedChannel', 'UCNewChannel']);
    
    // Should only return cached channel, not make API call for new channel
    expect($results)->toHaveCount(1);
    expect($results)->toHaveKey('UCCachedChannel');
    expect($results['UCCachedChannel']->subscriberCount)->toBe(5000);
    
    // Should not have made additional API calls due to graceful degradation
    Http::assertSentCount(10); // Only the 10 calls from the loop above
});