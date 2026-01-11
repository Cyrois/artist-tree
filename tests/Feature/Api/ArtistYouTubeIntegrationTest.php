<?php

use App\Models\Artist;
use App\Models\ArtistMetric;
use App\Models\User;

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    
    // Mock YouTube API responses
    Http::fake([
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannelId',
                    'statistics' => [
                        'subscriberCount' => '1000000',
                        'videoCount' => '500',
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

/**
 * Property 12: API Response Structure Consistency
 * For any artist API response, YouTube metrics should be included, with null values when no channel ID exists
 */
it('includes YouTube metrics in artist API response structure', function () {
    $artist = Artist::factory()
        ->has(ArtistMetric::factory([
            'youtube_subscribers' => 1000000,
            'youtube_avg_views' => 150000,
            'youtube_avg_likes' => 7500,
            'youtube_avg_comments' => 750,
            'youtube_videos_analyzed' => 3,
            'youtube_refreshed_at' => now(),
            'youtube_analytics_refreshed_at' => now(),
        ]))
        ->create([
            'youtube_channel_id' => 'UCTestChannelId',
        ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/artists/{$artist->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'metrics' => [
                    'youtube_subscribers',
                    'youtube_avg_views',
                    'youtube_avg_likes',
                    'youtube_avg_comments',
                    'youtube_videos_analyzed',
                    'youtube_refreshed_at',
                    'youtube_analytics_refreshed_at',
                ],
            ],
        ])
        ->assertJsonPath('data.metrics.youtube_subscribers', 1000000)
        ->assertJsonPath('data.metrics.youtube_avg_views', 150000)
        ->assertJsonPath('data.metrics.youtube_avg_likes', 7500)
        ->assertJsonPath('data.metrics.youtube_avg_comments', 750)
        ->assertJsonPath('data.metrics.youtube_videos_analyzed', 3);
});

it('returns null YouTube metrics when no channel ID exists', function () {
    $artist = Artist::factory()
        ->has(ArtistMetric::factory([
            'youtube_subscribers' => null,
            'youtube_avg_views' => null,
            'youtube_avg_likes' => null,
            'youtube_avg_comments' => null,
            'youtube_videos_analyzed' => null,
        ]))
        ->create([
            'youtube_channel_id' => null,
        ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/artists/{$artist->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.metrics.youtube_subscribers', null)
        ->assertJsonPath('data.metrics.youtube_avg_views', null)
        ->assertJsonPath('data.metrics.youtube_avg_likes', null)
        ->assertJsonPath('data.metrics.youtube_avg_comments', null)
        ->assertJsonPath('data.metrics.youtube_videos_analyzed', null);
});

it('automatically refreshes stale YouTube data when showing artist', function () {
    $artist = Artist::factory()
        ->has(ArtistMetric::factory([
            'youtube_subscribers' => 500000, // Old data
            'youtube_refreshed_at' => now()->subDays(2), // Stale
            'youtube_analytics_refreshed_at' => now()->subDays(8), // Stale
        ]))
        ->create([
            'youtube_channel_id' => 'UCTestChannelId',
        ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/artists/{$artist->id}");

    $response->assertStatus(200);

    // Verify YouTube data was refreshed
    $artist->refresh();
    expect($artist->metrics->youtube_subscribers)->toBe(1000000)
        ->and($artist->metrics->youtube_avg_views)->toBe(150000)
        ->and($artist->metrics->youtube_refreshed_at)->toBeGreaterThan(now()->subMinute())
        ->and($artist->metrics->youtube_analytics_refreshed_at)->toBeGreaterThan(now()->subMinute());
});

it('does not refresh YouTube data when not stale', function () {
    $originalRefreshTime = now()->subHours(12);
    $originalAnalyticsRefreshTime = now()->subDays(3);
    
    $artist = Artist::factory()
        ->has(ArtistMetric::factory([
            'youtube_subscribers' => 500000,
            'youtube_refreshed_at' => $originalRefreshTime,
            'youtube_analytics_refreshed_at' => $originalAnalyticsRefreshTime,
        ]))
        ->create([
            'youtube_channel_id' => 'UCTestChannelId',
        ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/artists/{$artist->id}");

    $response->assertStatus(200);

    // Verify YouTube data was NOT refreshed (timestamps unchanged)
    $artist->refresh();
    expect($artist->metrics->youtube_subscribers)->toBe(500000)
        ->and($artist->metrics->youtube_refreshed_at->timestamp)->toBe($originalRefreshTime->timestamp)
        ->and($artist->metrics->youtube_analytics_refreshed_at->timestamp)->toBe($originalAnalyticsRefreshTime->timestamp);
});

/**
 * Property 27: Frontend Refresh Integration
 * For any "Refresh Data" button click, the system should trigger refresh of both basic YouTube metrics and video analytics
 */
it('refreshes both YouTube basic metrics and video analytics on manual refresh', function () {
    $artist = Artist::factory()
        ->has(ArtistMetric::factory([
            'youtube_subscribers' => 500000,
            'youtube_avg_views' => 50000,
            'youtube_refreshed_at' => now()->subDays(1),
            'youtube_analytics_refreshed_at' => now()->subDays(1),
        ]))
        ->create([
            'spotify_id' => 'spotify123',
            'youtube_channel_id' => 'UCTestChannelId',
        ]);

    // Mock Spotify API for the refresh
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_token',
        ], 200),
        'https://api.spotify.com/v1/artists/spotify123' => Http::response([
            'id' => 'spotify123',
            'name' => 'Test Artist',
            'genres' => ['pop'],
            'images' => [['url' => 'https://example.com/image.jpg']],
            'popularity' => 80,
            'followers' => ['total' => 250000],
        ], 200),
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannelId',
                    'statistics' => [
                        'subscriberCount' => '1000000',
                        'videoCount' => '500',
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

    $response = $this->actingAs($this->user)
        ->postJson("/api/artists/{$artist->id}/refresh");

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Artist refreshed successfully');

    // Verify both basic metrics and analytics were refreshed
    $artist->refresh();
    expect($artist->metrics->youtube_subscribers)->toBe(1000000)
        ->and($artist->metrics->youtube_avg_views)->toBe(150000)
        ->and($artist->metrics->youtube_avg_likes)->toBe(7500)
        ->and($artist->metrics->youtube_avg_comments)->toBe(750)
        ->and($artist->metrics->youtube_videos_analyzed)->toBe(3)
        ->and($artist->metrics->youtube_refreshed_at)->toBeGreaterThan(now()->subMinute())
        ->and($artist->metrics->youtube_analytics_refreshed_at)->toBeGreaterThan(now()->subMinute());
});

it('handles YouTube API errors gracefully during refresh', function () {
    $artist = Artist::factory()
        ->has(ArtistMetric::factory())
        ->create([
            'spotify_id' => 'spotify123',
            'youtube_channel_id' => 'UCTestChannelId',
        ]);

    // Mock Spotify success but YouTube failure
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_token',
        ], 200),
        'https://api.spotify.com/v1/artists/spotify123' => Http::response([
            'id' => 'spotify123',
            'name' => 'Test Artist',
            'genres' => ['pop'],
            'images' => [['url' => 'https://example.com/image.jpg']],
            'popularity' => 80,
            'followers' => ['total' => 250000],
        ], 200),
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'error' => ['message' => 'Quota exceeded'],
        ], 403),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/artists/{$artist->id}/refresh");

    // Should still succeed with Spotify refresh, YouTube error logged
    $response->assertStatus(200)
        ->assertJsonPath('message', 'Artist refreshed successfully');
});

it('refreshes YouTube data when selecting artist', function () {
    $artist = Artist::factory()
        ->has(ArtistMetric::factory([
            'youtube_subscribers' => 500000,
            'youtube_refreshed_at' => now()->subDays(2), // Stale
        ]))
        ->create([
            'youtube_channel_id' => 'UCTestChannelId',
        ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/artists/select', [
            'artist_id' => $artist->id,
        ]);

    $response->assertStatus(200);

    // Verify YouTube data was refreshed
    $artist->refresh();
    expect($artist->metrics->youtube_subscribers)->toBe(1000000)
        ->and($artist->metrics->youtube_refreshed_at)->toBeGreaterThan(now()->subMinute());
});

it('skips YouTube refresh when artist has no channel ID', function () {
    $artist = Artist::factory()
        ->has(ArtistMetric::factory())
        ->create([
            'youtube_channel_id' => null,
        ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/artists/{$artist->id}");

    $response->assertStatus(200);

    // Verify no YouTube API calls were made
    Http::assertNothingSent();
});

it('creates metrics record when refreshing artist without existing metrics', function () {
    $artist = Artist::factory()->create([
        'youtube_channel_id' => 'UCTestChannelId',
    ]);

    // Mock Spotify API for the refresh
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_token',
        ], 200),
        'https://api.spotify.com/v1/artists/spotify123' => Http::response([
            'id' => 'spotify123',
            'name' => 'Test Artist',
            'genres' => ['pop'],
            'images' => [['url' => 'https://example.com/image.jpg']],
            'popularity' => 80,
            'followers' => ['total' => 250000],
        ], 200),
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannelId',
                    'statistics' => [
                        'subscriberCount' => '1000000',
                        'videoCount' => '500',
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
            ],
        ], 200),
    ]);

    // Ensure artist has no metrics initially
    expect($artist->metrics)->toBeNull();

    $artist->update(['spotify_id' => 'spotify123']);

    $response = $this->actingAs($this->user)
        ->postJson("/api/artists/{$artist->id}/refresh");

    $response->assertStatus(200);

    // Verify metrics were created with YouTube data
    $artist->refresh();
    expect($artist->metrics)->not->toBeNull()
        ->and($artist->metrics->youtube_subscribers)->toBe(1000000)
        ->and($artist->metrics->youtube_avg_views)->toBe(150000);
});