<?php

use App\Models\Artist;
use App\Models\ArtistMetric;
use App\Models\User;

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('requires authentication to search artists', function () {
    $response = $this->getJson('/api/artists/search?q=test');

    $response->assertStatus(401);
});

it('validates search query is required', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/artists/search');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['q']);
});

it('validates search query minimum length', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/artists/search?q=a');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['q']);
});

it('searches artists successfully with local results', function () {
    // Create test artists
    Artist::factory()
        ->has(ArtistMetric::factory())
        ->withGenres(['rock', 'indie'])
        ->create([
            'name' => 'Arctic Monkeys',
            'spotify_id' => 'arctic123',
        ]);

    // Mock Spotify to return empty
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_token',
        ], 200),
        'https://api.spotify.com/*' => Http::response([
            'artists' => ['items' => []],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/artists/search?q=Arctic');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'genres',
                    'image_url',
                    'exists_in_database',
                    'source',
                ],
            ],
        ])
        ->assertJsonPath('data.0.name', 'Arctic Monkeys')
        ->assertJsonPath('data.0.source', 'local')
        ->assertJsonPath('data.0.exists_in_database', true);
});

it('searches artists with Spotify results', function () {
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_token',
        ], 200),
        'https://api.spotify.com/v1/search*' => Http::response([
            'artists' => [
                'items' => [
                    [
                        'id' => 'spotify123',
                        'name' => 'Spotify Artist',
                        'genres' => ['electronic'],
                        'images' => [['url' => 'https://example.com/image.jpg']],
                        'popularity' => 75,
                        'followers' => ['total' => 100000],
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/artists/search?q=Spotify');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.name', 'Spotify Artist')
        ->assertJsonPath('data.0.source', 'spotify')
        ->assertJsonPath('data.0.exists_in_database', false);
});

it('respects custom limit parameter', function () {
    // Create artists with searchable names
    for ($i = 1; $i <= 10; $i++) {
        Artist::factory()
            ->has(ArtistMetric::factory())
            ->create(['name' => "Test Artist {$i}"]);
    }

    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response(['access_token' => 'test'], 200),
        'https://api.spotify.com/*' => Http::response(['artists' => ['items' => []]], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/artists/search?q=Test&limit=5');

    $response->assertStatus(200)
        ->assertJsonCount(5, 'data');
});

it('enforces rate limiting on search endpoint', function () {
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response(['access_token' => 'test'], 200),
        'https://api.spotify.com/*' => Http::response(['artists' => ['items' => []]], 200),
    ]);

    // Make requests up to the limit (60 per minute for authenticated users)
    for ($i = 0; $i < 61; $i++) {
        $response = $this->actingAs($this->user)
            ->getJson('/api/artists/search?q=test'.$i);

        if ($i < 60) {
            $response->assertStatus(200);
        } else {
            $response->assertStatus(429); // Too many requests
        }
    }
});

it('selects artist from database and refreshes Spotify data', function () {
    // Create an artist with stale data
    $artist = Artist::factory()
        ->has(ArtistMetric::factory()->stale())
        ->create([
            'spotify_id' => 'new123',
            'name' => 'Old Name',
        ]);

    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_token',
        ], 200),
        'https://api.spotify.com/v1/artists/new123' => Http::response([
            'id' => 'new123',
            'name' => 'New Artist',
            'genres' => ['pop'],
            'images' => [['url' => 'https://example.com/new.jpg']],
            'popularity' => 80,
            'followers' => ['total' => 250000],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/artists/select', [
            'artist_id' => $artist->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Artist selected successfully')
        ->assertJsonPath('data.id', $artist->id)
        ->assertJsonPath('data.name', 'New Artist')
        ->assertJsonPath('data.metrics.spotify_popularity', 80)
        ->assertJsonPath('data.metrics.spotify_followers', 250000);

    $this->assertDatabaseHas('artists', [
        'id' => $artist->id,
        'spotify_id' => 'new123',
        'name' => 'New Artist',
    ]);

    $this->assertDatabaseHas('artist_metrics', [
        'artist_id' => $artist->id,
        'spotify_popularity' => 80,
        'spotify_followers' => 250000,
    ]);
});

it('selects existing artist without refreshing when no Spotify ID', function () {
    $existingArtist = Artist::factory()
        ->has(ArtistMetric::factory()->fresh())
        ->create(['spotify_id' => null]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/artists/select', [
            'artist_id' => $existingArtist->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $existingArtist->id);

    // Should still only have 1 artist
    expect(Artist::count())->toBe(1);
});

it('validates artist_id is required when selecting artist', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/artists/select', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['artist_id']);
});

it('handles Spotify API errors gracefully when selecting artist', function () {
    $artist = Artist::factory()
        ->has(ArtistMetric::factory()->stale())
        ->create(['spotify_id' => 'bad123']);

    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response(['access_token' => 'test'], 200),
        'https://api.spotify.com/v1/artists/bad123' => Http::response([
            'error' => ['message' => 'Artist not found'],
        ], 404),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/artists/select', [
            'artist_id' => $artist->id,
        ]);

    // Should return 200 with empty data due to graceful degradation
    $response->assertStatus(200)
        ->assertJsonStructure(['message', 'data'])
        ->assertJsonPath('message', 'An unexpected error occurred. Please try again later.');
});

it('refreshes artist data from Spotify', function () {
    $artist = Artist::factory()
        ->has(ArtistMetric::factory()->stale())
        ->create([
            'spotify_id' => 'refresh123',
            'name' => 'Old Name',
        ]);

    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response(['access_token' => 'test'], 200),
        'https://api.spotify.com/v1/artists/refresh123' => Http::response([
            'id' => 'refresh123',
            'name' => 'Updated Name',
            'genres' => ['indie', 'rock'],
            'images' => [['url' => 'https://example.com/updated.jpg']],
            'popularity' => 85,
            'followers' => ['total' => 500000],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/artists/{$artist->id}/refresh");

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Artist refreshed successfully')
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.metrics.spotify_popularity', 85);

    $artist->refresh();
    expect($artist->name)->toBe('Updated Name')
        ->and($artist->metrics->spotify_followers)->toBe(500000);
});

it('returns error when refreshing artist without Spotify ID', function () {
    $artist = Artist::factory()->create(['spotify_id' => null]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/artists/{$artist->id}/refresh");

    // Graceful degradation - returns 200 with empty data and user-friendly message
    $response->assertStatus(200)
        ->assertJsonStructure(['message', 'data'])
        ->assertJsonPath('data', [])
        ->assertJsonPath('message', 'Artist does not have a Spotify ID');
});

it('returns 404 when refreshing non-existent artist', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/artists/99999/refresh');

    $response->assertStatus(404);
});

// New tests for spotify_id in search response and artist detail endpoints

it('includes spotify_id in search response', function () {
    Artist::factory()
        ->has(ArtistMetric::factory())
        ->withGenres(['rock'])
        ->create([
            'name' => 'Test Artist',
            'spotify_id' => 'test_spotify_123',
        ]);

    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ]),
        'https://api.spotify.com/v1/search*' => Http::response([
            'artists' => ['items' => []],
        ]),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/artists/search?q=test');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'spotify_id',
                    'name',
                    'genres',
                    'image_url',
                    'exists_in_database',
                    'source',
                ],
            ],
        ])
        ->assertJsonPath('data.0.spotify_id', 'test_spotify_123');
});

it('gets artist by database ID', function () {
    $artist = Artist::factory()
        ->has(ArtistMetric::factory([
            'spotify_popularity' => 85,
            'spotify_followers' => 100000,
        ]))
        ->withGenres(['rock', 'indie'])
        ->create([
            'name' => 'Test Artist',
            'spotify_id' => 'spotify123',
        ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/artists/{$artist->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'spotify_id',
                'name',
                'genres',
                'image_url',
                'metrics',
                'created_at',
                'updated_at',
            ],
        ])
        ->assertJsonPath('data.id', $artist->id)
        ->assertJsonPath('data.spotify_id', 'spotify123')
        ->assertJsonPath('data.name', 'Test Artist')
        ->assertJsonPath('data.genres', ['rock', 'indie'])
        ->assertJsonPath('data.metrics.spotify_popularity', 85)
        ->assertJsonPath('data.metrics.spotify_followers', 100000);
});

it('gets artist by Spotify ID', function () {
    $artist = Artist::factory()
        ->has(ArtistMetric::factory([
            'spotify_popularity' => 90,
            'spotify_followers' => 200000,
        ]))
        ->withGenres(['pop'])
        ->create([
            'name' => 'Another Artist',
            'spotify_id' => 'spotify456',
        ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/artists?spotify_id=spotify456');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'spotify_id',
                'name',
                'genres',
                'image_url',
                'metrics',
                'created_at',
                'updated_at',
            ],
        ])
        ->assertJsonPath('data.id', $artist->id)
        ->assertJsonPath('data.spotify_id', 'spotify456')
        ->assertJsonPath('data.name', 'Another Artist')
        ->assertJsonPath('data.metrics.spotify_popularity', 90);
});

it('returns 404 when artist not found by database ID', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/artists/99999');

    $response->assertStatus(404)
        ->assertJsonPath('message', 'Artist not found with ID: 99999');
});

it('returns 404 when artist not found by Spotify ID', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/artists?spotify_id=nonexistent');

    $response->assertStatus(404)
        ->assertJsonPath('message', 'Artist not found with Spotify ID: nonexistent');
});

it('requires authentication to get artist by ID', function () {
    $artist = Artist::factory()->create();

    $response = $this->getJson("/api/artists/{$artist->id}");

    $response->assertStatus(401);
});

it('requires authentication to get artist by Spotify ID', function () {
    $response = $this->getJson('/api/artists?spotify_id=test123');

    $response->assertStatus(401);
});

it('returns 422 validation error when neither ID nor spotify_id provided', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/artists');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['spotify_id']);
});

it('validates spotify_id must be a string', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/artists?spotify_id[]=array');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['spotify_id']);
});

it('validates spotify_id must not exceed max length', function () {
    $longSpotifyId = str_repeat('a', 256);

    $response = $this->actingAs($this->user)
        ->getJson('/api/artists?spotify_id='.$longSpotifyId);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['spotify_id']);
});

it('requires authentication to refresh artist', function () {
    $response = $this->postJson('/api/artists/1/refresh');

    $response->assertStatus(401);
});

it('handles name normalization like The prefix in hybrid search results', function () {
    // Mock Spotify to return "The Weeknd"
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response(['access_token' => 'test'], 200),
        'https://api.spotify.com/v1/search*' => Http::response([
            'artists' => [
                'items' => [
                    [
                        'id' => 'weeknd123',
                        'name' => 'The Weeknd',
                        'genres' => ['pop'],
                        'images' => [],
                        'popularity' => 95,
                        'followers' => ['total' => 50000000],
                    ],
                ],
            ],
        ], 200),
    ]);

    // Search for "Weeknd" (without "The")
    $response = $this->actingAs($this->user)
        ->getJson('/api/artists/search?q=Weeknd');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.name', 'The Weeknd');
});
