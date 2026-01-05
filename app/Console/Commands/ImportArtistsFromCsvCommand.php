<?php

namespace App\Console\Commands;

use App\Enums\SocialPlatform;
use App\Models\Artist;
use App\Models\Country;
use App\Models\Genre;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportArtistsFromCsvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'artist:import-csv {files* : The CSV file(s) to import (supports glob patterns)} {--limit= : Limit the number of records to import per file for testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import artist data from MusicBrainz CSV export(s)';

    /**
     * Caches for performance optimization.
     */
    private array $countryCache = [];

    private array $genreCache = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $files = $this->argument('files');
        $limit = $this->option('limit');

        foreach ($files as $filePath) {
            $this->processFile($filePath, $limit);
        }

        return 0;
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

        // Progress bar setup based on rough estimate or limit
        // Estimate line count for progress bar (rough calc: file size / ~150 bytes per line)
        $estimatedLines = $limit ?: (int) (filesize($filePath) / 150);
        $bar = $this->output->createProgressBar($estimatedLines);
        $bar->setFormat('debug'); // Shows %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%
        $count = 0;
        $batchSize = 100;
        $batchCount = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if ($limit && $count >= $limit) {
                    break;
                }

                $this->processRow($row, $columns);

                $bar->advance();
                $count++;
                $batchCount++;

                if ($batchCount >= $batchSize) {
                    DB::commit();
                    DB::beginTransaction();
                    $batchCount = 0;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->newLine();
            $this->error("Failed at record {$count}. Error: ".$e->getMessage());
            throw $e;
        }

        fclose($handle);
        $bar->finish();
        $this->newLine();
        $this->info("Imported {$count} artists from {$filePath}");
        $this->newLine();
    }

    private function processRow(array $row, array $columns): void
    {
        $musicbrainz_id = $row[$columns['ID']];
        $name = $row[$columns['Name']];

        // Extract first valid Spotify ID if multiple exist for metrics tracking
        $spotifyUrls = explode(';', $row[$columns['Spotify']] ?? '');
        $spotifyId = null;
        foreach ($spotifyUrls as $url) {
            if ($id = $this->extractSpotifyId(trim($url))) {
                $spotifyId = $id;
                break;
            }
        }

        // 1. Resolve Country (Normalize ISO codes)
        $countryId = $this->resolveCountry($row[$columns['Country']] ?? null, $row[$columns['Country Code']] ?? null);

        // 2. Create or Update the Artist record
        $artist = Artist::updateOrCreate(
            ['musicbrainz_id' => $musicbrainz_id],
            [
                'name' => $name,
                'spotify_id' => $spotifyId,
                'country_id' => $countryId,
                'youtube_channel_id' => $this->getFirstYoutubeId($row[$columns['YouTube']] ?? ''),
            ]
        );

        // 3. Handle Normalized Genres
        $this->syncGenres($artist, $row[$columns['Genres']] ?? '');

        // 4. Handle Artist Aliases for robust search
        $this->syncAliases($artist, $row[$columns['Aliases']] ?? '');

        // 5. Handle Social & Streaming Links (Using Enums)
        $this->syncLinks($artist, $row, $columns);
    }

    /**
     * Resolve country from CSV data and cache the result.
     */
    private function resolveCountry(?string $name, ?string $codes): ?int
    {
        if (! $codes && ! $name) {
            return null;
        }

        // Take the first country code if multiple exist
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

    /**
     * Sync genres to the artist, creating new ones if necessary.
     */
    private function syncGenres(Artist $artist, string $genreString): void
    {
        if (empty($genreString)) {
            return;
        }

        $genreNames = array_map('trim', explode(';', $genreString));
        $genreIds = [];

        foreach ($genreNames as $name) {
            if (empty($name)) {
                continue;
            }

            // Using the raw name as cache key for the command session
            if (! isset($this->genreCache[$name])) {
                $genre = Genre::findOrCreateSmart($name);
                $this->genreCache[$name] = $genre->id;
            }
            $genreIds[] = $this->genreCache[$name];
        }

        $artist->genres()->sync($genreIds);
    }

    /**
     * Sync aliases to the artist for search purposes.
     */
    private function syncAliases(Artist $artist, string $aliasString): void
    {
        if (empty($aliasString)) {
            return;
        }

        $aliases = array_map('trim', explode(';', $aliasString));

        // Refresh aliases by deleting existing ones and recreating
        $artist->aliases()->delete();

        $records = [];
        foreach ($aliases as $name) {
            if (empty($name)) {
                continue;
            }
            $records[] = ['name' => $name];
        }

        if (! empty($records)) {
            $artist->aliases()->createMany($records);
        }
    }

    /**
     * Sync social and streaming links using the SocialPlatform enum.
     */
    private function syncLinks(Artist $artist, array $row, array $columns): void
    {
        // Mapping CSV column names to our internal platform keys
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

        // Refresh links for this artist to match current CSV state
        $artist->links()->delete();

        $records = [];
        foreach ($platforms as $colName => $platformKey) {
            $urls = $row[$columns[$colName]] ?? null;
            if (! $urls) {
                continue;
            }

            foreach (explode(';', $urls) as $url) {
                $url = trim($url);
                if (empty($url)) {
                    continue;
                }

                $records[] = [
                    'platform' => $platformKey,
                    'url' => $url,
                ];
            }
        }

        if (! empty($records)) {
            $artist->links()->createMany($records);
        }
    }

    /**
     * Heuristic to pick the first valid YouTube ID as the primary channel.
     */
    private function getFirstYoutubeId(string $urls): ?string
    {
        foreach (explode(';', $urls) as $url) {
            if ($id = $this->extractYoutubeId(trim($url))) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Extract Spotify Artist ID from URL.
     */
    private function extractSpotifyId(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        // Supports: https://open.spotify.com/artist/6eUKZXS0eZJ6t9vj6oXNpZ
        if (preg_match('/artist\/([a-zA-Z0-9]{22})/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract YouTube Channel ID from URL.
     */
    private function extractYoutubeId(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        // Supports: https://www.youtube.com/channel/UC-9-kyTW8ZkZNDHQJ6FgpwQ
        if (preg_match('/channel\/([a-zA-Z0-9_-]{24})/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
