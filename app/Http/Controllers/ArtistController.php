<?php

namespace App\Http\Controllers;

use App\Exceptions\SpotifyApiException;
use App\Http\Requests\SearchArtistsRequest;
use App\Http\Requests\SelectArtistRequest;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\ArtistSearchResultResource;
use App\Services\ArtistSearchService;
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
    ) {}

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
        } catch (SpotifyApiException $e) {
            Log::error('Failed to refresh artist from Spotify', [
                'artist_id' => $artistId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to refresh artist from Spotify',
                'error' => $e->getMessage(),
            ], $e->statusCode ?? 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error selecting artist', [
                'artist_id' => $artistId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Refresh an artist's data from Spotify.
     *
     * POST /api/artists/{id}/refresh
     */
    public function refresh(int $id): JsonResponse
    {
        $artist = \App\Models\Artist::findOrFail($id);

        if (! $artist->spotify_id) {
            return response()->json([
                'message' => 'Artist does not have a Spotify ID',
            ], 400);
        }

        try {
            $refreshedArtist = $this->searchService->refreshArtistFromSpotify($artist);

            return response()->json([
                'message' => 'Artist refreshed successfully',
                'data' => new ArtistResource($refreshedArtist),
            ], 200);
        } catch (SpotifyApiException $e) {
            Log::error('Failed to refresh artist from Spotify', [
                'artist_id' => $id,
                'spotify_id' => $artist->spotify_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to refresh artist from Spotify',
                'error' => $e->getMessage(),
            ], $e->statusCode ?? 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error refreshing artist', [
                'artist_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred',
            ], 500);
        }
    }
}
