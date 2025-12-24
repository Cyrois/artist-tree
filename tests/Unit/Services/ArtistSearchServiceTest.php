<?php

use App\DataTransferObjects\SpotifyArtistDTO;
use App\Exceptions\SpotifyApiException;
use App\Models\Artist;
use App\Models\ArtistMetric;
use App\Services\ArtistSearchService;
use App\Services\SpotifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock SpotifyService
    $this->spotifyService = Mockery::mock(SpotifyService::class);
    $this->searchService = new ArtistSearchService($this->spotifyService);
});

it('returns empty collection for empty query', function () {
    $results = $this->searchService->search('');

    expect($results)->toBeEmpty();
});

it('searches local database successfully', function () {
    // Create test artists in database
    Artist::factory()
        ->has(ArtistMetric::factory())
        ->create(['name' => 'Arctic Monkeys', 'spotify_id' => 'arctic123']);

    Artist::factory()
        ->has(ArtistMetric::factory())
        ->create(['name' => 'Radiohead', 'spotify_id' => 'radio456']);

    // Mock Spotify to return empty results
    $this->spotifyService->shouldReceive('searchArtists')
        ->once()
        ->with('Arctic', 20)
        ->andReturn([]);

    $results = $this->searchService->search('Arctic');

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Arctic Monkeys')
        ->and($results->first()->source)->toBe('local')
        ->and($results->first()->existsInDatabase)->toBeTrue();
});

it('merges local and Spotify results without duplicates', function () {
    // Create local artist with searchable name
    Artist::factory()
        ->has(ArtistMetric::factory())
        ->create(['name' => 'Test Local Artist', 'spotify_id' => 'local123']);

    // Mock Spotify to return results including the local artist
    $this->spotifyService->shouldReceive('searchArtists')
        ->once()
        ->andReturn([
            new SpotifyArtistDTO(
                spotifyId: 'local123',
                name: 'Test Local Artist',
                genres: ['rock'],
                imageUrl: 'https://example.com/image.jpg',
                popularity: 75,
                followers: 100000,
            ),
            new SpotifyArtistDTO(
                spotifyId: 'spotify456',
                name: 'Test Spotify Artist',
                genres: ['indie'],
                imageUrl: null,
                popularity: 60,
                followers: 50000,
            ),
        ]);

    $results = $this->searchService->search('Test');

    // Should have 2 results total (local artist + Spotify-only artist)
    expect($results)->toHaveCount(2)
        // First result should be from local (higher priority)
        ->and($results->first()->spotifyId)->toBe('local123')
        ->and($results->first()->source)->toBe('local')
        // Second result should be from Spotify
        ->and($results->get(1)->spotifyId)->toBe('spotify456')
        ->and($results->get(1)->source)->toBe('spotify')
        ->and($results->get(1)->existsInDatabase)->toBeFalse();
});

it('prioritizes local results over Spotify results', function () {
    // Create local artists with searchable names
    for ($i = 1; $i <= 3; $i++) {
        Artist::factory()
            ->has(ArtistMetric::factory())
            ->create(['name' => "Test Artist {$i}", 'spotify_id' => "local{$i}"]);
    }

    // Mock Spotify to return different artists
    $this->spotifyService->shouldReceive('searchArtists')
        ->once()
        ->andReturn([
            new SpotifyArtistDTO(
                spotifyId: 'spotify1',
                name: 'Test Spotify Artist',
                genres: [],
                imageUrl: null,
                popularity: 80,
                followers: 200000,
            ),
        ]);

    $results = $this->searchService->search('Test', 10);

    // Local artists should come first
    expect($results->first()->source)->toBe('local');
});

it('handles Spotify API failures gracefully', function () {
    // Create local artist
    Artist::factory()
        ->has(ArtistMetric::factory())
        ->create(['name' => 'Local Artist']);

    // Mock Spotify to throw exception
    $this->spotifyService->shouldReceive('searchArtists')
        ->once()
        ->andThrow(new SpotifyApiException('API failed'));

    // Should still return local results
    $results = $this->searchService->search('Local');

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Local Artist');
});

it('creates new artist from Spotify data', function () {
    $spotifyDTO = new SpotifyArtistDTO(
        spotifyId: 'new123',
        name: 'New Artist',
        genres: ['pop', 'electronic'],
        imageUrl: 'https://example.com/new.jpg',
        popularity: 65,
        followers: 75000,
    );

    $this->spotifyService->shouldReceive('getArtist')
        ->once()
        ->with('new123')
        ->andReturn($spotifyDTO);

    $artist = $this->searchService->getOrCreateFromSpotify('new123');

    expect($artist)->toBeInstanceOf(Artist::class)
        ->and($artist->spotify_id)->toBe('new123')
        ->and($artist->name)->toBe('New Artist')
        ->and($artist->genres)->toBe(['pop', 'electronic'])
        ->and($artist->metrics)->not->toBeNull()
        ->and($artist->metrics->spotify_popularity)->toBe(65)
        ->and($artist->metrics->spotify_followers)->toBe(75000);

    $this->assertDatabaseHas('artists', [
        'spotify_id' => 'new123',
        'name' => 'New Artist',
    ]);
});

it('returns existing artist without creating duplicate', function () {
    $existingArtist = Artist::factory()
        ->has(ArtistMetric::factory()->fresh())
        ->create(['spotify_id' => 'existing123']);

    // Should not call Spotify API since metrics are fresh
    $this->spotifyService->shouldNotReceive('getArtist');

    $artist = $this->searchService->getOrCreateFromSpotify('existing123');

    expect($artist->id)->toBe($existingArtist->id);

    // Should still only have 1 artist in database
    expect(Artist::count())->toBe(1);
});

it('refreshes stale artist data from Spotify', function () {
    $artist = Artist::factory()
        ->has(ArtistMetric::factory()->stale())
        ->create(['spotify_id' => 'stale123', 'name' => 'Old Name']);

    $spotifyDTO = new SpotifyArtistDTO(
        spotifyId: 'stale123',
        name: 'Updated Name',
        genres: ['new genre'],
        imageUrl: 'https://example.com/updated.jpg',
        popularity: 90,
        followers: 1000000,
    );

    $this->spotifyService->shouldReceive('getArtist')
        ->once()
        ->with('stale123')
        ->andReturn($spotifyDTO);

    $refreshedArtist = $this->searchService->refreshArtistFromSpotify($artist);

    expect($refreshedArtist->name)->toBe('Updated Name')
        ->and($refreshedArtist->genres)->toBe(['new genre'])
        ->and($refreshedArtist->metrics->spotify_popularity)->toBe(90)
        ->and($refreshedArtist->metrics->spotify_followers)->toBe(1000000)
        ->and($refreshedArtist->metrics->isStale())->toBeFalse();
});

it('throws exception when refreshing artist without Spotify ID', function () {
    $artist = Artist::factory()->create(['spotify_id' => null]);

    expect(fn () => $this->searchService->refreshArtistFromSpotify($artist))
        ->toThrow(InvalidArgumentException::class, 'does not have a Spotify ID');
});

it('respects search limit', function () {
    // Create many local artists with searchable names
    for ($i = 1; $i <= 30; $i++) {
        Artist::factory()
            ->has(ArtistMetric::factory())
            ->create(['name' => "Test Artist {$i}", 'spotify_id' => "artist{$i}"]);
    }

    $this->spotifyService->shouldReceive('searchArtists')
        ->once()
        ->andReturn([]);

    $results = $this->searchService->search('Test', 10);

    expect($results)->toHaveCount(10);
});
