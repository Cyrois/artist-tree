<?php

namespace App\Jobs;

use App\DataTransferObjects\SpotifyArtistDTO;
use App\Models\Artist;
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

        // Extract Spotify IDs to check for existing artists
        $spotifyIds = array_map(
            fn (SpotifyArtistDTO $dto) => $dto->spotifyId,
            $this->spotifyArtists
        );

        // Get existing artists by Spotify ID
        $existingSpotifyIds = Artist::whereIn('spotify_id', $spotifyIds)
            ->pluck('spotify_id')
            ->toArray();

        // Filter out artists that already exist
        $newArtists = array_filter(
            $this->spotifyArtists,
            fn (SpotifyArtistDTO $dto) => ! in_array($dto->spotifyId, $existingSpotifyIds)
        );

        if (empty($newArtists)) {
            Log::info('CreateArtistsFromSpotifyJob: No new artists to create', [
                'total_submitted' => count($this->spotifyArtists),
                'already_exist' => count($existingSpotifyIds),
            ]);

            return;
        }

        // Create new artists with metrics in transaction
        DB::transaction(function () use ($newArtists) {
            foreach ($newArtists as $spotifyArtist) {
                $this->createArtist($spotifyArtist);
            }
        });

        Log::info('CreateArtistsFromSpotifyJob: Artists created successfully', [
            'created_count' => count($newArtists),
            'total_submitted' => count($this->spotifyArtists),
        ]);
    }

    /**
     * Create a single artist from Spotify data.
     */
    private function createArtist(SpotifyArtistDTO $spotify): void
    {
        try {
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

            Log::debug('Created artist from Spotify', [
                'artist_id' => $artist->id,
                'spotify_id' => $spotify->spotifyId,
                'name' => $spotify->name,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create artist from Spotify', [
                'spotify_id' => $spotify->spotifyId,
                'name' => $spotify->name,
                'error' => $e->getMessage(),
            ]);

            // Don't rethrow - continue processing other artists
        }
    }
}
