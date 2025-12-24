<?php

namespace Tests\Feature\Api;

use App\Models\Artist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArtistSpotifyDataTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Artist $artist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->artist = Artist::factory()->create([
            'name' => 'Test Artist',
            'spotify_id' => 'test_spotify_id',
        ]);
    }

    public function test_top_tracks_endpoint_returns_tracks(): void
    {
        // Mock Spotify OAuth token
        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'mock_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'https://api.spotify.com/v1/artists/test_spotify_id/top-tracks*' => Http::response([
                'tracks' => [
                    [
                        'id' => 'track1',
                        'name' => 'Song 1',
                        'album' => [
                            'name' => 'Album 1',
                            'images' => [['url' => 'https://example.com/image1.jpg']],
                        ],
                        'duration_ms' => 180000,
                        'preview_url' => 'https://example.com/preview1.mp3',
                        'external_urls' => ['spotify' => 'https://open.spotify.com/track/track1'],
                        'artists' => [
                            ['name' => 'Test Artist', 'id' => 'test_spotify_id'],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$this->artist->id}/top-tracks");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'spotify_id',
                        'name',
                        'album_name',
                        'album_image_url',
                        'duration_ms',
                        'preview_url',
                        'external_url',
                        'artists',
                    ],
                ],
            ])
            ->assertJsonPath('data.0.name', 'Song 1');
    }

    public function test_albums_endpoint_returns_albums(): void
    {
        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'mock_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'https://api.spotify.com/v1/artists/test_spotify_id/albums*' => Http::response([
                'items' => [
                    [
                        'id' => 'album1',
                        'name' => 'Test Album',
                        'album_type' => 'album',
                        'release_date' => '2024-01-01',
                        'total_tracks' => 12,
                        'images' => [['url' => 'https://example.com/album1.jpg']],
                        'external_urls' => ['spotify' => 'https://open.spotify.com/album/album1'],
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$this->artist->id}/albums");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'spotify_id',
                        'name',
                        'album_type',
                        'release_date',
                        'total_tracks',
                        'image_url',
                        'external_url',
                    ],
                ],
                'meta' => [
                    'limit',
                    'max_limit',
                    'has_more',
                ],
            ])
            ->assertJsonPath('data.0.name', 'Test Album')
            ->assertJsonPath('meta.limit', 5)
            ->assertJsonPath('meta.max_limit', 20);
    }

    public function test_albums_endpoint_respects_limit_parameter(): void
    {
        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'mock_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'https://api.spotify.com/v1/artists/test_spotify_id/albums*' => Http::response([
                'items' => array_map(fn ($i) => [
                    'id' => "album{$i}",
                    'name' => "Album {$i}",
                    'album_type' => 'album',
                    'release_date' => '2024-01-01',
                    'total_tracks' => 10,
                    'images' => [['url' => 'https://example.com/album.jpg']],
                    'external_urls' => ['spotify' => "https://open.spotify.com/album/album{$i}"],
                ], range(1, 10)),
            ]),
        ]);

        // Test with limit=10
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$this->artist->id}/albums?limit=10");

        $response->assertOk()
            ->assertJsonPath('meta.limit', 10)
            ->assertJsonPath('meta.has_more', true);

        // Test max limit is capped at 20
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$this->artist->id}/albums?limit=100");

        $response->assertOk()
            ->assertJsonPath('meta.limit', 20);
    }

    public function test_endpoints_resolve_missing_spotify_id(): void
    {
        // Artist without spotify_id
        $artistWithoutSpotifyId = Artist::factory()->create([
            'name' => 'Artist Without ID',
            'spotify_id' => null,
        ]);

        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'mock_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            // Mock search for exact name match
            'https://api.spotify.com/v1/search*' => Http::response([
                'artists' => [
                    'items' => [
                        [
                            'id' => 'resolved_spotify_id',
                            'name' => 'Artist Without ID', // Exact match
                            'genres' => ['rock'],
                            'images' => [['url' => 'https://example.com/artist.jpg']],
                            'followers' => ['total' => 10000],
                            'popularity' => 50,
                            'external_urls' => ['spotify' => 'https://open.spotify.com/artist/resolved_spotify_id'],
                        ],
                    ],
                ],
            ]),
            'https://api.spotify.com/v1/artists/resolved_spotify_id/top-tracks*' => Http::response([
                'tracks' => [
                    [
                        'id' => 'track1',
                        'name' => 'Resolved Track',
                        'album' => [
                            'name' => 'Resolved Album',
                            'images' => [['url' => 'https://example.com/album.jpg']],
                        ],
                        'duration_ms' => 200000,
                        'preview_url' => 'https://example.com/preview.mp3',
                        'external_urls' => ['spotify' => 'https://open.spotify.com/track/track1'],
                        'artists' => [
                            ['name' => 'Artist Without ID', 'id' => 'resolved_spotify_id'],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artistWithoutSpotifyId->id}/top-tracks");

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Resolved Track');

        // Verify spotify_id was persisted
        $artistWithoutSpotifyId->refresh();
        $this->assertEquals('resolved_spotify_id', $artistWithoutSpotifyId->spotify_id);
    }

    public function test_endpoints_return_empty_data_when_artist_has_no_spotify_id(): void
    {
        $artistWithoutSpotifyId = Artist::factory()->create([
            'name' => 'Unknown Artist',
            'spotify_id' => null,
        ]);

        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'mock_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            // Mock search with no results
            'https://api.spotify.com/v1/search*' => Http::response([
                'artists' => ['items' => []],
            ]),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artistWithoutSpotifyId->id}/top-tracks");

        $response->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('message', 'Artist does not have a Spotify ID');
    }

    public function test_endpoints_gracefully_handle_spotify_api_errors(): void
    {
        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'mock_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            // Simulate server error - all retry attempts will fail
            'https://api.spotify.com/v1/artists/test_spotify_id/top-tracks*' => Http::sequence()
                ->push([], 500)
                ->push([], 500)
                ->push([], 500)
                ->push([], 500),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$this->artist->id}/top-tracks");

        $response->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('message', 'An unexpected error occurred. Please try again later.');
    }

    public function test_endpoints_require_authentication(): void
    {
        $response = $this->getJson("/api/artists/{$this->artist->id}/top-tracks");
        $response->assertUnauthorized();

        $response = $this->getJson("/api/artists/{$this->artist->id}/albums");
        $response->assertUnauthorized();
    }

    public function test_resolve_spotify_id_caches_negative_results(): void
    {
        $artistWithoutSpotifyId = Artist::factory()->create([
            'name' => 'Unknown Artist',
            'spotify_id' => null,
        ]);

        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'mock_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            // Mock search with no results
            'https://api.spotify.com/v1/search*' => Http::response([
                'artists' => ['items' => []],
            ]),
        ]);

        // First request - should hit Spotify API
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artistWithoutSpotifyId->id}/top-tracks");

        $response->assertOk()
            ->assertJsonPath('data', []);

        // Verify API was called once
        Http::assertSentCount(2); // 1 token + 1 search

        // Second request - should use cached negative result and NOT call Spotify
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artistWithoutSpotifyId->id}/top-tracks");

        $response->assertOk()
            ->assertJsonPath('data', []);

        // Verify no additional API calls were made
        Http::assertSentCount(2); // Still just 1 token + 1 search (cached)
    }

    public function test_albums_limit_parameter_validation(): void
    {
        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'mock_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'https://api.spotify.com/v1/artists/test_spotify_id/albums*' => Http::response([
                'items' => [],
            ]),
        ]);

        // Test minimum limit (should fail validation)
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$this->artist->id}/albums?limit=0");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);

        // Test negative limit (should fail validation)
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$this->artist->id}/albums?limit=-5");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);

        // Test non-numeric limit (should fail validation)
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$this->artist->id}/albums?limit=abc");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);

        // Test exceeding max limit (should be capped at 20)
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$this->artist->id}/albums?limit=999");

        $response->assertOk()
            ->assertJsonPath('meta.limit', 20);

        // Test valid limit within range
        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$this->artist->id}/albums?limit=10");

        $response->assertOk()
            ->assertJsonPath('meta.limit', 10);
    }

    public function test_error_responses_do_not_expose_raw_exceptions(): void
    {
        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'mock_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            // Simulate API error with detailed error message
            'https://api.spotify.com/v1/artists/test_spotify_id/top-tracks*' => Http::sequence()
                ->push(['error' => ['status' => 500, 'message' => 'Internal database connection failed at 192.168.1.100:5432']], 500)
                ->push(['error' => ['status' => 500, 'message' => 'Internal database connection failed at 192.168.1.100:5432']], 500)
                ->push(['error' => ['status' => 500, 'message' => 'Internal database connection failed at 192.168.1.100:5432']], 500)
                ->push(['error' => ['status' => 500, 'message' => 'Internal database connection failed at 192.168.1.100:5432']], 500),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$this->artist->id}/top-tracks");

        $response->assertOk()
            ->assertJsonPath('data', []);

        // Verify the response does NOT contain raw error details
        $responseData = $response->json();
        $this->assertStringNotContainsString('192.168.1.100', json_encode($responseData));
        $this->assertStringNotContainsString('database connection', json_encode($responseData));

        // Verify generic user-friendly message is returned instead
        $this->assertStringContainsString('An unexpected error occurred', $responseData['message']);
    }
}
