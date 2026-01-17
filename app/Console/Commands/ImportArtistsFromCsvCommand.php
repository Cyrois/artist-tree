<?php

namespace App\Console\Commands;

use App\Enums\SocialPlatform;
use App\Models\Artist;
use App\Models\Country;
use App\Models\Genre;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportArtistsFromCsvCommand extends Command
{
    protected $signature = 'artist:import-csv {files* : The CSV file(s) to import} {--limit= : Limit records per file}';

    protected $description = 'High-performance bulk import of artist data from CSV';

    private array $countryCache = [];

    private array $genreCache = [];

    // Batch size for DB operations
    private const BATCH_SIZE = 500;

    public function handle(): int
    {
        ini_set('memory_limit', '4096M');

        // 1. Pre-load reference data to minimize queries
        $this->preloadCountries();
        $this->preloadGenres();

        $files = $this->argument('files');
        $limit = $this->option('limit');

        foreach ($files as $filePath) {
            $this->processFile($filePath, $limit);
        }

        return 0;
    }

    private function preloadCountries(): void
    {
        $this->info('Pre-loading countries...');
        $this->countryCache = Country::pluck('id', 'iso2')->toArray();
    }

    private function preloadGenres(): void
    {
        $this->info('Pre-loading genres...');
        $this->genreCache = Genre::pluck('id', 'name')->toArray();
    }

    private function processFile(string $filePath, ?int $limit): void
    {
        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return;
        }

        $this->info("Processing file: {$filePath}");

        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle);
        $columns = array_flip($header);

        $estimatedLines = $limit ?: (int) (filesize($filePath) / 150);
        $bar = $this->output->createProgressBar($estimatedLines);
        $bar->setFormat('debug');

        $batchArtists = [];
        $batchIndices = [];
        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($limit && $count >= $limit) {
                break;
            }

            // Prepare artist data array (in memory)
            $artistData = $this->prepareArtistData($row, $columns);

            // Check for duplicate Spotify ID within the CURRENT batch
            if ($artistData['spotify_id'] && isset($batchArtists[$artistData['spotify_id']])) {
                $bar->advance();
                $count++;

                continue;
            }

            // Key by spotify_id if present, otherwise use MBID to prevent batch collisions
            $key = $artistData['spotify_id'] ?? $artistData['musicbrainz_id'];
            $batchArtists[$key] = $artistData;
            $batchIndices[$key] = $count;

            $bar->advance();
            $count++;

            // Process Batch
            if (count($batchArtists) >= self::BATCH_SIZE) {
                $this->processBatch($batchArtists, $row, $columns);
                $batchArtists = [];
                $batchIndices = [];
            }
        }

        // Process final partial batch
        if (! empty($batchArtists)) {
            $this->processBatch($batchArtists, [], $columns);
        }

        fclose($handle);
        $bar->finish();
        $this->newLine();
        $this->info("Imported {$count} records from {$filePath}");
    }

    private function prepareArtistData(array $row, array $columns): array
    {
        $musicbrainz_id = $row[$columns['ID']];
        $name = $row[$columns['Name']];

        // Extract first valid Spotify ID
        $spotifyUrls = explode(';', $row[$columns['Spotify']] ?? '');
        $spotifyId = null;
        foreach ($spotifyUrls as $url) {
            if ($id = $this->extractSpotifyId(trim($url))) {
                $spotifyId = $id;
                break;
            }
        }

        $countryId = $this->resolveCountry($row[$columns['Country']] ?? null, $row[$columns['Country Code']] ?? null);
        $youtubeId = $this->getFirstYoutubeId($row[$columns['YouTube']] ?? '');

        return [
            'musicbrainz_id' => $musicbrainz_id,
            'spotify_id' => $spotifyId,
            'name' => mb_substr($name, 0, 255),
            'country_id' => $countryId,
            'youtube_channel_id' => $youtubeId,
            'updated_at' => now(),
            'created_at' => now(),
            // Pass raw strings for post-processing
            '_raw_genres' => $row[$columns['Genres']] ?? '',
            '_raw_aliases' => $row[$columns['Aliases']] ?? '',
            '_raw_links' => $row,
            '_columns_map' => $columns,
        ];
    }

    private function processBatch(array $batchArtists, array $sampleRow, array $columns): void
    {
        // 1. Upsert Artists
        $upsertData = [];
        foreach ($batchArtists as $data) {
            $upsertData[] = [
                'musicbrainz_id' => $data['musicbrainz_id'],
                'spotify_id' => $data['spotify_id'],
                'name' => $data['name'],
                'country_id' => $data['country_id'],
                'youtube_channel_id' => $data['youtube_channel_id'],
                'updated_at' => $data['updated_at'],
                'created_at' => $data['created_at'],
            ];
        }

        // Remove conflicting artists (Duplicate Spotify IDs across batches)
        $spotifyIds = array_filter(array_column($upsertData, 'spotify_id'));
        if (! empty($spotifyIds)) {
            $existingSpotify = DB::table('artists')
                ->whereIn('spotify_id', $spotifyIds)
                ->pluck('musicbrainz_id', 'spotify_id')
                ->toArray();

            foreach ($upsertData as $key => $record) {
                $sid = $record['spotify_id'];
                $mbid = $record['musicbrainz_id'];

                if ($sid && isset($existingSpotify[$sid])) {
                    $existingMbid = $existingSpotify[$sid];
                    if ($existingMbid !== $mbid) {
                        // CONFLICT: Same Spotify ID, different MBID. Skip.
                        if (isset($batchArtists[$sid])) {
                            unset($batchArtists[$sid]);
                        } elseif (isset($batchArtists[$mbid])) {
                            unset($batchArtists[$mbid]);
                        }
                        unset($upsertData[$key]);
                    }
                }
            }
        }

        if (empty($upsertData)) {
            return;
        }

        // Perform Upsert on musicbrainz_id
        Artist::upsert(
            $upsertData,
            ['musicbrainz_id'],
            ['name', 'spotify_id', 'country_id', 'youtube_channel_id', 'updated_at']
        );

        // 2. Fetch IDs of the inserted/updated artists
        $mbids = array_column($upsertData, 'musicbrainz_id');
        $artistIdMap = DB::table('artists')
            ->whereIn('musicbrainz_id', $mbids)
            ->pluck('id', 'musicbrainz_id')
            ->toArray();

        // 3. Prepare Bulk Inserts for Relations
        $genrePivots = [];
        $linksToInsert = [];
        $aliasesToInsert = [];

        foreach ($batchArtists as $data) {
            $mbid = $data['musicbrainz_id'];
            if (! isset($artistIdMap[$mbid])) {
                continue;
            }
            $artistId = $artistIdMap[$mbid];

            // Prepare Genres
            $genreIds = $this->resolveGenreIds($data['_raw_genres']);
            foreach ($genreIds as $genreId) {
                $genrePivots[] = [
                    'artist_id' => $artistId,
                    'genre_id' => $genreId,
                ];
            }

            // Prepare Aliases
            $aliases = $this->parseAliases($data['_raw_aliases']);
            foreach ($aliases as $aliasName) {
                $aliasesToInsert[] = [
                    'artist_id' => $artistId,
                    'name' => mb_substr($aliasName, 0, 255),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Prepare Links
            $links = $this->parseLinks($data['_raw_links'], $data['_columns_map']);
            foreach ($links as $link) {
                $linksToInsert[] = [
                    'artist_id' => $artistId,
                    'platform' => $link['platform'],
                    'url' => $link['url'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // 4. Bulk Execute Relations
        if (! empty($genrePivots)) {
            $uniquePivots = array_map('unserialize', array_unique(array_map('serialize', $genrePivots)));
            foreach (array_chunk($uniquePivots, 1000) as $chunk) {
                DB::table('artist_genre')->insertOrIgnore($chunk);
            }
        }

        if (! empty($aliasesToInsert)) {
            foreach (array_chunk($aliasesToInsert, 1000) as $chunk) {
                DB::table('artist_aliases')->insertOrIgnore($chunk);
            }
        }

        if (! empty($linksToInsert)) {
            foreach (array_chunk($linksToInsert, 1000) as $chunk) {
                DB::table('artist_links')->insertOrIgnore($chunk);
            }
        }
    }

    private function resolveCountry(?string $name, ?string $codes): ?int
    {
        if (! $codes && ! $name) {
            return null;
        }
        $code = trim(explode(';', $codes)[0]);
        if (strlen($code) !== 2) {
            return null;
        }

        if (isset($this->countryCache[$code])) {
            return $this->countryCache[$code];
        }

        $country = Country::firstOrCreate(
            ['iso2' => strtoupper($code)],
            ['name' => $name ?: strtoupper($code)]
        );
        $this->countryCache[$code] = $country->id;

        return $country->id;
    }

    private function resolveGenreIds(string $genreString): array
    {
        if (empty($genreString)) {
            return [];
        }

        $names = array_map('trim', explode(';', $genreString));
        $ids = [];

        foreach ($names as $name) {
            if (empty($name)) {
                continue;
            }

            if (isset($this->genreCache[$name])) {
                $ids[] = $this->genreCache[$name];
            } else {
                $genre = Genre::findOrCreateSmart($name);
                $this->genreCache[$name] = $genre->id;
                $ids[] = $genre->id;
            }
        }

        return array_unique($ids);
    }

    private function parseAliases(string $aliasString): array
    {
        if (empty($aliasString)) {
            return [];
        }

        return array_filter(array_map('trim', explode(';', $aliasString)));
    }

    private function parseLinks(array $row, array $columnsMap): array
    {
        $links = [];
        $platforms = [
            'Official Homepage' => SocialPlatform::Official->value,
            'Facebook' => SocialPlatform::Facebook->value,
            'Twitter' => SocialPlatform::Twitter->value,
            'Instagram' => SocialPlatform::Instagram->value,
            'YouTube' => SocialPlatform::YouTube->value,
            'Spotify' => SocialPlatform::Spotify->value,
            'Apple Music' => SocialPlatform::AppleMusic->value,
            'SoundCloud' => SocialPlatform::SoundCloud->value,
            'Bandcamp' => SocialPlatform::Bandcamp->value,
            'TikTok' => SocialPlatform::TikTok->value,
            'Discogs' => SocialPlatform::Discogs->value,
            'Wikidata' => SocialPlatform::Wikidata->value,
            'AllMusic' => SocialPlatform::AllMusic->value,
            'Deezer' => SocialPlatform::Deezer->value,
            'Tidal' => SocialPlatform::Tidal->value,
            'Last.fm' => SocialPlatform::LastFm->value,
            'Wikipedia' => SocialPlatform::Wikipedia->value,
        ];

        foreach ($platforms as $colName => $platformKey) {
            $urls = $row[$columnsMap[$colName]] ?? null;
            if (! $urls) {
                continue;
            }

            foreach (explode(';', $urls) as $url) {
                $url = trim($url);
                if (! empty($url)) {
                    $links[] = ['platform' => $platformKey, 'url' => $url];
                }
            }
        }

        return $links;
    }

    private function extractSpotifyId(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (preg_match('/artist\/([a-zA-Z0-9]{22})/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractYoutubeId(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (preg_match('/channel\/([a-zA-Z0-9_-]{24})/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function getFirstYoutubeId(string $urls): ?string
    {
        foreach (explode(';', $urls) as $url) {
            if ($id = $this->extractYoutubeId(trim($url))) {
                return $id;
            }
        }

        return null;
    }
}
