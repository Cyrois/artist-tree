<?php

use App\DataTransferObjects\SpotifyArtistDTO;
use App\Jobs\CreateArtistsFromSpotifyJob;
use App\Models\Artist;
use App\Services\YouTubeJobDispatchService;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('it creates artists from Spotify data', function () {
    $spotifyArtists = [
        new SpotifyArtistDTO(
            spotifyId: 'spotify123',
            name: 'Test Artist 1',
            genres: ['rock', 'indie'],
            imageUrl: 'https://example.com/image1.jpg',
            popularity: 85,
            followers: 100000,
        ),
        new SpotifyArtistDTO(
            spotifyId: 'spotify456',
            name: 'Test Artist 2',
            genres: ['pop'],
            imageUrl: 'https://example.com/image2.jpg',
            popularity: 90,
            followers: 200000,
        ),
    ];

    $youtubeService = app(YouTubeJobDispatchService::class);
    $job = new CreateArtistsFromSpotifyJob($spotifyArtists);
    $job->handle($youtubeService);

    // Verify artists were created
    expect(Artist::count())->toBe(2);

    $artist1 = Artist::where('spotify_id', 'spotify123')->first();
    expect($artist1)->not->toBeNull();
    expect($artist1->name)->toBe('Test Artist 1');
    expect($artist1->genres->pluck('name')->toArray())->toBe(['rock', 'indie']);
    expect($artist1->image_url)->toBe('https://example.com/image1.jpg');

    // Verify metrics were created
    expect($artist1->metrics)->not->toBeNull();
    expect($artist1->metrics->spotify_popularity)->toBe(85);
    expect($artist1->metrics->spotify_followers)->toBe(100000);
    expect($artist1->metrics->refreshed_at)->not->toBeNull();

    $artist2 = Artist::where('spotify_id', 'spotify456')->first();
    expect($artist2)->not->toBeNull();
    expect($artist2->name)->toBe('Test Artist 2');
});

test('it is idempotent and does not create duplicates', function () {
    // Create an artist first
    $artist = Artist::factory()->create([
        'spotify_id' => 'spotify123',
        'name' => 'Existing Artist',
    ]);
    $artist->metrics()->create([
        'spotify_popularity' => 80,
        'spotify_followers' => 50000,
        'refreshed_at' => now(),
    ]);

    $spotifyArtists = [
        new SpotifyArtistDTO(
            spotifyId: 'spotify123', // Same as existing
            name: 'Updated Name',
            genres: ['rock'],
            imageUrl: 'https://example.com/new.jpg',
            popularity: 90,
            followers: 100000,
        ),
        new SpotifyArtistDTO(
            spotifyId: 'spotify456', // New artist
            name: 'New Artist',
            genres: ['pop'],
            imageUrl: 'https://example.com/image.jpg',
            popularity: 85,
            followers: 75000,
        ),
    ];

    $youtubeService = app(YouTubeJobDispatchService::class);
    $job = new CreateArtistsFromSpotifyJob($spotifyArtists);
    $job->handle($youtubeService);

    // Verify only one new artist was created
    expect(Artist::count())->toBe(2);

    // Existing artist should not be updated
    $existingArtist = Artist::where('spotify_id', 'spotify123')->first();
    expect($existingArtist->name)->toBe('Existing Artist'); // Not updated
    expect($existingArtist->metrics->spotify_followers)->toBe(50000); // Not updated

    // New artist should be created
    $newArtist = Artist::where('spotify_id', 'spotify456')->first();
    expect($newArtist)->not->toBeNull();
    expect($newArtist->name)->toBe('New Artist');
});

test('it handles empty array gracefully', function () {
    $youtubeService = app(YouTubeJobDispatchService::class);
    $job = new CreateArtistsFromSpotifyJob([]);
    $job->handle($youtubeService);

    expect(Artist::count())->toBe(0);
});

test('it creates artist with metrics in transaction', function () {
    $spotifyArtists = [
        new SpotifyArtistDTO(
            spotifyId: 'spotify789',
            name: 'Transaction Test',
            genres: ['jazz'],
            imageUrl: 'https://example.com/jazz.jpg',
            popularity: 75,
            followers: 30000,
        ),
    ];

    $youtubeService = app(YouTubeJobDispatchService::class);
    $job = new CreateArtistsFromSpotifyJob($spotifyArtists);
    $job->handle($youtubeService);

    $artist = Artist::where('spotify_id', 'spotify789')->first();
    expect($artist)->not->toBeNull();
    expect($artist->metrics)->not->toBeNull();
    expect($artist->metrics->artist_id)->toBe($artist->id);
});

test('it logs creation information', function () {
    Log::shouldReceive('debug')->once();
    Log::shouldReceive('info')->once()->with('CreateArtistsFromSpotifyJob: Job completed', [
        'created_count' => 1,
        'already_exist' => 0,
        'total_submitted' => 1,
        'youtube_jobs_dispatched' => 0,
    ]);

    $spotifyArtists = [
        new SpotifyArtistDTO(
            spotifyId: 'spotify_log',
            name: 'Logging Test',
            genres: ['electronic'],
            imageUrl: 'https://example.com/log.jpg',
            popularity: 70,
            followers: 20000,
        ),
    ];

    $youtubeService = app(YouTubeJobDispatchService::class);
    $job = new CreateArtistsFromSpotifyJob($spotifyArtists);
    $job->handle($youtubeService);
});

test('it handles artists with null image URL', function () {
    $spotifyArtists = [
        new SpotifyArtistDTO(
            spotifyId: 'spotify_no_image',
            name: 'No Image Artist',
            genres: ['ambient'],
            imageUrl: null,
            popularity: 60,
            followers: 10000,
        ),
    ];

    $youtubeService = app(YouTubeJobDispatchService::class);
    $job = new CreateArtistsFromSpotifyJob($spotifyArtists);
    $job->handle($youtubeService);

    $artist = Artist::where('spotify_id', 'spotify_no_image')->first();
    expect($artist)->not->toBeNull();
    expect($artist->image_url)->toBeNull();
    expect($artist->metrics)->not->toBeNull();
});

test('it handles artists with empty genres', function () {
    $spotifyArtists = [
        new SpotifyArtistDTO(
            spotifyId: 'spotify_no_genres',
            name: 'No Genres Artist',
            genres: [],
            imageUrl: 'https://example.com/image.jpg',
            popularity: 50,
            followers: 5000,
        ),
    ];

    $youtubeService = app(YouTubeJobDispatchService::class);
    $job = new CreateArtistsFromSpotifyJob($spotifyArtists);
    $job->handle($youtubeService);

    $artist = Artist::where('spotify_id', 'spotify_no_genres')->first();
    expect($artist)->not->toBeNull();
    expect($artist->genres->pluck('name')->toArray())->toBe([]);
});
