<?php

namespace Tests\Feature;

use App\Jobs\VerifyArtistContentJob;
use App\Models\Artist;
use App\Services\ArtistSearchService;
use App\Services\SpotifyService;
use App\DataTransferObjects\SpotifyArtistDTO;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class ArtistCleanupTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_soft_deleted_artist_is_excluded_from_search_results()
    {
        // 1. Create a soft-deleted artist
        $spotifyId = 'deleted_artist_' . uniqid();
        $artist = Artist::create([
            'name' => 'Deleted Artist',
            'spotify_id' => $spotifyId,
            'image_url' => 'http://example.com/img.jpg',
        ]);
        $artist->delete();

        // 2. Mock SpotifyService to return this artist
        $mockSpotify = Mockery::mock(SpotifyService::class);
        $mockSpotify->shouldReceive('searchArtists')
            ->once()
            ->andReturn([
                new SpotifyArtistDTO(
                    spotifyId: $spotifyId,
                    name: 'Deleted Artist',
                    imageUrl: 'http://example.com/img.jpg',
                    popularity: 50,
                    followers: 1000,
                    genres: ['pop']
                )
            ]);

        // 3. Search
        $service = new ArtistSearchService($mockSpotify);
        $results = $service->search('Deleted Artist');

        // 4. Assert
        $this->assertTrue($results->isEmpty(), 'Soft deleted artist should not appear in search results.');
    }

    public function test_verify_job_deletes_empty_artist()
    {
        // 1. Create active artist
        $spotifyId = 'empty_artist_' . uniqid();
        $artist = Artist::create([
            'name' => 'Empty Artist',
            'spotify_id' => $spotifyId,
            'image_url' => 'http://example.com/img.jpg',
        ]);

        // 2. Mock SpotifyService to return NO tracks
        $mockSpotify = Mockery::mock(SpotifyService::class);
        $mockSpotify->shouldReceive('getArtistTopTracks')
            ->with($spotifyId)
            ->once()
            ->andReturn([]);

        // 3. Run Job
        $job = new VerifyArtistContentJob($artist);
        $job->handle($mockSpotify);

        // 4. Assert
        $this->assertSoftDeleted($artist);
        $this->assertEquals(\App\Enums\ArtistDeleteReason::NO_SONGS, $artist->fresh()->deleted_reason);
    }
}
