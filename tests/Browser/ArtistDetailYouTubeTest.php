<?php

use App\Models\Artist;
use App\Models\ArtistMetric;
use App\Models\User;

use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->withoutTwoFactor()->create();
    $this->artist = Artist::factory()->create([
        'name' => 'Test Artist',
        'spotify_id' => 'test_spotify_id_' . uniqid(),
        'youtube_channel_id' => 'UCTestChannelId',
    ]);

    // Create metrics with YouTube data
    ArtistMetric::factory()->create([
        'artist_id' => $this->artist->id,
        'spotify_followers' => 1000000,
        'spotify_popularity' => 85,
        'youtube_subscribers' => 500000,
        'youtube_avg_views' => 100000,
        'youtube_avg_likes' => 5000,
        'youtube_avg_comments' => 250,
        'youtube_videos_analyzed' => 15,
        'instagram_followers' => 750000,
        'refreshed_at' => now(),
        'youtube_refreshed_at' => now(),
        'youtube_analytics_refreshed_at' => now(),
    ]);

    actingAs($this->user);
});

/**
 * Test YouTube metrics display in Data & Metrics tab.
 */
it('displays YouTube metrics in data tab', function () {
    $page = visit("/artist/{$this->artist->id}");

    $page->assertNoJavascriptErrors()
        ->waitForText('Test Artist')
        ->click('Data & Metrics')
        ->waitForText('YouTube Video Analytics')
        ->assertSee('500K') // YouTube subscribers
        ->assertSee('100K') // Average views
        ->assertSee('5.0K') // Average likes
        ->assertSee('250') // Average comments
        ->assertSee('15'); // Videos analyzed
})->group('youtube', 'artist-detail');

/**
 * Test null value handling for YouTube metrics.
 */
it('displays N/A for null YouTube metrics', function () {
    // Create artist without YouTube data
    $artistWithoutYouTube = Artist::factory()->create([
        'name' => 'No YouTube Artist',
        'spotify_id' => 'no_youtube_spotify_id_' . uniqid(),
        'youtube_channel_id' => null,
    ]);

    ArtistMetric::factory()->create([
        'artist_id' => $artistWithoutYouTube->id,
        'spotify_followers' => 1000000,
        'spotify_popularity' => 85,
        'youtube_subscribers' => null,
        'youtube_avg_views' => null,
        'youtube_avg_likes' => null,
        'youtube_avg_comments' => null,
        'youtube_videos_analyzed' => null,
        'refreshed_at' => now(),
    ]);

    $page = visit("/artist/{$artistWithoutYouTube->id}");

    $page->assertNoJavascriptErrors()
        ->waitForText('No YouTube Artist')
        ->click('Data & Metrics')
        ->assertSee('N/A') // Should show N/A for null YouTube subscribers
        ->assertDontSee('YouTube Video Analytics'); // Section should not appear
})->group('youtube', 'artist-detail');

/**
 * Test partial YouTube data handling.
 */
it('handles partial YouTube data correctly', function () {
    // Create artist with basic YouTube data but no analytics
    $artistPartialYouTube = Artist::factory()->create([
        'name' => 'Partial YouTube Artist',
        'spotify_id' => 'partial_youtube_spotify_id_' . uniqid(),
        'youtube_channel_id' => 'UCPartialChannelId',
    ]);

    ArtistMetric::factory()->create([
        'artist_id' => $artistPartialYouTube->id,
        'spotify_followers' => 1000000,
        'spotify_popularity' => 85,
        'youtube_subscribers' => 250000,
        'youtube_avg_views' => null,
        'youtube_avg_likes' => null,
        'youtube_avg_comments' => null,
        'youtube_videos_analyzed' => null,
        'refreshed_at' => now(),
        'youtube_refreshed_at' => now(),
    ]);

    $page = visit("/artist/{$artistPartialYouTube->id}");

    $page->assertNoJavascriptErrors()
        ->waitForText('Partial YouTube Artist')
        ->click('Data & Metrics')
        ->assertSee('250K') // YouTube subscribers should show
        ->assertSee('YouTube Video Analytics') // Section should appear
        ->assertSee('N/A'); // Analytics should show N/A
})->group('youtube', 'artist-detail');

/**
 * Test refresh timestamps display.
 */
it('displays refresh timestamps correctly', function () {
    $page = visit("/artist/{$this->artist->id}");

    $page->assertNoJavascriptErrors()
        ->waitForText('Test Artist')
        ->click('Data & Metrics')
        ->waitForText('YouTube Video Analytics')
        ->assertSee('Spotify Data')
        ->assertSee('YouTube Basic')
        ->assertSee('YouTube Analytics');
})->group('youtube', 'artist-detail');

/**
 * Test refresh button functionality.
 */
it('refresh button triggers YouTube data refresh', function () {
    // Mock the YouTube API responses
    Http::fake([
        'www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannelId',
                    'statistics' => [
                        'subscriberCount' => '600000',
                        'videoCount' => '100',
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUTestChannelId',
                        ],
                    ],
                ],
            ],
        ]),
        'www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
            'items' => [
                ['contentDetails' => ['videoId' => 'video1']],
                ['contentDetails' => ['videoId' => 'video2']],
            ],
        ]),
        'www.googleapis.com/youtube/v3/videos*' => Http::response([
            'items' => [
                [
                    'id' => 'video1',
                    'statistics' => [
                        'viewCount' => '150000',
                        'likeCount' => '7500',
                        'commentCount' => '300',
                    ],
                ],
                [
                    'id' => 'video2',
                    'statistics' => [
                        'viewCount' => '120000',
                        'likeCount' => '6000',
                        'commentCount' => '200',
                    ],
                ],
            ],
        ]),
        'accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'fake_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]),
        'api.spotify.com/v1/artists/*' => Http::response([
            'id' => 'test_spotify_id',
            'name' => 'Test Artist',
            'followers' => ['total' => 1100000],
            'popularity' => 87,
            'genres' => ['pop', 'rock'],
            'images' => [
                ['url' => 'https://example.com/image.jpg', 'height' => 640, 'width' => 640],
            ],
        ]),
    ]);

    $page = visit("/artist/{$this->artist->id}");

    $page->assertNoJavascriptErrors()
        ->waitForText('Test Artist')
        ->click('Data & Metrics')
        ->waitForText('YouTube Video Analytics')
        ->click('Refresh Data')
        ->waitForText('Refreshing...')
        ->waitUntilMissing('Refreshing...', 10)
        ->assertSee('600K'); // Updated subscriber count
})->group('youtube', 'artist-detail');

/**
 * Test YouTube metrics in overview tab.
 */
it('displays YouTube metrics in overview tab', function () {
    $page = visit("/artist/{$this->artist->id}");

    $page->assertNoJavascriptErrors()
        ->waitForText('Test Artist')
        ->assertSee('500K') // YouTube subscribers in quick metrics
        ->assertSee('1.0M') // Spotify followers
        ->assertSee('750K'); // Instagram followers
})->group('youtube', 'artist-detail');

/**
 * Test error handling when refresh fails.
 */
it('handles refresh errors gracefully', function () {
    // Mock API failure
    Http::fake([
        'accounts.spotify.com/api/token' => Http::response([], 500),
        'www.googleapis.com/youtube/v3/channels*' => Http::response([], 500),
    ]);

    $page = visit("/artist/{$this->artist->id}");

    $page->assertNoJavascriptErrors()
        ->waitForText('Test Artist')
        ->click('Data & Metrics')
        ->waitForText('YouTube Video Analytics')
        ->click('Refresh Data')
        ->waitForText('Refreshing...')
        ->waitUntilMissing('Refreshing...', 10)
        // Should still show original data after failed refresh
        ->assertSee('500K'); // Original YouTube subscribers
})->group('youtube', 'artist-detail');