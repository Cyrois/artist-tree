<?php

namespace App\Console\Commands;

use App\Models\Artist;
use App\Models\Genre;
use App\Services\SpotifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateArtistMetadataFromSpotifyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'artist:hydrate-spotify {--limit= : Limit number of artists to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hydrate missing artist metadata (images, popularity) from Spotify for imported artists';

    /**
     * Execute the console command.
     */
    public function handle(SpotifyService $spotifyService): int
    {
        $limit = $this->option('limit');

        $query = Artist::whereNotNull('spotify_id')
            ->whereNull('image_url')
            ->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        $total = $query->count();
        $this->info("Found {$total} artists needing hydration.");

        if ($total === 0) {
            return 0;
        }

        $bar = $this->output->createProgressBar($total);

        // Process in chunks of 50 (Spotify's max batch size)
        $query->chunkById(50, function ($artists) use ($spotifyService, $bar) {
            $spotifyIds = $artists->pluck('spotify_id')->filter()->toArray();

            if (empty($spotifyIds)) {
                return;
            }

            try {
                $spotifyData = $spotifyService->getArtists($spotifyIds);

                // Key by ID for easy lookup
                $spotifyMap = [];
                foreach ($spotifyData as $data) {
                    $spotifyMap[$data->spotifyId] = $data;
                }

                DB::transaction(function () use ($artists, $spotifyMap, $bar) {
                    // Local genre cache for the batch to reduce DB hits
                    $genreCache = [];

                    foreach ($artists as $artist) {
                        $data = $spotifyMap[$artist->spotify_id] ?? null;

                        if (! $data) {
                            // Artist not found in Spotify or API error?
                            // We could mark them as "checked" to avoid infinite loops, but for now skip.
                            $bar->advance();

                            continue;
                        }

                        // Update basic info
                        $artist->update([
                            'image_url' => $data->imageUrl,
                            // We could update name too, but MusicBrainz name might be preferred?
                            // Let's stick to just hydrating missing images for now.
                        ]);

                        // Sync Genres
                        if (! empty($data->genres)) {
                            $genreIds = [];
                            foreach ($data->genres as $name) {
                                if (isset($genreCache[$name])) {
                                    $genreIds[] = $genreCache[$name];

                                    continue;
                                }

                                $genre = Genre::firstOrCreate(
                                    ['name' => $name],
                                    ['slug' => Str::slug($name)]
                                );
                                $genreCache[$name] = $genre->id;
                                $genreIds[] = $genre->id;
                            }
                            $artist->genres()->syncWithoutDetaching($genreIds);
                        }

                        // Update Metrics
                        $artist->metrics()->updateOrCreate(
                            ['artist_id' => $artist->id],
                            [
                                'spotify_popularity' => $data->popularity,
                                'spotify_followers' => $data->followers,
                                'refreshed_at' => now(),
                            ]
                        );

                        $bar->advance();
                    }
                });

                // Respect rate limits - sleep slightly if needed?
                // The service handles rate limit checks, but a small pause doesn't hurt.
                usleep(200000); // 200ms

            } catch (\Exception $e) {
                $this->error('Error fetching batch: '.$e->getMessage());
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Hydration complete.');

        return 0;
    }
}
