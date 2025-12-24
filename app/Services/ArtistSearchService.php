<?php

namespace App\Services;

use App\DataTransferObjects\ArtistSearchResultDTO;
use App\DataTransferObjects\SpotifyArtistDTO;
use App\Exceptions\SpotifyApiException;
use App\Jobs\CreateArtistsFromSpotifyJob;
use App\Models\Artist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Artist search service with hybrid local + Spotify search.
 *
 * Searches both local database and Spotify API in parallel,
 * merges results, deduplicates, and prioritizes local results.
 */
class ArtistSearchService
{
    public function __construct(
        private SpotifyService $spotifyService,
    ) {}

    /**
     * Search for artists using hybrid approach.
     *
     * - Searches local database and Spotify API in parallel
     * - Merges and deduplicates results by Spotify ID
     * - Prioritizes local results over Spotify results
     * - Returns up to $limit results
     *
     * @param  string  $query  Search term
     * @param  int  $limit  Maximum results to return (default 20)
     * @return Collection<ArtistSearchResultDTO>
     */
    public function search(string $query, int $limit = 20): Collection
    {
        if (empty(trim($query))) {
            return collect();
        }

        // Search local database and Spotify in parallel
        [$localResults, $spotifyResults] = $this->searchBoth($query, $limit);

        // Merge, deduplicate, and limit results
        return $this->mergeAndDeduplicate($localResults, $spotifyResults, $limit);
    }

    /**
     * Search both local database and Spotify API concurrently.
     *
     * @return array{Collection<Artist>, array<SpotifyArtistDTO>}
     */
    private function searchBoth(string $query, int $limit): array
    {
        // Fetch local results immediately
        $localResults = $this->searchLocal($query, $limit);

        // Fetch Spotify results (may throw exception)
        try {
            $spotifyResults = $this->spotifyService->searchArtists($query, $limit);
        } catch (SpotifyApiException $e) {
            Log::warning('Spotify search failed, using local results only', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            $spotifyResults = [];
        }

        return [$localResults, $spotifyResults];
    }

    /**
     * Search local database for artists.
     *
     * @return Collection<Artist>
     */
    private function searchLocal(string $query, int $limit): Collection
    {
        return Artist::query()
            ->search($query)
            ->with('metrics')
            ->limit($limit)
            ->get();
    }

    /**
     * Merge local and Spotify results, deduplicate by Spotify ID.
     *
     * Priority: Local results come first, Spotify results fill in gaps.
     * Dispatches background job to create missing artists from Spotify.
     *
     * @param  Collection<Artist>  $localResults
     * @param  array<SpotifyArtistDTO>  $spotifyResults
     * @return Collection<ArtistSearchResultDTO>
     */
    private function mergeAndDeduplicate(
        Collection $localResults,
        array $spotifyResults,
        int $limit,
    ): Collection {
        $merged = collect();
        $seenSpotifyIds = [];

        // Add local results first (highest priority)
        foreach ($localResults as $artist) {
            if ($artist->spotify_id) {
                $merged->push(ArtistSearchResultDTO::fromLocalArtist($artist));
                $seenSpotifyIds[$artist->spotify_id] = true;
            }
        }

        // Track Spotify artists that don't exist in local database
        $missingArtists = [];

        // Add Spotify results that aren't already in local database
        foreach ($spotifyResults as $spotifyArtist) {
            if (isset($seenSpotifyIds[$spotifyArtist->spotifyId])) {
                continue; // Already added from local database
            }

            // Check if this Spotify artist exists in our database
            $localArtist = Artist::where('spotify_id', $spotifyArtist->spotifyId)->first();

            $merged->push(ArtistSearchResultDTO::fromSpotifyArtist($spotifyArtist, $localArtist));
            $seenSpotifyIds[$spotifyArtist->spotifyId] = true;

            // Track artists that need to be created
            if (! $localArtist) {
                $missingArtists[] = $spotifyArtist;
            }
        }

        // Dispatch background job to create missing artists
        if (! empty($missingArtists)) {
            CreateArtistsFromSpotifyJob::dispatch($missingArtists);

            Log::info('Dispatched CreateArtistsFromSpotifyJob', [
                'artists_count' => count($missingArtists),
            ]);
        }

        // Limit final results
        return $merged->take($limit);
    }

    /**
     * Get or create an artist in the database from Spotify data.
     *
     * If artist exists by Spotify ID, updates their data.
     * If not, creates new artist record with metrics.
     *
     * @param  string  $spotifyId  Spotify artist ID
     * @return Artist Created or updated artist model
     *
     * @throws SpotifyApiException
     */
    public function getOrCreateFromSpotify(string $spotifyId): Artist
    {
        // Check if artist already exists
        $artist = Artist::where('spotify_id', $spotifyId)->first();

        if ($artist) {
            // Update existing artist if metrics are stale
            if ($artist->hasStaleMetrics()) {
                $this->refreshArtistFromSpotify($artist);
            }

            return $artist;
        }

        // Fetch from Spotify and create new artist
        $spotifyData = $this->spotifyService->getArtist($spotifyId);

        return $this->createArtistFromSpotify($spotifyData);
    }

    /**
     * Create a new artist in the database from Spotify data.
     */
    private function createArtistFromSpotify(SpotifyArtistDTO $spotify): Artist
    {
        return DB::transaction(function () use ($spotify) {
            // Create artist record
            $artist = Artist::create([
                'spotify_id' => $spotify->spotifyId,
                'name' => $spotify->name,
                'genres' => $spotify->genres,
                'image_url' => $spotify->imageUrl,
            ]);

            // Create associated metrics
            $artist->metrics()->create([
                'spotify_popularity' => $spotify->popularity,
                'spotify_followers' => $spotify->followers,
                'refreshed_at' => now(),
            ]);

            return $artist->load('metrics');
        });
    }

    /**
     * Refresh an existing artist's data from Spotify.
     */
    public function refreshArtistFromSpotify(Artist $artist): Artist
    {
        if (! $artist->spotify_id) {
            throw new \InvalidArgumentException('Artist does not have a Spotify ID');
        }

        $spotifyData = $this->spotifyService->getArtist($artist->spotify_id);

        return DB::transaction(function () use ($artist, $spotifyData) {
            // Update artist data
            $artist->update([
                'name' => $spotifyData->name,
                'genres' => $spotifyData->genres,
                'image_url' => $spotifyData->imageUrl,
            ]);

            // Update or create metrics
            $artist->metrics()->updateOrCreate(
                ['artist_id' => $artist->id],
                [
                    'spotify_popularity' => $spotifyData->popularity,
                    'spotify_followers' => $spotifyData->followers,
                    'refreshed_at' => now(),
                ]
            );

            return $artist->fresh(['metrics']);
        });
    }
}
