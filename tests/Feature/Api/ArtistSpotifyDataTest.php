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
            ])
            ->assertJsonPath('data.0.name', 'Test Album');
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
            ->assertJsonPath('message', 'Failed to fetch top tracks');
    }

    public function test_endpoints_require_authentication(): void
    {
        $response = $this->getJson("/api/artists/{$this->artist->id}/top-tracks");
        $response->assertUnauthorized();

        $response = $this->getJson("/api/artists/{$this->artist->id}/albums");
        $response->assertUnauthorized();
    }
}
