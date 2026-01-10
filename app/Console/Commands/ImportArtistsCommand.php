<?php

namespace App\Console\Commands;

use App\Enums\SocialPlatform;
use App\Models\Artist;
use App\Models\ArtistAlias;
use App\Models\ArtistLink;
use App\Models\Country;
use App\Models\Genre;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SplFileObject;

class ImportArtistsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'artists:import {file? : Specific CSV file to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import artists from CSV data files';

    /**
     * Mapping of CSV headers to SocialPlatform Enum values.
     */
    protected $platformMapping = [
        'Official Homepage' => 'official',
        'Facebook' => 'facebook',
        'Twitter' => 'twitter',
        'Instagram' => 'instagram',
        'YouTube' => 'youtube',
        'Spotify' => 'spotify',
        'Apple Music' => 'apple_music',
        'SoundCloud' => 'soundcloud',
        'Bandcamp' => 'bandcamp',
        'Discogs' => 'discogs',
        'Wikidata' => 'wikidata',
        'AllMusic' => 'allmusic',
        'Deezer' => 'deezer',
        'Tidal' => 'tidal',
        'Last.fm' => 'last_fm',
        'Wikipedia' => 'wikipedia',
        'TikTok' => 'tiktok',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $specificFile = $this->argument('file');
        
        if ($specificFile) {
            $files = [$specificFile];
        } else {
            $files = glob(base_path('requirements/data/artist_data/artist_data_part_*.csv'));
        }

        if (empty($files)) {
            $this->error('No CSV files found.');
            return 1;
        }

        $this->info('Starting artist import...');

        // Pre-load existing MBIDs to memory to reduce DB hits (Optimization)
        $this->info('Loading existing artists into memory...');
        $existingMbids = Artist::pluck('musicbrainz_id')->flip()->toArray();
        $this->info('Loaded ' . count($existingMbids) . ' existing artists.');

        // Pre-load countries to memory to reduce DB hits
        $countries = Country::all()->keyBy('iso2'); // Key by ISO2 code
        $countryNameMap = Country::all()->keyBy(fn($c) => strtolower($c->name)); // Fallback map by name

        $errorLogPath = storage_path('logs/artist_import_errors_' . date('Y-m-d_H-i-s') . '.csv');
        $errorHandle = fopen($errorLogPath, 'w');
        fputcsv($errorHandle, ['File', 'Row', 'Artist Name', 'MBID', 'Spotify ID', 'Error Type', 'Message']);

        foreach ($files as $file) {
            $this->processFile($file, $countries, $countryNameMap, $errorHandle, $existingMbids);
        }

        fclose($errorHandle);
        $this->info("Import complete. Check errors in: $errorLogPath");
    }

    private function processFile(string $filePath, $countries, $countryNameMap, $errorHandle, &$existingMbids)
    {
        $this->info("Processing file: " . basename($filePath));

        $csv = new SplFileObject($filePath);
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        
        $headers = [];
        $headerMap = []; // Col Index -> Header Name

        foreach ($csv as $index => $row) {
            if ($index === 0) {
                $headers = $row;
                $headerMap = array_flip($headers);
                // Validate required headers
                if (!isset($headerMap['ID']) || !isset($headerMap['Name'])) {
                    $this->error("Invalid CSV format in $filePath. Missing ID or Name.");
                    return;
                }
                continue;
            }

            // Skip empty rows
            if (empty($row) || count($row) < 2) {
                continue;
            }

            try {
                $this->processRow($row, $headerMap, $filePath, $index + 1, $countries, $countryNameMap, $errorHandle, $existingMbids);
            } catch (\Exception $e) {
                // Catch any unexpected crashes per row
                fputcsv($errorHandle, [
                    basename($filePath),
                    $index + 1,
                    $row[$headerMap['Name']] ?? 'Unknown',
                    $row[$headerMap['ID']] ?? 'Unknown',
                    'Unknown',
                    'Exception',
                    $e->getMessage()
                ]);
            }
        }
    }

    private function processRow(array $row, array $headerMap, string $fileName, int $rowNum, $countries, $countryNameMap, $errorHandle, &$existingMbids)
    {
        $mbid = $row[$headerMap['ID']];
        $name = mb_scrub($row[$headerMap['Name']]); // Sanitize UTF-8
        
        $countryCode = $row[$headerMap['Country Code']] ?? null;
        if ($countryCode) {
             $countryCode = strtoupper(trim($countryCode));
             // Validate ISO2 format (2 chars A-Z)
             if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
                 $countryCode = null; // Invalid code, ignore
             }
        }

        $countryName = $row[$headerMap['Country']] ?? null;
        if ($countryName) $countryName = mb_scrub($countryName);
        
        // Extract Spotify ID
        $spotifyLink = $row[$headerMap['Spotify']] ?? null;
        $spotifyId = null;
        if ($spotifyLink) {
            if (preg_match('/artist\/([a-zA-Z0-9]+)/', $spotifyLink, $matches)) {
                $spotifyId = $matches[1];
            }
        }

        // 1. Check for Duplicate MBID (Primary Key Check) - Memory Optimized
        if (isset($existingMbids[$mbid])) {
             return; 
        }

        // 2. Check for Duplicate Spotify ID (Conflict Check)
        if ($spotifyId && Artist::where('spotify_id', $spotifyId)->exists()) {
            $existing = Artist::where('spotify_id', $spotifyId)->first();
            fputcsv($errorHandle, [
                basename($fileName),
                $rowNum,
                $name,
                $mbid,
                $spotifyId,
                'Duplicate Spotify ID',
                "Conflict with existing artist ID: {$existing->id} (MBID: {$existing->musicbrainz_id})"
            ]);
            return; // Skip insertion
        }

        // 3. Resolve Country
        $countryId = null;
        if ($countryCode && isset($countries[$countryCode])) {
            $countryId = $countries[$countryCode]->id;
        } elseif ($countryName && isset($countryNameMap[strtolower($countryName)])) {
            $countryId = $countryNameMap[strtolower($countryName)]->id;
        } elseif ($countryName || $countryCode) {
            // Create new country if not exists (Basic Create)
            // Note: In a strict environment we might not want to auto-create without code, but for now:
            $newCountry = Country::firstOrCreate(
                ['iso2' => $countryCode ?: substr(strtoupper($countryName), 0, 2)], // Fallback ISO
                ['name' => $countryName ?: $countryCode]
            );
            $countryId = $newCountry->id;
            // Update cache maps
            if ($countryCode) $countries[$countryCode] = $newCountry;
            if ($countryName) $countryNameMap[strtolower($countryName)] = $newCountry;
        }

        // 4. Create Artist
        DB::beginTransaction();
        try {
            $artist = Artist::create([
                'musicbrainz_id' => $mbid,
                'name' => $name,
                'spotify_id' => $spotifyId,
                'country_id' => $countryId,
                // youtube_channel_id extraction could be added here if needed from URL
            ]);

            // 5. Aliases
            $aliasesStr = $row[$headerMap['Aliases']] ?? '';
            if ($aliasesStr) {
                $aliases = explode(';', $aliasesStr);
                foreach ($aliases as $aliasName) {
                    $aliasName = trim($aliasName);
                    if ($aliasName) {
                        ArtistAlias::create([
                            'artist_id' => $artist->id,
                            'name' => $aliasName
                        ]);
                    }
                }
            }

            // 6. Genres
            $genresStr = $row[$headerMap['Genres']] ?? '';
            if ($genresStr) {
                $genreNames = explode(';', $genresStr);
                $genreIds = [];
                foreach ($genreNames as $genreName) {
                    $genre = Genre::findOrCreateSmart($genreName);
                    $genreIds[] = $genre->id;
                }
                $artist->genres()->sync($genreIds);
            }

            // 7. Links
            foreach ($this->platformMapping as $header => $platformValue) {
                if (isset($headerMap[$header]) && !empty($row[$headerMap[$header]])) {
                    $url = $row[$headerMap[$header]];
                    
                    // Special extraction for YouTube Channel ID if needed for main table
                    if ($platformValue === 'youtube' && !$artist->youtube_channel_id) {
                         // Simple extraction logic: channel/ID or user/USER
                         if (preg_match('/channel\/(UC[\w-]+)/', $url, $matches)) {
                             $artist->update(['youtube_channel_id' => $matches[1]]);
                         }
                    }

                    ArtistLink::create([
                        'artist_id' => $artist->id,
                        'platform' => SocialPlatform::from($platformValue),
                        'url' => $url
                    ]);
                }
            }

            DB::commit();
            
            // Mark as existing
            $existingMbids[$mbid] = true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e; // Re-throw to be caught by processFile loop
        }
    }
}
