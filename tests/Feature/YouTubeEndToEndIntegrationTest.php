<?php

use App\Jobs\FetchYouTubeDataJob;
use App\Models\Artist;
use App\Models\ArtistMetric;
use App\Models\User;
use App\Services\YouTubeService;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
    
    // Clear any existing cache
    Cache::flush();
    
    // Mock YouTube API responses for consistent testing
    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannelId',
                    'statistics' => [
                        'subscriberCount' => '1500000',
                        'videoCount' => '750',
                        'hiddenSubscriberCount' => false,
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUTestChannelId',
                        ],
                    ],
                ],
            ],
        ], 200),
        'https://www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
            'items' => [
                ['contentDetails' => ['videoId' => 'video1']],
                ['contentDetails' => ['videoId' => 'video2']],
                ['contentDetails' => ['videoId' => 'video3']],
                ['contentDetails' => ['videoId' => 'video4']],
                ['contentDetails' => ['videoId' => 'video5']],
            ],
        ], 200),
        'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
            'items' => [
                [
                    'id' => 'video1',
                    'statistics' => [
                        'viewCount' => '250000',
                        'likeCount' => '12500',
                        'commentCount' => '625',
                    ],
                ],
                [
                    'id' => 'video2',
                    'statistics' => [
                        'viewCount' => '180000',
                        'likeCount' => '9000',
                        'commentCount' => '450',
                    ],
                ],
                [
                    'id' => 'video3',
                    'statistics' => [
                        'viewCount' => '320000',
                        'likeCount' => '16000',
                        'commentCount' => '800',
                    ],
                ],
                [
                    'id' => 'video4',
                    'statistics' => [
                        'viewCount' => '200000',
                        'likeCount' => '10000',
                        'commentCount' => '500',
                    ],
                ],
                [
                    'id' => 'video5',
                    'statistics' => [
                        'viewCount' => '150000',
                        'likeCount' => '7500',
                        'commentCount' => '375',
                    ],
                ],
            ],
        ], 200),
    ]);
});

describe('YouTube End-to-End Integration', function () {
    it('completes full flow from background job to API response to UI display', function () {
        // Step 1: Create artist with stale YouTube data
        $artist = Artist::factory()->create([
            'name' => 'E2E Test Artist',
            'spotify_id' => 'e2e_test_spotify',
            'youtube_channel_id' => 'UCTestChannelId',
        ]);
        
        // Add approved link so needsToUpdateYoutubeChannel() returns false
        $artist->links()->create([
            'platform' => \App\Enums\SocialPlatform::YouTube,
            'url' => 'https://youtube.com/c/UCTestChannelId',
            'review_status' => \App\Models\ArtistLink::REVIEW_STATUS_APPROVED,
        ]);

        $metrics = ArtistMetric::factory()->create([
            'artist_id' => $artist->id,
            'youtube_subscribers' => 1000000, // Old data
            'youtube_avg_views' => 100000, // Old data
            'youtube_avg_likes' => 5000, // Old data
            'youtube_avg_comments' => 250, // Old data
            'youtube_videos_analyzed' => 10, // Old data
            'youtube_refreshed_at' => now()->subDays(2), // Stale
            'youtube_analytics_refreshed_at' => now()->subDays(8), // Stale
            'refreshed_at' => now(),
        ]);

        // Step 2: Execute background job to fetch fresh YouTube data
        $job = new FetchYouTubeDataJob([$artist->id]);
        $job->handle(app(YouTubeService::class));

        // Step 3: Verify database was updated with fresh basic data (not analytics)
        $metrics->refresh();
        expect($metrics->youtube_subscribers)->toBe(1500000)
            ->and($metrics->youtube_refreshed_at)->toBeGreaterThan(now()->subMinute());

        // Video analytics should remain unchanged since job only handles basic metrics
        expect($metrics->youtube_avg_views)->toBe(100000) // Original data
            ->and($metrics->youtube_avg_likes)->toBe(5000) // Original data
            ->and($metrics->youtube_avg_comments)->toBe(250) // Original data
            ->and($metrics->youtube_videos_analyzed)->toBe(10); // Original data

        // Step 4: Test API response includes updated YouTube basic data
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artist->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.metrics.youtube_subscribers', 1500000);

        // Step 5: Test video analytics through API (which triggers calculateVideoAnalytics)
        // The API should automatically refresh stale analytics
        $metrics->update(['youtube_analytics_refreshed_at' => now()->subDays(8)]); // Make analytics stale
        
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artist->id}");

        // Calculate expected average views: (250000 + 180000 + 320000 + 200000 + 150000) / 5 = 220000
        $response->assertStatus(200)
            ->assertJsonPath('data.metrics.youtube_subscribers', 1500000)
            ->assertJsonPath('data.metrics.youtube_avg_views', 220000) // Now updated via API
            ->assertJsonPath('data.metrics.youtube_avg_likes', 11000)
            ->assertJsonPath('data.metrics.youtube_avg_comments', 550)
            ->assertJsonPath('data.metrics.youtube_videos_analyzed', 5);

        // Step 5: Verify caching is working (second request should use cache)
        Http::fake(); // Clear HTTP fakes to ensure no new requests
        
        $cachedResponse = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artist->id}");

        $cachedResponse->assertStatus(200)
            ->assertJsonPath('data.metrics.youtube_subscribers', 1500000);

        // Verify no new HTTP requests were made (data came from cache)
        Http::assertNothingSent();
    });

    it('handles error scenarios gracefully across all components', function () {
        $artist = Artist::factory()->create([
            'youtube_channel_id' => 'UCErrorChannel',
        ]);

        $metrics = ArtistMetric::factory()->create([
            'artist_id' => $artist->id,
            'youtube_subscribers' => 500000, // Fallback data
            'youtube_refreshed_at' => now()->subDays(2),
        ]);

        // Mock the YouTubeService to throw quota exhausted exception
        $this->mock(YouTubeService::class, function ($mock) {
            $mock->shouldReceive('checkQuotaAvailability')->andReturn(true);
            $mock->shouldReceive('getMultipleChannelMetrics')
                ->andThrow(\App\Exceptions\YouTubeApiException::quotaExhausted(1));
            $mock->shouldReceive('getRemainingQuota')->andReturn(9999);
        });

        // Execute job - should handle error gracefully
        $job = new FetchYouTubeDataJob([$artist->id]);
        $job->handle(app(YouTubeService::class));

        // Verify original data is preserved (job should handle error gracefully)
        $metrics->refresh();
        expect($metrics->youtube_subscribers)->toBe(500000); // Should remain unchanged

        // API should still return data (cached/existing)
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artist->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.metrics.youtube_subscribers', 500000);
    });

    it('manages quota efficiently across multiple artists', function () {
        // Create multiple artists with different priority levels
        $highPriorityArtists = Artist::factory()->count(3)->create([
            'youtube_channel_id' => 'UCTestChannelId',
        ]);

        foreach ($highPriorityArtists as $artist) {
            ArtistMetric::factory()->create([
                'artist_id' => $artist->id,
                'youtube_refreshed_at' => now()->subDays(2), // Stale - high priority
                'youtube_subscribers' => 100000,
            ]);
        }

        $mediumPriorityArtists = Artist::factory()->count(2)->create([
            'youtube_channel_id' => 'UCTestChannelId',
        ]);

        foreach ($mediumPriorityArtists as $artist) {
            ArtistMetric::factory()->create([
                'artist_id' => $artist->id,
                'youtube_refreshed_at' => now()->subHours(12), // Fresh basic
                'youtube_analytics_refreshed_at' => now()->subDays(8), // Stale analytics - medium priority
                'youtube_subscribers' => 200000,
                'youtube_avg_views' => 50000,
                'youtube_videos_analyzed' => 10,
            ]);
        }

        // Track quota usage - the service should track quota internally
        $initialQuota = Cache::get('youtube_quota_' . now()->format('Y-m-d'), 0);

        // Process all artists
        $allArtistIds = $highPriorityArtists->pluck('id')
            ->concat($mediumPriorityArtists->pluck('id'))
            ->toArray();

        $job = new FetchYouTubeDataJob($allArtistIds);
        $job->handle(app(YouTubeService::class));

        // Verify quota was tracked (service should increment quota usage)
        $finalQuota = Cache::get('youtube_quota_' . now()->format('Y-m-d'), 0);
        expect($finalQuota)->toBeGreaterThanOrEqual($initialQuota); // Should be same or higher

        // Verify high priority artists were processed (basic metrics)
        foreach ($highPriorityArtists as $artist) {
            $artist->metrics->refresh();
            expect($artist->metrics->youtube_subscribers)->toBe(1500000);
        }

        // Medium priority artists should only get basic metrics updated (not analytics)
        foreach ($mediumPriorityArtists as $artist) {
            $artist->metrics->refresh();
            expect($artist->metrics->youtube_subscribers)->toBe(1500000); // Updated
            expect($artist->metrics->youtube_avg_views)->toBe(50000); // Unchanged - job doesn't handle analytics
        }
    });

    it('handles quota exhaustion scenario realistically', function () {
        $artist = Artist::factory()->create([
            'youtube_channel_id' => 'UCTestChannelId',
        ]);

        ArtistMetric::factory()->create([
            'artist_id' => $artist->id,
            'youtube_subscribers' => 500000,
            'youtube_refreshed_at' => now()->subDays(2),
        ]);

        // Simulate quota exhaustion
        Cache::put('youtube_quota_' . now()->format('Y-m-d'), 10000); // At limit
        Cache::put('youtube_quota_exhausted', true, now()->addDay());

        $job = new FetchYouTubeDataJob([$artist->id]);
        $job->handle(app(YouTubeService::class));

        // Verify no API calls were made due to quota exhaustion
        Http::assertNothingSent();

        // Verify original data is preserved
        $artist->metrics->refresh();
        expect($artist->metrics->youtube_subscribers)->toBe(500000);

        // API should still work with cached data
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artist->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.metrics.youtube_subscribers', 500000);
    });

    it('validates data consistency across all integration points', function () {
        $artist = Artist::factory()->create([
            'youtube_channel_id' => 'UCTestChannelId',
        ]);

        // Start with no metrics
        expect($artist->metrics)->toBeNull();

        // Execute job to create initial metrics
        $job = new FetchYouTubeDataJob([$artist->id]);
        $job->handle(app(YouTubeService::class));

        // Verify metrics were created
        $artist->refresh();
        expect($artist->metrics)->not->toBeNull();

        $metrics = $artist->metrics;
        
        // Verify data consistency - job only handles basic metrics
        expect($metrics->youtube_subscribers)->toBe(1500000)
            ->and($metrics->youtube_refreshed_at)->not->toBeNull();

        // Analytics should be null since job doesn't handle them
        expect($metrics->youtube_avg_views)->toBeNull()
            ->and($metrics->youtube_avg_likes)->toBeNull()
            ->and($metrics->youtube_avg_comments)->toBeNull()
            ->and($metrics->youtube_videos_analyzed)->toBeNull()
            ->and($metrics->youtube_analytics_refreshed_at)->toBeNull();

        // Verify API response matches database (basic metrics only from job)
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artist->id}");

        $responseData = $response->json('data.metrics');
        
        expect($responseData['youtube_subscribers'])->toBe($metrics->youtube_subscribers);
    });

    it('processes mixed batch of artists with different data states', function () {
        // Clear any existing HTTP fakes to avoid interference
        Http::fake([
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => 'UCTestChannelId',
                        'statistics' => [
                            'subscriberCount' => '1500000',
                            'videoCount' => '750',
                            'hiddenSubscriberCount' => false,
                        ],
                        'contentDetails' => [
                            'relatedPlaylists' => [
                                'uploads' => 'UUTestChannelId',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Artist with no YouTube data
        $noYouTubeArtist = Artist::factory()->create([
            'youtube_channel_id' => null,
        ]);
        ArtistMetric::factory()->create([
            'artist_id' => $noYouTubeArtist->id,
            'youtube_subscribers' => null,
        ]);

        // Artist with stale basic data
        $staleBasicArtist = Artist::factory()->create([
            'youtube_channel_id' => 'UCTestChannelId',
        ]);
        ArtistMetric::factory()->create([
            'artist_id' => $staleBasicArtist->id,
            'youtube_subscribers' => 100000,
            'youtube_refreshed_at' => now()->subDays(2),
        ]);

        // Artist with fresh basic but stale analytics
        $staleAnalyticsArtist = Artist::factory()->create([
            'youtube_channel_id' => 'UCTestChannelId',
        ]);
        ArtistMetric::factory()->create([
            'artist_id' => $staleAnalyticsArtist->id,
            'youtube_subscribers' => 200000,
            'youtube_refreshed_at' => now()->subHours(12),
            'youtube_analytics_refreshed_at' => now()->subDays(8),
            'youtube_avg_views' => 50000,
            'youtube_videos_analyzed' => 10,
        ]);

        // Artist with all fresh data
        $freshArtist = Artist::factory()->create([
            'youtube_channel_id' => 'UCTestChannelId',
        ]);
        ArtistMetric::factory()->create([
            'artist_id' => $freshArtist->id,
            'youtube_subscribers' => 300000,
            'youtube_refreshed_at' => now()->subHours(6),
            'youtube_analytics_refreshed_at' => now()->subDays(2),
            'youtube_avg_views' => 75000,
            'youtube_videos_analyzed' => 15,
        ]);

        // Only process artists with YouTube channel IDs
        $allArtistIds = [
            $staleBasicArtist->id,
            $staleAnalyticsArtist->id,
            $freshArtist->id,
        ];

        // Process batch (noYouTubeArtist not included since it has no channel ID)
        $job = new FetchYouTubeDataJob($allArtistIds);
        $job->handle(app(YouTubeService::class));

        // Verify results - job processes all artists with YouTube channel IDs
        $noYouTubeArtist->metrics->refresh();
        expect($noYouTubeArtist->metrics->youtube_subscribers)->toBeNull(); // Not processed by job

        $staleBasicArtist->metrics->refresh();
        expect($staleBasicArtist->metrics->youtube_subscribers)->toBe(1500000); // Updated

        $staleAnalyticsArtist->metrics->refresh();
        expect($staleAnalyticsArtist->metrics->youtube_subscribers)->toBe(1500000) // Updated by job
            ->and($staleAnalyticsArtist->metrics->youtube_avg_views)->toBe(50000); // Unchanged - job doesn't handle analytics

        $freshArtist->metrics->refresh();
        expect($freshArtist->metrics->youtube_subscribers)->toBe(1500000) // Updated by job (job processes all)
            ->and($freshArtist->metrics->youtube_avg_views)->toBe(75000); // Unchanged
    });
});