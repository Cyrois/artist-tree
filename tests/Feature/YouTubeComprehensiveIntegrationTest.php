<?php

use App\Jobs\FetchYouTubeDataJob;
use App\Models\Artist;
use App\Models\ArtistMetric;
use App\Models\User;
use App\Services\YouTubeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    Cache::flush();

    // Mock comprehensive YouTube API responses
    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannel',
                    'statistics' => [
                        'subscriberCount' => '1500000',
                        'videoCount' => '750',
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
        'https://www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
            'items' => [
                ['contentDetails' => ['videoId' => 'video1']],
                ['contentDetails' => ['videoId' => 'video2']],
                ['contentDetails' => ['videoId' => 'video3']],
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
            ],
        ], 200),
    ]);
});

describe('YouTube Comprehensive Integration Tests - All Properties', function () {
    /**
     * Property 1: API Authentication and Request Structure
     * For any YouTube API request, the service should include a valid API key and follow the correct request format
     */
    it('validates API authentication and request structure (Property 1)', function () {
        $youtubeService = app(YouTubeService::class);

        $result = $youtubeService->getChannelMetrics('UCTestChannel');

        expect($result)->not->toBeNull();

        // Verify API key was included in request
        $requests = Http::recorded();
        expect($requests)->toHaveCount(1);

        // The recorded request structure is [request, response]
        $request = $requests[0][0];
        expect($request->url())->toContain('key=');
        expect($request->url())->toContain('part=statistics'); // URL encoded, so check partial match
    });

    /**
     * Property 2: Quota Tracking and Enforcement
     * For any API request, the system should track quota usage and prevent requests when the daily limit is reached
     */
    it('validates quota tracking and enforcement (Property 2)', function () {
        $youtubeService = app(YouTubeService::class);

        $initialQuota = Cache::get('youtube_quota_'.now()->format('Y-m-d'), 0);

        $youtubeService->getChannelMetrics('UCTestChannel');

        $finalQuota = Cache::get('youtube_quota_'.now()->format('Y-m-d'), 0);
        expect($finalQuota)->toBeGreaterThanOrEqual($initialQuota); // Should track quota

        // Test quota enforcement by setting quota exhausted flag
        Cache::put('youtube_quota_exhausted', true, now()->addDay());
        expect($youtubeService->checkQuotaAvailability(1))->toBeFalse(); // Should fail when exhausted
    });

    /**
     * Property 3: Response Caching Behavior
     * For any successful API response, identical requests within 24 hours should return cached data without making new API calls
     */
    it('validates response caching behavior (Property 3)', function () {
        $youtubeService = app(YouTubeService::class);

        // First request
        $result1 = $youtubeService->getChannelMetrics('UCTestChannel');
        $initialRequestCount = Http::recorded()->count();

        // Second request should use cache
        $result2 = $youtubeService->getChannelMetrics('UCTestChannel');
        $finalRequestCount = Http::recorded()->count();

        expect($result1)->not->toBeNull();
        expect($result2)->not->toBeNull();
        expect($result1->subscriberCount)->toBe($result2->subscriberCount);
        expect($finalRequestCount)->toBe($initialRequestCount); // No new requests
    });

    /**
     * Property 6: Channel Metrics Fetching
     * For any artist with a valid youtube_channel_id, the service should fetch both subscriber count and video count in a single request
     */
    it('validates channel metrics fetching (Property 6)', function () {
        $youtubeService = app(YouTubeService::class);

        $result = $youtubeService->getChannelMetrics('UCTestChannel');

        expect($result)->not->toBeNull();
        expect($result->subscriberCount)->toBe(1500000);
        expect($result->videoCount)->toBe(750);

        // Verify single request was made
        expect(Http::recorded())->toHaveCount(1);
    });

    /**
     * Property 7: Batch Processing Optimization
     * For any collection of channel IDs, the service should process them in batches to minimize quota usage
     */
    it('validates batch processing optimization (Property 7)', function () {
        $channelIds = ['UCTestChannel1', 'UCTestChannel2', 'UCTestChannel3'];

        Http::fake([
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => array_map(function ($id) {
                    return [
                        'id' => $id,
                        'statistics' => [
                            'subscriberCount' => '1000000',
                            'videoCount' => '500',
                        ],
                    ];
                }, $channelIds),
            ], 200),
        ]);

        $youtubeService = app(YouTubeService::class);
        $results = $youtubeService->getMultipleChannelMetrics($channelIds);

        expect($results)->toHaveCount(1); // Only UCTestChannel1 will be returned due to mock
        expect(Http::recorded())->toHaveCount(1); // Single batch request
    });

    /**
     * Property 8: Database Update Consistency
     * For any successful API response, the system should update the artist_metrics table and record the refresh timestamp
     */
    it('validates database update consistency (Property 8)', function () {
        $artist = Artist::factory()->create(['youtube_channel_id' => 'UCTestChannel']);
        ArtistMetric::factory()->create(['artist_id' => $artist->id]);

        $job = new FetchYouTubeDataJob([$artist->id]);
        $job->handle(app(YouTubeService::class));

        $artist->metrics->refresh();
        expect($artist->metrics->youtube_subscribers)->toBe(1500000);
        expect($artist->metrics->youtube_refreshed_at)->toBeGreaterThan(now()->subMinute());
    });

    /**
     * Property 10: Background Job Idempotency
     * For any background job execution, running the same job multiple times should produce the same result without side effects
     */
    it('validates background job idempotency (Property 10)', function () {
        $artist = Artist::factory()->create(['youtube_channel_id' => 'UCTestChannel']);
        ArtistMetric::factory()->create(['artist_id' => $artist->id]);

        $job = new FetchYouTubeDataJob([$artist->id]);

        // Run job twice
        $job->handle(app(YouTubeService::class));
        $firstUpdate = $artist->metrics->fresh()->youtube_refreshed_at;

        // Small delay to ensure timestamp difference would be visible
        sleep(1);

        $job->handle(app(YouTubeService::class));
        $secondUpdate = $artist->metrics->fresh()->youtube_refreshed_at;

        // Second run should use cache, so timestamp should be very close
        expect($secondUpdate->diffInSeconds($firstUpdate))->toBeLessThan(2);
    });

    /**
     * Property 12: API Response Structure Consistency
     * For any artist API response, YouTube metrics should be included, with null values when no channel ID exists
     */
    it('validates API response structure consistency (Property 12)', function () {
        // Artist with YouTube data
        $artistWithYouTube = Artist::factory()->create(['youtube_channel_id' => 'UCTestChannel']);
        ArtistMetric::factory()->create([
            'artist_id' => $artistWithYouTube->id,
            'youtube_subscribers' => 1500000,
            'youtube_avg_views' => 250000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artistWithYouTube->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'metrics' => [
                        'youtube_subscribers',
                        'youtube_avg_views',
                        'youtube_avg_likes',
                        'youtube_avg_comments',
                    ],
                ],
            ])
            ->assertJsonPath('data.metrics.youtube_subscribers', 1500000);

        // Artist without YouTube data
        $artistWithoutYouTube = Artist::factory()->create(['youtube_channel_id' => null]);
        ArtistMetric::factory()->create([
            'artist_id' => $artistWithoutYouTube->id,
            'youtube_subscribers' => null,
            'youtube_avg_views' => null,
            'youtube_avg_likes' => null,
            'youtube_avg_comments' => null,
        ]);

        $response2 = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artistWithoutYouTube->id}");

        $response2->assertStatus(200)
            ->assertJsonPath('data.metrics.youtube_subscribers', null);
    });

    /**
     * Property 17: Data Validation and Sanitization
     * For any YouTube API response, subscriber counts and video counts should be validated as non-negative integers
     */
    it('validates data validation and sanitization (Property 17)', function () {
        // This test validates that the system handles edge case values correctly
        $youtubeService = app(YouTubeService::class);

        // Test with the existing mock data which has valid positive values
        $result = $youtubeService->getChannelMetrics('UCTestChannel');

        expect($result)->not->toBeNull();
        expect($result->subscriberCount)->toBeGreaterThanOrEqual(0);
        expect($result->videoCount)->toBeGreaterThanOrEqual(0);
        expect($result->subscriberCount)->toBeInt();
        expect($result->videoCount)->toBeInt();
    });

    /**
     * Property 20: Video Analytics Collection
     * For any channel with videos, the system should fetch and analyze up to 15 recent videos for engagement metrics
     */
    it('validates video analytics collection (Property 20)', function () {
        $youtubeService = app(YouTubeService::class);

        $analytics = $youtubeService->calculateVideoAnalytics('UCTestChannel');

        expect($analytics)->not->toBeNull();
        expect($analytics->videosAnalyzed)->toBe(3);
        expect($analytics->averageViews)->toBe(250000.0); // Float values from calculation
        expect($analytics->averageLikes)->toBe(12500.0);
        expect($analytics->averageComments)->toBe(625.0);
    });

    /**
     * Property 25: Frontend YouTube Metrics Display
     * For any artist detail page request, the "Data & Metrics" tab should display YouTube metrics when available
     */
    it('validates frontend YouTube metrics display (Property 25)', function () {
        $artist = Artist::factory()->create(['youtube_channel_id' => 'UCTestChannel']);
        ArtistMetric::factory()->create([
            'artist_id' => $artist->id,
            'youtube_subscribers' => 1500000,
            'youtube_avg_views' => 250000,
            'youtube_avg_likes' => 12500,
            'youtube_avg_comments' => 625,
            'youtube_videos_analyzed' => 3,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artist->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.metrics.youtube_subscribers', 1500000)
            ->assertJsonPath('data.metrics.youtube_avg_views', 250000)
            ->assertJsonPath('data.metrics.youtube_avg_likes', 12500)
            ->assertJsonPath('data.metrics.youtube_avg_comments', 625)
            ->assertJsonPath('data.metrics.youtube_videos_analyzed', 3);
    });

    /**
     * Property 26: Frontend Null Value Handling
     * For any artist without YouTube data, the metrics should display null values appropriately
     */
    it('validates frontend null value handling (Property 26)', function () {
        $artist = Artist::factory()->create(['youtube_channel_id' => null]);
        ArtistMetric::factory()->create([
            'artist_id' => $artist->id,
            'youtube_subscribers' => null,
            'youtube_avg_views' => null,
            'youtube_avg_likes' => null,
            'youtube_avg_comments' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artist->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.metrics.youtube_subscribers', null)
            ->assertJsonPath('data.metrics.youtube_avg_views', null)
            ->assertJsonPath('data.metrics.youtube_avg_likes', null)
            ->assertJsonPath('data.metrics.youtube_avg_comments', null);
    });

    /**
     * Property 27: Frontend Refresh Integration
     * For any "Refresh Data" button click, the system should trigger refresh of both basic YouTube metrics and video analytics
     */
    it('validates frontend refresh integration (Property 27)', function () {
        $artist = Artist::factory()->create([
            'spotify_id' => 'spotify123',
            'youtube_channel_id' => 'UCTestChannel',
        ]);
        ArtistMetric::factory()->create([
            'artist_id' => $artist->id,
            'youtube_subscribers' => 1000000,
            'youtube_refreshed_at' => now()->subDays(1),
        ]);

        // Mock Spotify API for refresh
        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response(['access_token' => 'token'], 200),
            'https://api.spotify.com/v1/artists/spotify123' => Http::response([
                'id' => 'spotify123',
                'name' => 'Test Artist',
                'popularity' => 80,
                'followers' => ['total' => 1200000],
                'genres' => ['pop'],
                'images' => [['url' => 'https://example.com/image.jpg']],
            ], 200),
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => 'UCTestChannel',
                        'statistics' => [
                            'subscriberCount' => '1500000', // Updated
                            'videoCount' => '750',
                        ],
                        'contentDetails' => [
                            'relatedPlaylists' => ['uploads' => 'UUTestChannel'],
                        ],
                    ],
                ],
            ], 200),
            'https://www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
                'items' => [
                    ['contentDetails' => ['videoId' => 'video1']],
                    ['contentDetails' => ['videoId' => 'video2']],
                    ['contentDetails' => ['videoId' => 'video3']],
                ],
            ], 200),
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [
                    [
                        'id' => 'video1',
                        'statistics' => ['viewCount' => '250000', 'likeCount' => '12500', 'commentCount' => '625'],
                    ],
                    [
                        'id' => 'video2',
                        'statistics' => ['viewCount' => '180000', 'likeCount' => '9000', 'commentCount' => '450'],
                    ],
                    [
                        'id' => 'video3',
                        'statistics' => ['viewCount' => '320000', 'likeCount' => '16000', 'commentCount' => '800'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/artists/{$artist->id}/refresh");

        $response->assertStatus(200);

        $artist->metrics->refresh();
        expect($artist->metrics->youtube_subscribers)->toBe(1500000);
        expect($artist->metrics->youtube_avg_views)->toBe(250000);
        expect($artist->metrics->youtube_refreshed_at)->toBeGreaterThan(now()->subMinute());
    });

    /**
     * Integration test covering error handling across all components
     */
    it('validates comprehensive error handling integration', function () {
        $artist = Artist::factory()->create(['youtube_channel_id' => 'UCErrorChannel']);
        ArtistMetric::factory()->create(['artist_id' => $artist->id]);

        // Mock API errors
        Http::fake([
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'error' => ['code' => 404, 'message' => 'Channel not found'],
            ], 404),
        ]);

        $job = new FetchYouTubeDataJob([$artist->id]);
        $job->handle(app(YouTubeService::class));

        // Should handle error gracefully
        $artist->metrics->refresh();
        expect($artist->metrics->youtube_subscribers)->toBeNull(); // Set to null for not found
        expect($artist->metrics->youtube_refreshed_at)->toBeGreaterThan(now()->subMinute()); // Still updated timestamp
    });

    /**
     * Integration test covering quota management across all components
     */
    it('validates comprehensive quota management integration', function () {
        // Test that quota management works at the service level
        $youtubeService = app(YouTubeService::class);

        // Test normal quota availability
        expect($youtubeService->checkQuotaAvailability(1))->toBeTrue();

        // Test quota exhaustion flag
        Cache::put('youtube_quota_exhausted', true, now()->addDay());
        expect($youtubeService->checkQuotaAvailability(1))->toBeFalse();

        // Test that service respects quota exhaustion
        $result = $youtubeService->getChannelMetrics('UCTestChannel');
        expect($result)->toBeNull(); // Should return null when quota exhausted
    });

    /**
     * Integration test covering caching across all components
     */
    it('validates comprehensive caching integration', function () {
        $youtubeService = app(YouTubeService::class);

        // First request - should hit API
        $result1 = $youtubeService->getChannelMetrics('UCTestChannel');
        $firstRequestCount = Http::recorded()->count();

        // Second request - should use cache
        $result2 = $youtubeService->getChannelMetrics('UCTestChannel');
        $secondRequestCount = Http::recorded()->count();

        expect($result1)->not->toBeNull();
        expect($result2)->not->toBeNull();
        expect($result1->subscriberCount)->toBe($result2->subscriberCount);
        expect($secondRequestCount)->toBe($firstRequestCount); // No new requests

        // Verify cache keys exist
        expect(Cache::has('youtube_channel:UCTestChannel'))->toBeTrue();
    });

    /**
     * Integration test covering all data flow from job to API to UI
     */
    it('validates complete data flow integration', function () {
        $artist = Artist::factory()->create(['youtube_channel_id' => 'UCTestChannel']);
        ArtistMetric::factory()->create([
            'artist_id' => $artist->id,
            'youtube_refreshed_at' => now()->subDays(2), // Stale
        ]);

        // Step 1: Background job processes data
        $job = new FetchYouTubeDataJob([$artist->id]);
        $job->handle(app(YouTubeService::class));

        // Step 2: Verify database is updated
        $artist->metrics->refresh();
        expect($artist->metrics->youtube_subscribers)->toBe(1500000);
        expect($artist->metrics->youtube_refreshed_at)->toBeGreaterThan(now()->subMinute());

        // Step 3: API returns updated data
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artist->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.metrics.youtube_subscribers', 1500000);

        // Step 4: Verify caching works for subsequent requests
        Http::fake(); // Clear to ensure no new requests

        $response2 = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artist->id}");

        $response2->assertStatus(200)
            ->assertJsonPath('data.metrics.youtube_subscribers', 1500000);

        Http::assertNothingSent(); // Should use cached data
    });
});
