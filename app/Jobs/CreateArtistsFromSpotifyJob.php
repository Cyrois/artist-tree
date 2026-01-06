<?php

namespace App\Jobs;

use App\DataTransferObjects\SpotifyArtistDTO;
use App\Models\Artist;
use App\Models\Genre;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Background job to create artists from Spotify search results.
 *
 * Accepts array of SpotifyArtistDTO objects and creates missing artists
 * in the database. Job is idempotent - checks by spotify_id before creating.
 */
class CreateArtistsFromSpotifyJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param  array<SpotifyArtistDTO>  $spotifyArtists
     */
    public function __construct(
        private array $spotifyArtists,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->spotifyArtists)) {
            return;
        }

        // 1. Optimize Genre Resolution: Deduplicate and bulk fetch
        $genreMap = $this->resolveGenres();

        $createdCount = 0;
        $alreadyExistCount = 0;

        // Create artists with metrics in transaction
        DB::transaction(function () use ($genreMap, &$createdCount, &$alreadyExistCount) {
            foreach ($this->spotifyArtists as $spotifyArtist) {
                try {
                    // Atomic check/create without genres first
                    $artist = Artist::firstOrCreate(
                        ['spotify_id' => $spotifyArtist->spotifyId],
                        [
                            'name' => $spotifyArtist->name,
                            'image_url' => $spotifyArtist->imageUrl,
                        ]
                    );

                    if ($artist->wasRecentlyCreated) {
                        // Sync genres using pre-resolved map
                        if (! empty($spotifyArtist->genres)) {
                            $genreIds = [];
                            foreach ($spotifyArtist->genres as $name) {
                                if (isset($genreMap[$name])) {
                                    $genreIds[] = $genreMap[$name];
                                }
                            }
                            $artist->genres()->sync($genreIds);
                        }

                        // Create associated metrics for new artists
                        $artist->metrics()->create([
                            'spotify_popularity' => $spotifyArtist->popularity,
                            'spotify_followers' => $spotifyArtist->followers,
                            'refreshed_at' => now(),
                        ]);

                        Log::debug('Created artist from Spotify', [
                            'artist_id' => $artist->id,
                            'spotify_id' => $spotifyArtist->spotifyId,
                            'name' => $spotifyArtist->name,
                        ]);

                        $createdCount++;
                    } else {
                        $alreadyExistCount++;
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to create artist from Spotify', [
                        'spotify_id' => $spotifyArtist->spotifyId,
                        'name' => $spotifyArtist->name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info('CreateArtistsFromSpotifyJob: Job completed', [
            'created_count' => $createdCount,
            'already_exist' => $alreadyExistCount,
            'total_submitted' => count($this->spotifyArtists),
        ]);
    }

    /**
     * Resolve genre IDs for all artists in the batch efficiently.
     *
     * @return array<string, int> Map of genre name to ID
     */
    private function resolveGenres(): array
    {
        $allGenreNames = [];
        foreach ($this->spotifyArtists as $dto) {
            if (! empty($dto->genres)) {
                foreach ($dto->genres as $name) {
                    $allGenreNames[] = $name;
                }
            }
        }

        if (empty($allGenreNames)) {
            return [];
        }

        $uniqueNames = array_unique($allGenreNames);
        $map = [];

        // 1. Bulk find exact matches to save queries
        // Note: This relies on database collation for case-sensitivity.
        // findOrCreateSmart is more robust, so this is just a first-pass optimization.
        $existing = Genre::whereIn('name', $uniqueNames)->get();
        foreach ($existing as $genre) {
            $map[$genre->name] = $genre->id;
        }

        // 2. Resolve remaining using smart logic (handles creation and synonyms)
        foreach ($uniqueNames as $name) {
            if (! isset($map[$name])) {
                $genre = Genre::findOrCreateSmart($name);
                $map[$name] = $genre->id;
            }
        }

        return $map;
    }
}
