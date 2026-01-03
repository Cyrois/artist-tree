<?php

use App\DataTransferObjects\SpotifyArtistDTO;
use App\Exceptions\SpotifyApiException;
use App\Services\SpotifyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();

    // Set up Spotify credentials in config
    config([
        'services.spotify.client_id' => 'test_client_id',
        'services.spotify.client_secret' => 'test_client_secret',
        'services.spotify.token_cache_ttl' => 3600,
        'services.spotify.search_cache_ttl' => 86400,
        'services.spotify.rate_limit_per_minute' => 180,
    ]);
});

it('successfully fetches OAuth access token', function () {
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_access_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        'https://api.spotify.com/*' => Http::response([
            'artists' => ['items' => []],
        ], 200),
    ]);

    $service = new SpotifyService;
    $service->searchArtists('test');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://accounts.spotify.com/api/token'
            && $request['grant_type'] === 'client_credentials';
    });
});

it('caches OAuth access token', function () {
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_access_token',
        ], 200),
        'https://api.spotify.com/*' => Http::response([
            'artists' => ['items' => []],
        ], 200),
    ]);

    $service = new SpotifyService;

    // First call - should fetch token
    $service->searchArtists('test1');
    // Second call - should use cached token
    $service->searchArtists('test2');

    // Token endpoint should only be called once
    Http::assertSentCount(3); // 1 token + 2 search requests
});

it('searches for artists successfully', function () {
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_access_token',
        ], 200),
        'https://api.spotify.com/v1/search*' => Http::response([
            'artists' => [
                'items' => [
                    [
                        'id' => 'artist1',
                        'name' => 'Test Artist',
                        'genres' => ['rock', 'indie'],
                        'images' => [['url' => 'https://example.com/image.jpg']],
                        'popularity' => 75,
                        'followers' => ['total' => 100000],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new SpotifyService;
    $results = $service->searchArtists('Test Artist');

    expect($results)->toHaveCount(1)
        ->and($results[0])->toBeInstanceOf(SpotifyArtistDTO::class)
        ->and($results[0]->spotifyId)->toBe('artist1')
        ->and($results[0]->name)->toBe('Test Artist')
        ->and($results[0]->genres)->toBe(['rock', 'indie'])
        ->and($results[0]->popularity)->toBe(75)
        ->and($results[0]->followers)->toBe(100000);
});

it('caches search results', function () {
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_access_token',
        ], 200),
        'https://api.spotify.com/v1/search*' => Http::response([
            'artists' => ['items' => []],
        ], 200),
    ]);

    $service = new SpotifyService;

    // First search - should hit API
    $service->searchArtists('test query');
    // Second identical search - should use cache
    $service->searchArtists('test query');

    // Should only make 1 token request + 1 search request
    Http::assertSentCount(2);
});

it('clamps search limit between 1 and 50', function () {
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_access_token',
        ], 200),
        'https://api.spotify.com/v1/search*' => Http::response([
            'artists' => ['items' => []],
        ], 200),
    ]);

    $service = new SpotifyService;

    $service->searchArtists('test', 100); // Over max

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'limit=50');
    });
});

it('gets a single artist by Spotify ID', function () {
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_access_token',
        ], 200),
        'https://api.spotify.com/v1/artists/artist123' => Http::response([
            'id' => 'artist123',
            'name' => 'Specific Artist',
            'genres' => ['electronic'],
            'images' => [['url' => 'https://example.com/artist.jpg']],
            'popularity' => 80,
            'followers' => ['total' => 500000],
        ], 200),
    ]);

    $service = new SpotifyService;
    $artist = $service->getArtist('artist123');

    expect($artist)->toBeInstanceOf(SpotifyArtistDTO::class)
        ->and($artist->spotifyId)->toBe('artist123')
        ->and($artist->name)->toBe('Specific Artist');
});

it('throws exception when Spotify API returns error', function () {
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_access_token',
        ], 200),
        'https://api.spotify.com/v1/search*' => Http::response([
            'error' => ['message' => 'Invalid search query'],
        ], 400),
    ]);

    $service = new SpotifyService;

    // Should throw an exception on API error (either SpotifyApiException or RequestException)
    expect(fn () => $service->searchArtists('test'))
        ->toThrow(Exception::class);
});

it('throws exception when rate limit is exceeded', function () {
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_access_token',
        ], 200),
        'https://api.spotify.com/v1/search*' => Http::response([
            'artists' => ['items' => []],
        ], 200),
    ]);

    // Set very low rate limit for testing
    config(['services.spotify.rate_limit_per_minute' => 2]);

    $service = new SpotifyService;

    // These should work
    $service->clearSearchCache('test1', 20);
    $service->searchArtists('test1');
    $service->clearSearchCache('test2', 20);
    $service->searchArtists('test2');

    // This should fail
    expect(fn () => $service->searchArtists('test3'))
        ->toThrow(SpotifyApiException::class, 'Rate limit exceeded');
});

it('can clear search cache', function () {
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_access_token',
        ], 200),
        'https://api.spotify.com/v1/search*' => Http::response([
            'artists' => ['items' => []],
        ], 200),
    ]);

    $service = new SpotifyService;

    // First search
    $service->searchArtists('test');

    // Clear cache
    $service->clearSearchCache('test', 20);

    // Second search should hit API again
    $service->searchArtists('test');

    // Should make 1 token + 2 search requests (not cached)
    Http::assertSentCount(3);
});

it('handles missing artist image gracefully', function () {
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::response([
            'access_token' => 'test_access_token',
        ], 200),
        'https://api.spotify.com/v1/search*' => Http::response([
            'artists' => [
                'items' => [
                    [
                        'id' => 'artist1',
                        'name' => 'No Image Artist',
                        'genres' => [],
                        'images' => [], // Empty images array
                        'popularity' => 50,
                        'followers' => ['total' => 1000],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new SpotifyService;
    $results = $service->searchArtists('test');

    expect($results[0]->imageUrl)->toBeNull();
});

it('clears token cache on 401 error', function () {
    // 1. Initial successful token fetch
    Http::fake([
        'https://accounts.spotify.com/api/token' => Http::sequence()
            ->push(['access_token' => 'expired_token'], 200)
            ->push(['access_token' => 'fresh_token'], 200),
        'https://api.spotify.com/v1/search*' => Http::sequence()
            ->push(['error' => ['status' => 401, 'message' => 'Invalid access token']], 401)
            ->push(['artists' => ['items' => []]], 200),
    ]);

    $service = new SpotifyService;

    // First attempt: should fetch 'expired_token', then get 401, which should clear cache
    try {
        $service->searchArtists('test');
    } catch (Exception $e) {
        // Expected to throw because searchArtists throws on non-successful response
    }

    expect(Cache::has('spotify_access_token'))->toBeFalse();

    // Second attempt: should fetch 'fresh_token' because cache was cleared
    $service->searchArtists('test');

    // Sent count: 
    // 1. POST token (fetching 'expired_token')
    // 2. GET search (fails 401) -> triggers cache clear
    // 3. POST token (fetching 'fresh_token')
    // 4. GET search (success)
    Http::assertSentCount(4);
});
