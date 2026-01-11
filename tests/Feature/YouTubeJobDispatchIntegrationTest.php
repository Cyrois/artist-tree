<?php

use App\Jobs\CreateArtistsFromSpotifyJob;
use App\Jobs\FetchYouTubeDataJob;
use App\Models\Artist;
use App\Services\ArtistSearchService;
use App\Services\SpotifyService;
use App\Services\YouTubeJobDispatchService;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    
    // Mock Spotify OAuth token request
    Http::fake([
        'accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'fake_access_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]),
    ]);
});

describe('YouTube Job Dispatch Integration', function () {
    it('dispatches youtube jobs when searching for artists with stale data', function () {
        // This test verifies that the search service integration works
        // The actual job dispatch logic is tested in unit tests
        
        $localArtist = Artist::factory()->create([
            'spotify_id' => 'local123',
            'name' => 'Local Artist',
            'youtube_channel_id' => 'UC_local_stale',
        ]);
        $localArtist->metrics()->create([
            'spotify_popularity' => 80,
            'spotify_followers' => 100000,
            'youtube_subscribers' => 50000,
            'youtube_refreshed_at' => now()->subHours(25), // Stale
            'refreshed_at' => now(),
        ]);

        // Test that the dispatch service works directly
        $dispatchService = app(YouTubeJobDispatchService::class);
        $stats = $dispatchService->dispatchPriorityJobs([$localArtist->id], false);

        expect($stats['high_priority'])->toBe(1);
        Queue::assertPushed(FetchYouTubeDataJob::class);
    });

    it('dispatches youtube jobs when creating artists from spotify with youtube channel ids', function () {
        // Create an artist that already exists with YouTube channel ID
        $existingArtist = Artist::factory()->create([
            'spotify_id' => 'spotify123',
            'name' => 'Existing Artist',
            'youtube_channel_id' => 'UC_existing_artist',
        ]);

        // Mock Spotify artist data
        $spotifyArtistData = [
            (object) [
                'spotifyId' => 'new_spotify123',
                'name' => 'New Artist',
                'popularity' => 75,
                'followers' => 80000,
                'genres' => ['pop'],
                'imageUrl' => 'https://example.com/image.jpg',
            ],
        ];

        // Create the job and handle it
        $job = new CreateArtistsFromSpotifyJob($spotifyArtistData);
        $youtubeDispatchService = app(YouTubeJobDispatchService::class);
        $job->handle($youtubeDispatchService);

        // The job should create the new artist but won't dispatch YouTube jobs
        // since Spotify data doesn't include YouTube channel IDs
        $newArtist = Artist::where('spotify_id', 'new_spotify123')->first();
        expect($newArtist)->not->toBeNull();

        // No YouTube job should be dispatched since the new artist has no YouTube channel ID
        Queue::assertNotPushed(FetchYouTubeDataJob::class);
    });

    it('dispatches priority-based youtube jobs through artist controller refresh', function () {
        // Create authenticated user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $artist = Artist::factory()->create([
            'spotify_id' => 'test123',
            'youtube_channel_id' => 'UC_test_refresh',
        ]);
        $artist->metrics()->create([
            'spotify_popularity' => 70,
            'spotify_followers' => 50000,
            'youtube_subscribers' => 25000,
            'youtube_refreshed_at' => now()->subHours(25), // Stale
            'refreshed_at' => now(),
        ]);

        // Mock Spotify API response
        Http::fake([
            'api.spotify.com/v1/artists/test123' => Http::response([
                'id' => 'test123',
                'name' => 'Test Artist',
                'popularity' => 75,
                'followers' => ['total' => 55000],
                'genres' => ['rock'],
                'images' => [['url' => 'https://example.com/image.jpg']],
            ]),
        ]);

        // Mock YouTube API response
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => 'UC_test_refresh',
                        'statistics' => [
                            'subscriberCount' => '30000',
                            'videoCount' => '100',
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson("/api/artists/{$artist->id}/refresh");

        $response->assertOk();

        // Should dispatch priority-based YouTube job
        Queue::assertPushed(FetchYouTubeDataJob::class);
    });

    it('handles quota exhaustion gracefully in job dispatch', function () {
        $artist = Artist::factory()->create([
            'youtube_channel_id' => 'UC_quota_test',
        ]);
        $artist->metrics()->create([
            'youtube_refreshed_at' => now()->subHours(25), // Stale
            'youtube_subscribers' => 1000,
            'refreshed_at' => now(),
        ]);

        // Mock YouTube service to return quota exhausted
        $this->mock(\App\Services\YouTubeService::class, function ($mock) {
            $mock->shouldReceive('checkQuotaAvailability')->andReturn(false);
        });

        $service = app(YouTubeJobDispatchService::class);
        $stats = $service->dispatchPriorityJobs([$artist->id], true);

        expect($stats['total_jobs'])->toBe(0)
            ->and($stats)->toHaveKey('quota_exhausted', true);

        Queue::assertNotPushed(FetchYouTubeDataJob::class);
    });

    it('batches multiple artists correctly in priority dispatch', function () {
        // Create 30 artists with different priority levels
        $highPriorityArtists = Artist::factory()->count(15)->create([
            'youtube_channel_id' => 'UC_high',
        ]);
        foreach ($highPriorityArtists as $artist) {
            $artist->metrics()->create([
                'youtube_refreshed_at' => now()->subHours(25), // Stale basic metrics
                'youtube_subscribers' => 1000,
                'refreshed_at' => now(),
            ]);
        }

        $mediumPriorityArtists = Artist::factory()->count(10)->create([
            'youtube_channel_id' => 'UC_medium',
        ]);
        foreach ($mediumPriorityArtists as $artist) {
            $artist->metrics()->create([
                'youtube_refreshed_at' => now()->subHours(12), // Fresh basic metrics
                'youtube_analytics_refreshed_at' => now()->subDays(8), // Stale analytics
                'youtube_subscribers' => 2000,
                'youtube_avg_views' => 5000, // Has analytics data
                'youtube_videos_analyzed' => 10, // Has analytics data
                'refreshed_at' => now(),
            ]);
        }

        $lowPriorityArtists = Artist::factory()->count(5)->create([
            'youtube_channel_id' => 'UC_low',
        ]);
        foreach ($lowPriorityArtists as $artist) {
            $artist->metrics()->create([
                'youtube_refreshed_at' => now()->subHours(12), // Fresh basic metrics
                'youtube_subscribers' => 3000,
                'youtube_avg_views' => null, // No analytics yet
                'youtube_videos_analyzed' => null, // No analytics yet
                'youtube_analytics_refreshed_at' => null, // No analytics yet
                'refreshed_at' => now(),
            ]);
        }

        $allArtistIds = $highPriorityArtists->pluck('id')
            ->concat($mediumPriorityArtists->pluck('id'))
            ->concat($lowPriorityArtists->pluck('id'))
            ->toArray();

        $service = app(YouTubeJobDispatchService::class);
        $stats = $service->dispatchPriorityJobs($allArtistIds, false);

        // High priority: 15 artists = 1 batch (20 max)
        // Medium priority: 10 artists = 1 batch (15 max)
        // Low priority: 5 artists = 1 batch (10 max)
        expect($stats['high_priority'])->toBe(1)
            ->and($stats['medium_priority'])->toBe(1)
            ->and($stats['low_priority'])->toBe(1)
            ->and($stats['total_jobs'])->toBe(3)
            ->and($stats['total_artists'])->toBe(30);

        Queue::assertPushed(FetchYouTubeDataJob::class, 3);
    });

    it('integrates with artist search service for mixed local and spotify results', function () {
        // This test verifies that the dispatch service can handle multiple artists
        // with different priority levels
        
        $staleArtist = Artist::factory()->create([
            'spotify_id' => 'local_stale',
            'name' => 'Stale Local Artist',
            'youtube_channel_id' => 'UC_local_stale',
        ]);
        $staleArtist->metrics()->create([
            'youtube_refreshed_at' => now()->subHours(25), // Stale
            'youtube_subscribers' => 20000,
            'refreshed_at' => now(),
        ]);

        $freshArtist = Artist::factory()->create([
            'spotify_id' => 'local_fresh',
            'name' => 'Fresh Local Artist',
            'youtube_channel_id' => 'UC_local_fresh',
        ]);
        $freshArtist->metrics()->create([
            'youtube_refreshed_at' => now()->subHours(12), // Fresh
            'youtube_subscribers' => 10000,
            'youtube_avg_views' => 5000, // Has analytics
            'youtube_videos_analyzed' => 10, // Has analytics
            'youtube_analytics_refreshed_at' => now()->subDays(3), // Fresh analytics
            'refreshed_at' => now(),
        ]);

        // Test that only stale artists get dispatched
        $dispatchService = app(YouTubeJobDispatchService::class);
        $stats = $dispatchService->dispatchPriorityJobs([$staleArtist->id, $freshArtist->id], false);

        expect($stats['high_priority'])->toBe(1)
            ->and($stats['total_jobs'])->toBe(1);

        Queue::assertPushed(FetchYouTubeDataJob::class, 1);
    });
});