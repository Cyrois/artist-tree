<?php

namespace App\Http\Controllers;

use App\Exceptions\SpotifyApiException;
use App\Http\Requests\GetArtistAlbumsRequest;
use App\Http\Requests\GetArtistTopTracksRequest;
use App\Http\Requests\RefreshArtistRequest;
use App\Http\Requests\SearchArtistsRequest;
use App\Http\Requests\SelectArtistRequest;
use App\Http\Requests\ShowArtistRequest;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\ArtistSearchResultResource;
use App\Models\Artist;
use App\Services\ArtistSearchService;
use App\Services\SpotifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

/**
 * API controller for artist search and management.
 */
class ArtistController extends Controller
{
    public function __construct(
        private ArtistSearchService $searchService,
        private SpotifyService $spotifyService,
    ) {}

    /**
     * Handle Spotify API errors with standardized response.
     *
     * @param  SpotifyApiException|\Exception  $e  The exception to handle
     * @param  string  $context  Description of what operation failed (for logging)
     * @param  array<string, mixed>  $logData  Additional data to include in logs
     * @return JsonResponse Always returns 200 with empty data for graceful degradation
     */
    private function handleSpotifyError(SpotifyApiException|\Exception $e, string $context, array $logData = []): JsonResponse
    {
        $isSpotifyError = $e instanceof SpotifyApiException;
        $statusCode = $isSpotifyError ? ($e->statusCode ?? 500) : 500;

        Log::error($context, array_merge($logData, [
            'error' => $e->getMessage(),
        ]));

        // Never expose raw exception messages in production
        $message = $isSpotifyError
            ? 'Unable to fetch data from Spotify. Please try again later.'
            : 'An unexpected error occurred. Please try again later.';

        return response()->json([
            'message' => $message,
            'data' => [],
        ], 200); // Return 200 with empty data for graceful degradation
    }

    /**
     * Search for artists (hybrid local + Spotify).
     *
     * GET /api/artists/search?q={query}&limit={limit}
     */
    public function search(SearchArtistsRequest $request): AnonymousResourceCollection
    {
        $query = $request->validated('q');
        $limit = $request->validated('limit', 20);

        $results = $this->searchService->search($query, $limit);

        return ArtistSearchResultResource::collection($results);
    }

    /**
     * Select an artist (refreshes data from Spotify if available).
     *
     * POST /api/artists/select
     * Body: { "artist_id": 123 }
     */
    public function select(SelectArtistRequest $request): JsonResponse
    {
        $artistId = $request->validated('artist_id');

        try {
            $artist = \App\Models\Artist::with('metrics')->findOrFail($artistId);

            // If artist has a Spotify ID, refresh their data
            if ($artist->spotify_id) {
                $artist = $this->searchService->refreshArtistFromSpotify($artist);
            }

            return response()->json([
                'message' => 'Artist selected successfully',
                'data' => new ArtistResource($artist->load('metrics')),
            ], 200);
        } catch (SpotifyApiException|\Exception $e) {
            return $this->handleSpotifyError($e, 'Failed to select artist', [
                'artist_id' => $artistId,
            ]);
        }
    }

    /**
     * Refresh an artist's data from Spotify.
     *
     * POST /api/artists/{id}/refresh
     */
    public function refresh(int $id, RefreshArtistRequest $request): JsonResponse
    {
        $artist = Artist::findOrFail($id);

        if (! $artist->spotify_id) {
            return response()->json([
                'message' => 'Artist does not have a Spotify ID',
                'data' => [],
            ], 200);
        }

        try {
            $refreshedArtist = $this->searchService->refreshArtistFromSpotify($artist);

            return response()->json([
                'message' => 'Artist refreshed successfully',
                'data' => new ArtistResource($refreshedArtist),
            ], 200);
        } catch (SpotifyApiException|\Exception $e) {
            return $this->handleSpotifyError($e, 'Failed to refresh artist', [
                'artist_id' => $id,
                'spotify_id' => $artist->spotify_id,
            ]);
        }
    }

    /**
     * Get artist by database ID or Spotify ID.
     *
     * GET /api/artists/{id} - Get by database ID
     * GET /api/artists?spotify_id=abc123 - Get by Spotify ID
     */
    public function show(ShowArtistRequest $request, ?int $id = null): JsonResponse
    {
        // Check if querying by Spotify ID
        if ($request->has('spotify_id')) {
            $spotifyId = $request->validated('spotify_id');
            $artist = Artist::where('spotify_id', $spotifyId)->with('metrics')->first();

            if (! $artist) {
                return response()->json([
                    'message' => 'Artist not found with Spotify ID: '.$spotifyId,
                ], 404);
            }

            return response()->json([
                'data' => new ArtistResource($artist),
            ], 200);
        }

        // Query by database ID (validation ensures at least one param is provided)
        $artist = Artist::with('metrics')->find($id);

        if (! $artist) {
            return response()->json([
                'message' => 'Artist not found with ID: '.$id,
            ], 404);
        }

        return response()->json([
            'data' => new ArtistResource($artist),
        ], 200);
    }

    /**
     * Get artist's top tracks from Spotify.
     *
     * GET /api/artists/{id}/top-tracks?limit=5
     */
    public function topTracks(int $id, GetArtistTopTracksRequest $request): JsonResponse
    {
        $artist = Artist::findOrFail($id);
        $spotifyId = $this->spotifyService->resolveSpotifyId($artist);
        $limit = $request->validated('limit', 5);

        if (! $spotifyId) {
            return response()->json([
                'message' => 'Artist does not have a Spotify ID',
                'data' => [],
            ], 200);
        }

        try {
            $tracks = $this->spotifyService->getArtistTopTracks($spotifyId, limit: $limit);

            return response()->json([
                'data' => array_map(fn ($track) => $track->toArray(), $tracks),
            ], 200);
        } catch (SpotifyApiException|\Exception $e) {
            return $this->handleSpotifyError($e, 'Failed to fetch top tracks', [
                'artist_id' => $id,
                'spotify_id' => $spotifyId,
            ]);
        }
    }

    /**
     * Get artist's albums from Spotify.
     *
     * GET /api/artists/{id}/albums?limit=5
     */
    public function albums(int $id, GetArtistAlbumsRequest $request): JsonResponse
    {
        $artist = Artist::findOrFail($id);
        $spotifyId = $this->spotifyService->resolveSpotifyId($artist);
        $limit = min((int) $request->validated('limit', 5), 20);

        if (! $spotifyId) {
            return response()->json([
                'message' => 'Artist does not have a Spotify ID',
                'data' => [],
                'meta' => ['limit' => $limit, 'max_limit' => 20, 'has_more' => false],
            ], 200);
        }

        try {
            $albums = $this->spotifyService->getArtistAlbums($spotifyId, $limit);

            return response()->json([
                'data' => array_map(fn ($album) => $album->toArray(), $albums),
                'meta' => [
                    'limit' => $limit,
                    'max_limit' => 20,
                    'has_more' => count($albums) === $limit && $limit < 20,
                ],
            ], 200);
        } catch (SpotifyApiException|\Exception $e) {
            $response = $this->handleSpotifyError($e, 'Failed to fetch albums', [
                'artist_id' => $id,
                'spotify_id' => $spotifyId,
            ]);

            // Add meta to error response
            $data = $response->getData(true);
            $data['meta'] = ['limit' => $limit, 'max_limit' => 20, 'has_more' => false];

            return response()->json($data, 200);
        }
    }
}
