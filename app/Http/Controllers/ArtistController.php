<?php

namespace App\Http\Controllers;

use App\Exceptions\SpotifyApiException;
use App\Http\Requests\GetArtistAlbumsRequest;
use App\Http\Requests\GetArtistTopTracksRequest;
use App\Http\Requests\GetSimilarArtistsRequest;
use App\Http\Requests\RefreshArtistRequest;
use App\Http\Requests\SearchArtistsRequest;
use App\Http\Requests\SelectArtistRequest;
use App\Http\Requests\ShowArtistRequest;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\ArtistSearchResultResource;
use App\Models\Artist;
use App\Services\ArtistSearchService;
use App\Services\ArtistYouTubeRefreshService;
use App\Services\SpotifyService;
use App\Services\YouTubeJobDispatchService;
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
        private ArtistYouTubeRefreshService $youtubeRefreshService,
        private YouTubeJobDispatchService $youtubeJobDispatchService,
    ) {}

    /**
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
            ? __('artists.error_spotify_fetch')
            : __('common.error_unexpected');

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
     * This method is called when a user selects an artist from the artist search or lineup page.
     *
     * POST /api/artists/select
     * Body: { "artist_id": 123 } OR { "spotify_id": "..." }
     */
    public function select(SelectArtistRequest $request): JsonResponse
    {
        $artistId = $request->validated('artist_id');
        $spotifyId = $request->validated('spotify_id');

        try {
            if ($artistId) {
                $artist = \App\Models\Artist::with(['metrics', 'links'])->findOrFail($artistId);

                // If artist has a Spotify ID, refresh their data
                if ($artist->spotify_id && $artist->hasStaleMetrics()) {
                    $artist = $this->searchService->refreshArtistFromSpotify($artist);
                }

                // Refresh YouTube data if needed
                $this->youtubeRefreshService->refreshIfNeeded($artist);
            } else {
                $artist = $this->searchService->getOrCreateFromSpotify($spotifyId);
            }

            return response()->json([
                'message' => __('artists.select_success'),
                'data' => new ArtistResource($artist->load('metrics')),
            ], 200);
        } catch (SpotifyApiException|\Exception $e) {
            return $this->handleSpotifyError($e, 'Failed to select artist', [
                'artist_id' => $artistId,
                'spotify_id' => $spotifyId,
            ]);
        }
    }

    /**
     * Refresh an artist's data from Spotify.
     * TODO: This method should be an async call that either informs the UI that the calls are done or waits for the response from the third parties
     *
     * POST /api/artists/{id}/refresh
     */
    public function refresh(int $id, RefreshArtistRequest $request): JsonResponse
    {
        $artist = Artist::findOrFail($id);

        if (! $artist->spotify_id) {
            return response()->json([
                'message' => __('artists.error_no_spotify_id'),
                'data' => [],
            ], 200);
        }

        try {
            $refreshedArtist = $this->searchService->refreshArtistFromSpotify($artist);

            // Also refresh YouTube data if channel ID exists
            $this->youtubeRefreshService->forceRefresh($refreshedArtist);

            // Dispatch priority-based YouTube job for background processing
            if ($refreshedArtist->youtube_channel_id) {
                $this->youtubeJobDispatchService->dispatchPriorityJobs([$refreshedArtist->id]);
            }

            return response()->json([
                'message' => __('artists.refresh_success'),
                'data' => new ArtistResource($refreshedArtist->fresh(['metrics', 'genres', 'country'])),
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
     * GET /api/artists/{id} - Get by database ID (prioritized)
     * GET /api/artists?spotify_id=abc123 - Get by Spotify ID (fallback)
     */
    public function show(ShowArtistRequest $request, ?int $id = null): JsonResponse
    {
        $relations = ['metrics', 'genres', 'country', 'links'];

        // Prioritize database ID over Spotify ID
        if ($id) {
            $artist = Artist::with($relations)->find($id);

            if (! $artist) {
                return response()->json([
                    'message' => __('artists.error_not_found_id', ['id' => $id]),
                ], 404);
            }
        } else {
            // Fallback to Spotify ID lookup
            $spotifyId = $request->validated('spotify_id');

            if (! $spotifyId) {
                return response()->json([
                    'message' => __('artists.error_missing_identifier'),
                ], 400);
            }

            $artist = Artist::where('spotify_id', $spotifyId)->with($relations)->first();

            if (! $artist) {
                return response()->json([
                    'message' => __('artists.error_not_found_spotify', ['id' => $spotifyId]),
                ], 404);
            }
        }

        $artist = $this->refreshArtistDataIfNeeded($artist);

        return response()->json([
            'data' => new ArtistResource($artist->fresh($relations)),
        ], 200);
    }

    /**
     * Refresh artist data from external sources if needed.
     */
    private function refreshArtistDataIfNeeded(Artist $artist): Artist
    {
        // Refresh Spotify data if stale
        if ($artist->spotify_id && $artist->hasStaleMetrics()) {
            try {
                $artist = $this->searchService->refreshArtistFromSpotify($artist);
            } catch (SpotifyApiException|\Exception $e) {
                Log::warning('Failed to refresh Spotify data', [
                    'artist_id' => $artist->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Refresh YouTube data if needed
        $this->youtubeRefreshService->refreshIfNeeded($artist);

        return $artist;
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
                'message' => __('artists.error_no_spotify_id'),
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
        $type = $request->validated('type', 'album,single');

        if (! $spotifyId) {
            return response()->json([
                'message' => __('artists.error_no_spotify_id'),
                'data' => [],
                'meta' => ['limit' => $limit, 'max_limit' => 20, 'has_more' => false],
            ], 200);
        }

        try {
            $albums = $this->spotifyService->getArtistAlbums($spotifyId, $limit, $type);

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

    /**
     * Get similar artists from Spotify based on genre.
     *
     * GET /api/artists/{id}/similar?limit=10
     */
    public function similar(int $id, GetSimilarArtistsRequest $request): JsonResponse
    {
        $artist = Artist::with('genres')->findOrFail($id);
        $limit = $request->validated('limit', 10);

        if ($artist->genres->isEmpty()) {
            return response()->json([
                'data' => [],
                'message' => __('artists.error_no_genres'),
            ], 200);
        }

        try {
            // Search using the first genre for the most relevant results
            $genreName = $artist->genres->first()->name;
            $results = $this->spotifyService->searchArtistsByGenre($genreName, $limit + 1);

            // Need scoring service to calculate scores for results
            $scoringService = app(\App\Services\ArtistScoringService::class);

            // Filter out the current artist and map to array
            $similarArtists = collect($results)
                ->filter(fn ($similar) => $similar->spotifyId !== $artist->spotify_id)
                ->take($limit)
                ->map(function ($similar) use ($scoringService) {
                    $data = $similar->toArray();
                    $data['score'] = $scoringService->calculateScoreFromMetrics([
                        'spotify_popularity' => $similar->popularity,
                        'spotify_followers' => $similar->followers,
                    ]);

                    return $data;
                })
                ->values();

            return response()->json([
                'data' => $similarArtists,
            ], 200);
        } catch (SpotifyApiException|\Exception $e) {
            return $this->handleSpotifyError($e, 'Failed to fetch similar artists', [
                'artist_id' => $id,
                'genre' => $artist->genres->first()->name,
            ]);
        }
    }
}
