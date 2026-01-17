<?php

namespace App\Jobs;

use App\Enums\ArtistDeleteReason;
use App\Exceptions\SpotifyApiException;
use App\Models\Artist;
use App\Services\SpotifyService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * VerifyArtistContentJob
 *
 * This job is responsible for verifying the content of an artist.
 * It checks if the artist has any tracks on Spotify and soft deletes the artist if they don't.
 */
class VerifyArtistSpotifyContentJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 3600;

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->artist->id;
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Artist $artist
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SpotifyService $spotifyService): void
    {
        if (! $this->artist->spotify_id) {
            return;
        }

        if ($this->artist->trashed()) {
            return;
        }

        try {
            // Check for top tracks (most reliable indicator of active artist)
            $topTracks = $spotifyService->getArtistTopTracks($this->artist->spotify_id);

            if (empty($topTracks)) {
                Log::info('Soft deleting artist with no tracks', [
                    'artist_id' => $this->artist->id,
                    'name' => $this->artist->name,
                    'spotify_id' => $this->artist->spotify_id,
                ]);

                $this->artist->update(['deleted_reason' => ArtistDeleteReason::NO_SONGS]);
                $this->artist->delete();
            }
        } catch (SpotifyApiException $e) {
            // If the artist is not found on Spotify (404), they should be deleted
            if ($e->statusCode === 404) {
                Log::info('Soft deleting artist not found on Spotify (404)', [
                    'artist_id' => $this->artist->id,
                    'name' => $this->artist->name,
                    'spotify_id' => $this->artist->spotify_id,
                ]);

                $this->artist->update(['deleted_reason' => ArtistDeleteReason::SPOTIFY_404]);
                $this->artist->delete();
                return;
            }

            // Re-throw other API errors to trigger retry
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to verify artist content', [
                'artist_id' => $this->artist->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
