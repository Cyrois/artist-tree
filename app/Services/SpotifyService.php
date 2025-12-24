<?php

namespace App\Services;

use App\DataTransferObjects\SpotifyAlbumSimpleDTO;
use App\DataTransferObjects\SpotifyArtistDTO;
use App\DataTransferObjects\SpotifyTrackDTO;
use App\Exceptions\SpotifyApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Spotify API integration service.
 *
 * Handles OAuth authentication, artist search, and rate limiting.
 */
class SpotifyService
{
    private const BASE_URL = 'https://api.spotify.com/v1';

    private const AUTH_URL = 'https://accounts.spotify.com/api/token';

    private const TOKEN_CACHE_KEY = 'spotify_access_token';

    private string $clientId;

    private string $clientSecret;

    private int $tokenCacheTtl;

    private int $searchCacheTtl;

    private int $rateLimitPerMinute;

    public function __construct()
    {
        $this->clientId = config('services.spotify.client_id');
        $this->clientSecret = config('services.spotify.client_secret');
        $this->tokenCacheTtl = config('services.spotify.token_cache_ttl', 3600);
        $this->searchCacheTtl = config('services.spotify.search_cache_ttl', 86400);
        $this->rateLimitPerMinute = config('services.spotify.rate_limit_per_minute', 180);

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new \RuntimeException('Spotify API credentials not configured');
        }
    }

    /**
     * Search for artists on Spotify.
     *
     * @param  string  $query  Search term
     * @param  int  $limit  Maximum number of results (1-50)
     * @return array<SpotifyArtistDTO>
     *
     * @throws SpotifyApiException
     */
    public function searchArtists(string $query, int $limit = 20): array
    {
        $limit = min(max($limit, 1), 50); // Clamp between 1-50
        $cacheKey = "spotify_search:{$query}:{$limit}";

        return Cache::remember($cacheKey, $this->searchCacheTtl, function () use ($query, $limit) {
            $this->checkRateLimit();

            $response = $this->makeAuthenticatedRequest()
                ->get(self::BASE_URL.'/search', [
                    'q' => $query,
                    'type' => 'artist',
                    'limit' => $limit,
                ]);

            if (! $response->successful()) {
                throw SpotifyApiException::fromResponse($response, 'Artist search failed');
            }

            $artists = $response->json('artists.items', []);

            return array_map(
                fn (array $artist) => SpotifyArtistDTO::fromSpotifyResponse($artist),
                $artists
            );
        });
    }

    /**
     * Get a single artist by Spotify ID.
     *
     * @throws SpotifyApiException
     */
    public function getArtist(string $spotifyId): SpotifyArtistDTO
    {
        $cacheKey = "spotify_artist:{$spotifyId}";

        return Cache::remember($cacheKey, $this->searchCacheTtl, function () use ($spotifyId) {
            $this->checkRateLimit();

            $response = $this->makeAuthenticatedRequest()
                ->get(self::BASE_URL."/artists/{$spotifyId}");

            if (! $response->successful()) {
                throw SpotifyApiException::fromResponse($response, 'Get artist failed');
            }

            return SpotifyArtistDTO::fromSpotifyResponse($response->json());
        });
    }

    /**
     * Get OAuth access token (cached).
     *
     * @throws SpotifyApiException
     */
    private function getAccessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, $this->tokenCacheTtl, function () {
            $response = Http::asForm()->post(self::AUTH_URL, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            if (! $response->successful()) {
                throw SpotifyApiException::fromResponse($response, 'OAuth token request failed');
            }

            return $response->json('access_token');
        });
    }

    /**
     * Create authenticated HTTP client.
     */
    private function makeAuthenticatedRequest(): PendingRequest
    {
        $token = $this->getAccessToken();

        return Http::withToken($token)
            ->timeout(10)
            ->retry(3, 1000, function ($exception, $request) {
                // Retry on network errors and 5xx responses, but not on 4xx
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    $response = $exception->response;

                    return $response && $response->status() >= 500;
                }

                return true; // Retry on connection failures
            });
    }

    /**
     * Check rate limit and throw exception if exceeded.
     *
     * Uses a simple per-minute counter. For production, consider more
     * sophisticated rate limiting (e.g., token bucket algorithm).
     *
     * @throws SpotifyApiException
     */
    private function checkRateLimit(): void
    {
        $key = 'spotify_rate_limit:'.now()->format('Y-m-d-H-i');
        $count = Cache::get($key, 0);

        if ($count >= $this->rateLimitPerMinute) {
            Log::warning('Spotify rate limit exceeded', ['count' => $count]);
            throw new SpotifyApiException('Rate limit exceeded. Please try again later.', null, 429);
        }

        Cache::put($key, $count + 1, 60); // TTL 60 seconds
    }

    /**
     * Clear cached search results for a query.
     */
    public function clearSearchCache(string $query, int $limit = 20): void
    {
        $cacheKey = "spotify_search:{$query}:{$limit}";
        Cache::forget($cacheKey);
    }

    /**
     * Clear cached artist data by Spotify ID.
     */
    public function clearArtistCache(string $spotifyId): void
    {
        $cacheKey = "spotify_artist:{$spotifyId}";
        Cache::forget($cacheKey);
    }

    /**
     * Get artist's top tracks.
     *
     * @param  string  $spotifyId  Artist's Spotify ID
     * @param  string  $market  ISO 3166-1 alpha-2 country code (default: US)
     * @param  int  $limit  Number of tracks to return (max 10)
     * @return array<SpotifyTrackDTO>
     *
     * @throws SpotifyApiException
     */
    public function getArtistTopTracks(string $spotifyId, string $market = 'US', int $limit = 5): array
    {
        $limit = min(max($limit, 1), 10);
        $cacheKey = "spotify_top_tracks:{$spotifyId}:{$market}:{$limit}";

        return Cache::remember($cacheKey, $this->searchCacheTtl, function () use ($spotifyId, $market, $limit) {
            $this->checkRateLimit();

            $response = $this->makeAuthenticatedRequest()
                ->get(self::BASE_URL."/artists/{$spotifyId}/top-tracks", [
                    'market' => $market,
                ]);

            if (! $response->successful()) {
                throw SpotifyApiException::fromResponse($response, 'Get top tracks failed');
            }

            $tracks = array_slice($response->json('tracks', []), 0, $limit);

            return array_map(
                fn (array $track) => SpotifyTrackDTO::fromSpotifyResponse($track),
                $tracks
            );
        });
    }

    /**
     * Get artist's albums.
     *
     * @param  string  $spotifyId  Artist's Spotify ID
     * @param  int  $limit  Number of albums to return (max 50)
     * @return array<SpotifyAlbumSimpleDTO>
     *
     * @throws SpotifyApiException
     */
    public function getArtistAlbums(string $spotifyId, int $limit = 10): array
    {
        $limit = min(max($limit, 1), 50);
        $cacheKey = "spotify_albums:{$spotifyId}:{$limit}";

        return Cache::remember($cacheKey, $this->searchCacheTtl, function () use ($spotifyId, $limit) {
            $this->checkRateLimit();

            $response = $this->makeAuthenticatedRequest()
                ->get(self::BASE_URL."/artists/{$spotifyId}/albums", [
                    'include_groups' => 'album,single',
                    'limit' => $limit,
                ]);

            if (! $response->successful()) {
                throw SpotifyApiException::fromResponse($response, 'Get albums failed');
            }

            $albums = $response->json('items', []);

            return array_map(
                fn (array $album) => SpotifyAlbumSimpleDTO::fromSpotifyResponse($album),
                $albums
            );
        });
    }

    /**
     * Resolve Spotify ID for an artist.
     *
     * If artist is missing spotify_id, search Spotify for exact match and persist.
     * Returns the spotify_id if found, null otherwise.
     * Caches negative results to prevent repeated API calls.
     *
     * @param  \App\Models\Artist  $artist  The artist model to resolve
     * @return string|null The Spotify ID if found, null otherwise
     */
    public function resolveSpotifyId(\App\Models\Artist $artist): ?string
    {
        if ($artist->spotify_id) {
            return $artist->spotify_id;
        }

        // Check if we've already tried and failed to resolve this artist
        $cacheKey = "spotify_resolve_failed:{$artist->id}";
        if (Cache::has($cacheKey)) {
            return null;
        }

        // Search Spotify for exact name match
        try {
            $results = $this->searchArtists($artist->name, limit: 5);

            foreach ($results as $spotifyArtist) {
                if (strcasecmp($spotifyArtist->name, $artist->name) === 0) {
                    // Exact match found - update artist record
                    $artist->update(['spotify_id' => $spotifyArtist->spotifyId]);

                    return $spotifyArtist->spotifyId;
                }
            }

            // No match found - cache this failure for 24 hours
            Cache::put($cacheKey, true, $this->searchCacheTtl);
        } catch (\Exception $e) {
            Log::warning('Failed to resolve Spotify ID', [
                'artist_id' => $artist->id,
                'artist_name' => $artist->name,
                'error' => $e->getMessage(),
            ]);

            // Cache failure to prevent retry storms during API issues
            Cache::put($cacheKey, true, 3600); // 1 hour for errors
        }

        return null;
    }
}
