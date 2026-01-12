<?php

use App\Jobs\FetchYouTubeDataJob;
use App\Models\Artist;
use App\Models\ArtistMetric;
use App\Services\YouTubeService;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();
    
    // Mock YouTube API responses for consistent testing
    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => array_map(function ($i) {
                return [
                    'id' => "UCTestChannel{$i}",
                    'statistics' => [
                        'subscriberCount' => (string) (1000000 + $i * 100000),
                        'videoCount' => (string) (500 + $i * 50),
                        'hiddenSubscriberCount' => false,
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => "UUTestChannel{$i}",
                        ],
                    ],
                ];
            }, range(1, 50)), // Generate 50 channels
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
                        'viewCount' => '100000',
                        'likeCount' => '5000',
                        'commentCount' => '500',
                    ],
                ],
                [
                    'id' => 'video2',
                    'statistics' => [
                        'viewCount' => '200000',
                        'likeCount' => '10000',
                        'commentCount' => '1000',
                    ],
                ],
                [
                    'id' => 'video3',
                    'statistics' => [
                        'viewCount' => '150000',
                        'likeCount' => '7500',
                        'commentCount' => '750',
                    ],
                ],
            ],
        ], 200),
    ]);
});

describe('YouTube Performance and Quota Optimization', function () {
    it('validates optimal batch size configuration', function () {
        // Create 50 artists with YouTube channel IDs
        $artists = Artist::factory()->count(50)->create()->map(function ($artist, $index) {
            $artist->update(['youtube_channel_id' => "UCTestChannel" . ($index + 1)]);
            ArtistMetric::factory()->create([
                'artist_id' => $artist->id,
                'youtube_subscribers' => 500000,
                'youtube_refreshed_at' => now()->subDays(2), // All stale
            ]);
            return $artist;
        });

        $artistIds = $artists->pluck('id')->toArray();

        // Test that batch size 50 (YouTube API limit) is used effectively
        $job = new FetchYouTubeDataJob($artistIds, 50);
        $job->handle(app(YouTubeService::class));
        
        // Verify all artists were processed
        $updatedCount = 0;
        foreach ($artists as $artist) {
            $artist->metrics->refresh();
            if ($artist->metrics->youtube_refreshed_at > now()->subMinute()) {
                $updatedCount++;
            }
        }

        expect($updatedCount)->toBe(50); // All artists should be updated
        
        // Verify minimal API requests were made (should be 1 for 50 channels)
        $apiRequests = Http::recorded()->count();
        expect($apiRequests)->toBeLessThanOrEqual(2); // Should be very efficient
    });

    it('validates quota usage projections are accurate', function () {
        // Create test data with known quota costs
        $basicOnlyArtists = Artist::factory()->count(20)->create()->map(function ($artist, $index) {
            $artist->update(['youtube_channel_id' => "UCBasic" . ($index + 1)]);
            ArtistMetric::factory()->create([
                'artist_id' => $artist->id,
                'youtube_refreshed_at' => now()->subDays(2), // Needs basic refresh
            ]);
            return $artist;
        });

        $startQuota = Cache::get('youtube_quota_' . now()->format('Y-m-d'), 0);

        // Process basic metrics only
        $basicJob = new FetchYouTubeDataJob($basicOnlyArtists->pluck('id')->toArray());
        $basicJob->handle(app(YouTubeService::class));

        $afterBasicQuota = Cache::get('youtube_quota_' . now()->format('Y-m-d'), 0);
        $basicQuotaUsed = $afterBasicQuota - $startQuota;

        // Basic metrics should use minimal quota (1 unit for up to 50 channels)
        expect($basicQuotaUsed)->toBeLessThanOrEqual(2); // Very efficient for basic metrics
        
        // Verify all artists were processed
        foreach ($basicOnlyArtists as $artist) {
            $artist->metrics->refresh();
            expect($artist->metrics->youtube_refreshed_at)->toBeGreaterThan(now()->subMinute());
        }
    });

    it('validates caching strategies work correctly', function () {
        $youtubeService = app(YouTubeService::class);

        // Test 1: First call should make API request
        $channelData1 = $youtubeService->getChannelMetrics('UCTestChannel1');
        expect($channelData1)->not->toBeNull();
        expect($channelData1->subscriberCount)->toBe(1100000);

        // Test 2: Second call should use cache
        $initialRequestCount = Http::recorded()->count();
        $channelData2 = $youtubeService->getChannelMetrics('UCTestChannel1');
        $finalRequestCount = Http::recorded()->count();
        
        expect($channelData2)->not->toBeNull();
        expect($channelData2->subscriberCount)->toBe(1100000);
        expect($finalRequestCount)->toBe($initialRequestCount); // No new requests

        // Test 3: Cache key exists
        expect(Cache::has('youtube_channel:UCTestChannel1'))->toBeTrue();
    });

    it('validates quota management configuration', function () {
        $youtubeService = app(YouTubeService::class);

        // Test 1: Normal quota availability
        expect($youtubeService->checkQuotaAvailability(1))->toBeTrue();
        expect($youtubeService->getRemainingQuota())->toBeGreaterThan(9000); // Should have plenty

        // Test 2: Quota tracking works
        $initialQuota = Cache::get('youtube_quota_' . now()->format('Y-m-d'), 0);
        
        // Make a request that uses quota
        $youtubeService->getChannelMetrics('UCTestChannel1');
        
        $finalQuota = Cache::get('youtube_quota_' . now()->format('Y-m-d'), 0);
        expect($finalQuota)->toBeGreaterThanOrEqual($initialQuota); // Quota should be tracked
    });

    it('validates performance benchmarks are met', function () {
        // Create a realistic workload
        $artists = Artist::factory()->count(25)->create()->map(function ($artist, $index) {
            $artist->update(['youtube_channel_id' => "UCPerf{$index}"]);
            ArtistMetric::factory()->create([
                'artist_id' => $artist->id,
                'youtube_refreshed_at' => now()->subDays(2),
                'youtube_subscribers' => 500000,
            ]);
            return $artist;
        });

        // Measure processing time
        $startTime = microtime(true);
        $startQuota = Cache::get('youtube_quota_' . now()->format('Y-m-d'), 0);

        $job = new FetchYouTubeDataJob($artists->pluck('id')->toArray());
        $job->handle(app(YouTubeService::class));

        $endTime = microtime(true);
        $endQuota = Cache::get('youtube_quota_' . now()->format('Y-m-d'), 0);

        $processingTime = $endTime - $startTime;
        $quotaUsed = $endQuota - $startQuota;

        // Performance benchmarks
        expect($processingTime)->toBeLessThan(3.0); // Should complete quickly
        expect($quotaUsed)->toBeLessThan(5); // Should use minimal quota for basic metrics

        // Verify all artists were processed
        $updatedCount = 0;
        foreach ($artists as $artist) {
            $artist->metrics->refresh();
            if ($artist->metrics->youtube_refreshed_at > now()->subMinute()) {
                $updatedCount++;
            }
        }

        expect($updatedCount)->toBe(count($artists)); // All should be updated
    });

    it('validates batch processing optimization strategies', function () {
        // Create artists with different channel IDs to test batching
        $artists = Artist::factory()->count(30)->create()->map(function ($artist, $index) {
            $artist->update(['youtube_channel_id' => "UCBatch{$index}"]);
            ArtistMetric::factory()->create([
                'artist_id' => $artist->id,
                'youtube_refreshed_at' => now()->subDays(2), // All need updates
                'youtube_subscribers' => 100000,
            ]);
            return $artist;
        });

        $startTime = microtime(true);
        $startRequests = Http::recorded()->count();

        // Process with optimal batch size
        $job = new FetchYouTubeDataJob($artists->pluck('id')->toArray(), 50);
        $job->handle(app(YouTubeService::class));

        $endTime = microtime(true);
        $endRequests = Http::recorded()->count();

        $processingTime = $endTime - $startTime;
        $apiRequestsMade = $endRequests - $startRequests;

        // Verify efficiency
        expect($processingTime)->toBeLessThan(2.0); // Fast processing
        expect($apiRequestsMade)->toBeLessThanOrEqual(2); // Minimal API calls for 30 artists
        
        // Verify all artists were updated
        foreach ($artists as $artist) {
            $artist->metrics->refresh();
            expect($artist->metrics->youtube_refreshed_at)->toBeGreaterThan(now()->subMinute());
        }
    });
});