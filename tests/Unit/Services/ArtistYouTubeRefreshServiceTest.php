<?php

use App\Models\Artist;
use App\Models\ArtistMetric;
use App\Services\ArtistYouTubeRefreshService;
use App\Services\YouTubeService;
use App\DataTransferObjects\YouTubeChannelDTO;
use App\DataTransferObjects\YouTubeVideoAnalyticsDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

// No need to explicitly use RefreshDatabase since TestCase handles it

beforeEach(function () {
    // Mock YouTube API responses
    Http::fake([
        'www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannel',
                    'statistics' => [
                        'subscriberCount' => '1500000',
                        'videoCount' => '250'
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUTestChannel'
                        ]
                    ]
                ]
            ]
        ]),
        'www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
            'items' => [
                ['contentDetails' => ['videoId' => 'video1']],
                ['contentDetails' => ['videoId' => 'video2']],
                ['contentDetails' => ['videoId' => 'video3']]
            ]
        ]),
        'www.googleapis.com/youtube/v3/videos*' => Http::response([
            'items' => [
                [
                    'id' => 'video1',
                    'statistics' => [
                        'viewCount' => '100000',
                        'likeCount' => '5000',
                        'commentCount' => '250'
                    ]
                ],
                [
                    'id' => 'video2',
                    'statistics' => [
                        'viewCount' => '200000',
                        'likeCount' => '10000',
                        'commentCount' => '500'
                    ]
                ],
                [
                    'id' => 'video3',
                    'statistics' => [
                        'viewCount' => '150000',
                        'likeCount' => '7500',
                        'commentCount' => '375'
                    ]
                ]
            ]
        ])
    ]);
});

describe('ArtistYouTubeRefreshService', function () {
    it('force refreshes YouTube data for artist with channel ID', function () {
        $artist = Artist::factory()->create([
            'youtube_channel_id' => 'UCTestChannel'
        ]);

        $service = app(ArtistYouTubeRefreshService::class);
        $result = $service->forceRefresh($artist);

        expect($result)->toBeTrue();

        $artist->refresh();
        expect($artist->metrics)->not->toBeNull()
            ->and($artist->metrics->youtube_subscribers)->toBe(1500000)
            ->and($artist->metrics->youtube_refreshed_at)->not->toBeNull()
            ->and($artist->metrics->youtube_avg_views)->toBe(150000) // Average of 100k, 200k, 150k
            ->and($artist->metrics->youtube_avg_likes)->toBe(7500)
            ->and($artist->metrics->youtube_avg_comments)->toBe(375)
            ->and($artist->metrics->youtube_videos_analyzed)->toBe(3)
            ->and($artist->metrics->youtube_analytics_refreshed_at)->not->toBeNull();
    });

    it('skips refresh for artist without YouTube channel ID', function () {
        $artist = Artist::factory()->create([
            'youtube_channel_id' => null
        ]);

        $service = app(ArtistYouTubeRefreshService::class);
        $result = $service->forceRefresh($artist);

        expect($result)->toBeFalse();
        expect($artist->metrics)->toBeNull();
    });

    it('refreshes only when needed based on staleness', function () {
        $artist = Artist::factory()->create([
            'youtube_channel_id' => 'UCTestChannel'
        ]);

        // Create fresh metrics AND an approved YouTube link to prevent job dispatch
        ArtistMetric::factory()->create([
            'artist_id' => $artist->id,
            'youtube_refreshed_at' => now(),
            'youtube_analytics_refreshed_at' => now(),
            'youtube_subscribers' => 1000000,
        ]);
        
        // Add approved link so needsToUpdateYoutubeChannel() returns false
        $artist->links()->create([
            'platform' => \App\Enums\SocialPlatform::YouTube,
            'url' => 'https://youtube.com/c/UCTestChannel',
            'review_status' => \App\Models\ArtistLink::REVIEW_STATUS_APPROVED,
        ]);

        $service = app(ArtistYouTubeRefreshService::class);
        
        // Should not refresh fresh data
        $result = $service->refreshIfNeeded($artist);
        expect($result)->toBeFalse();

        // Make data stale
        $artist->metrics->update([
            'youtube_refreshed_at' => now()->subDays(2),
            'youtube_analytics_refreshed_at' => now()->subDays(8),
        ]);

        // Should refresh stale data
        $result = $service->refreshIfNeeded($artist);
        expect($result)->toBeTrue();

        $artist->refresh();
        expect($artist->metrics->youtube_subscribers)->toBe(1500000); // Updated
    });

    it('refreshes basic metrics only', function () {
        $artist = Artist::factory()->create([
            'youtube_channel_id' => 'UCTestChannel'
        ]);

        $service = app(ArtistYouTubeRefreshService::class);
        $result = $service->refreshBasicMetrics($artist);

        expect($result)->toBeTrue();

        $artist->refresh();
        expect($artist->metrics)->not->toBeNull()
            ->and($artist->metrics->youtube_subscribers)->toBe(1500000)
            ->and($artist->metrics->youtube_refreshed_at)->not->toBeNull()
            ->and($artist->metrics->youtube_avg_views)->toBeNull() // Analytics not refreshed
            ->and($artist->metrics->youtube_analytics_refreshed_at)->toBeNull();
    });

    it('refreshes analytics only', function () {
        $artist = Artist::factory()->create([
            'youtube_channel_id' => 'UCTestChannel'
        ]);

        // Create existing metrics without analytics
        ArtistMetric::factory()->create([
            'artist_id' => $artist->id,
            'youtube_subscribers' => 1000000,
            'youtube_refreshed_at' => now(),
        ]);

        $service = app(ArtistYouTubeRefreshService::class);
        $result = $service->refreshAnalytics($artist);

        expect($result)->toBeTrue();

        $artist->refresh();
        expect($artist->metrics->youtube_subscribers)->toBe(1000000) // Unchanged
            ->and($artist->metrics->youtube_avg_views)->toBe(150000) // Analytics added
            ->and($artist->metrics->youtube_analytics_refreshed_at)->not->toBeNull();
    });

    it('correctly identifies when refresh is needed', function () {
        $artist = Artist::factory()->create([
            'youtube_channel_id' => 'UCTestChannel'
        ]);

        $service = app(ArtistYouTubeRefreshService::class);

        // Artist without metrics needs refresh
        expect($service->needsRefresh($artist))->toBeTrue();

        // Create fresh metrics with complete data
        $artist->metrics()->create([
            'youtube_refreshed_at' => now(),
            'youtube_analytics_refreshed_at' => now(),
            'youtube_subscribers' => 1000000,
            'youtube_avg_views' => 100000,
            'youtube_videos_analyzed' => 10,
            'refreshed_at' => now(),
        ]);

        $artist->refresh();

        // Fresh metrics don't need refresh
        expect($service->needsRefresh($artist))->toBeFalse();

        // Make basic metrics stale
        $artist->metrics->update(['youtube_refreshed_at' => now()->subDays(2)]);
        expect($service->needsRefresh($artist))->toBeTrue();

        // Reset and make analytics stale
        $artist->metrics->update([
            'youtube_refreshed_at' => now(),
            'youtube_analytics_refreshed_at' => now()->subDays(8)
        ]);
        expect($service->needsRefresh($artist))->toBeTrue();
    });

    it('handles artists without YouTube channel ID gracefully', function () {
        // Fake the queue to prevent actual job dispatch
        Queue::fake();
        
        $artist = Artist::factory()->create([
            'youtube_channel_id' => null
        ]);

        $service = app(ArtistYouTubeRefreshService::class);

        // needsRefresh returns false since there's no channel to refresh
        // forceRefresh returns false since there's no channel
        // refreshIfNeeded returns true because it dispatches UpdateYoutubeLinksJob for channel discovery
        // refreshBasicMetrics and refreshAnalytics return false since there's no channel
        expect($service->needsRefresh($artist))->toBeFalse()
            ->and($service->forceRefresh($artist))->toBeFalse()
            ->and($service->refreshIfNeeded($artist))->toBeTrue() // Now dispatches job for channel discovery
            ->and($service->refreshBasicMetrics($artist))->toBeFalse()
            ->and($service->refreshAnalytics($artist))->toBeFalse();
        
        // Verify job was dispatched for channel discovery
        Queue::assertPushed(\App\Jobs\UpdateYoutubeLinksJob::class);
    });

    it('refreshes YouTube data for artist with channel ID but no metrics', function () {
        // Fake the queue to prevent actual job dispatch
        Queue::fake();
        
        // This reproduces the bug: artist has youtube_channel_id but no metrics record
        $artist = Artist::factory()->create([
            'name' => 'Test Artist',
            'youtube_channel_id' => 'UCTestChannel',
        ]);
        
        // Add approved link so needsToUpdateYoutubeChannel() returns false
        // This ensures forceRefresh is called instead of dispatching the job
        $artist->links()->create([
            'platform' => \App\Enums\SocialPlatform::YouTube,
            'url' => 'https://youtube.com/c/UCTestChannel',
            'review_status' => \App\Models\ArtistLink::REVIEW_STATUS_APPROVED,
        ]);

        // Ensure no metrics exist
        expect($artist->metrics)->toBeNull();

        $service = app(ArtistYouTubeRefreshService::class);
        
        // needsRefresh should return true for artist with channel but no metrics
        expect($service->needsRefresh($artist))->toBeTrue();
        
        // refreshIfNeeded should actually refresh the data (via forceRefresh)
        $result = $service->refreshIfNeeded($artist);
        expect($result)->toBeTrue();

        // Verify metrics were created with YouTube data
        $artist->refresh();
        expect($artist->metrics)->not->toBeNull()
            ->and($artist->metrics->youtube_subscribers)->toBe(1500000)
            ->and($artist->metrics->youtube_refreshed_at)->not->toBeNull()
            ->and($artist->metrics->youtube_avg_views)->toBe(150000)
            ->and($artist->metrics->youtube_analytics_refreshed_at)->not->toBeNull();
    });
});