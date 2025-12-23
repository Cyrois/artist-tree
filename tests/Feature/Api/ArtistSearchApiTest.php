<?php

use App\Models\Artist;
use App\Models\ArtistMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

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
        ->create([
            'name' => 'Arctic Monkeys',
            'spotify_id' => 'arctic123',
            'genres' => ['rock', 'indie'],
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
                    'spotify_id',
                    'name',
                    'genres',
                    'image_url',
                    'popularity',
                    'followers',
                    'exists_in_database',
                    'database_id',
                    'source',
                    'followers_formatted',
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
        ->assertJsonPath('data.0.exists_in_database', false)
        ->assertJsonPath('data.0.followers_formatted', '100K');
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

it('selects artist from Spotify and creates in database', function () {
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
            'spotify_id' => 'new123',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Artist selected successfully')
        ->assertJsonPath('data.spotify_id', 'new123')
        ->assertJsonPath('data.name', 'New Artist')
        ->assertJsonPath('data.metrics.spotify_popularity', 80)
        ->assertJsonPath('data.metrics.spotify_followers', 250000);

    $this->assertDatabaseHas('artists', [
        'spotify_id' => 'new123',
        'name' => 'New Artist',
    ]);

    $this->assertDatabaseHas('artist_metrics', [
        'spotify_popularity' => 80,
        'spotify_followers' => 250000,
    ]);
});

it('returns existing artist when selecting duplicate', function () {
    $existingArtist = Artist::factory()
        ->has(ArtistMetric::factory()->fresh())
        ->create(['spotify_id' => 'existing123']);

    $response = $this->actingAs($this->user)
        ->postJson('/api/artists/select', [
            'spotify_id' => 'existing123',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $existingArtist->id);

    // Should still only have 1 artist
    expect(Artist::count())->toBe(1);
});

it('validates spotify_id is required when selecting artist', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/artists/select', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['spotify_id']);
});

it('handles Spotify API errors gracefully when selecting artist', function () {
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response(['access_token' => 'test'], 200),
        'https://api.spotify.com/v1/artists/bad123' => Http::response([
            'error' => ['message' => 'Artist not found'],
        ], 404),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/artists/select', [
            'spotify_id' => 'bad123',
        ]);

    // Should return error status with error message
    expect($response->status())->toBeGreaterThanOrEqual(400);
    $response->assertJsonStructure(['message']);
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

    $response->assertStatus(400)
        ->assertJsonPath('message', 'Artist does not have a Spotify ID');
});

it('returns 404 when refreshing non-existent artist', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/artists/99999/refresh');

    $response->assertStatus(404);
});
