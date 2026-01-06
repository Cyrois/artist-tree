<?php

namespace App\Console\Commands;

use App\Models\Artist;
use App\Models\ArtistAlias;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResolveArtistConflictsCommand extends Command
{
    protected $signature = 'artist:resolve-conflicts';
    protected $description = 'Resolve conflicts from the import process';

    public function handle()
    {
        $conflictsFile = base_path('requirements/data/artist_data/conflicts.csv');
        if (!file_exists($conflictsFile)) {
            $this->error("Conflicts file not found.");
            return 1;
        }

        $this->info("Reading conflicts...");
        $handle = fopen($conflictsFile, 'r');
        fgetcsv($handle); // Skip header

        $conflicts = [];
        while (($row = fgetcsv($handle)) !== false) {
            // MBID, Name (empty), SpotifyID
            $conflicts[] = [
                'mbid' => $row[0],
                'spotify_id' => $row[2] ?? null,
            ];
        }
        fclose($handle);

        $this->info("Found " . count($conflicts) . " conflicts. Resolving names...");

        $bar = $this->output->createProgressBar(count($conflicts));
        
        $resolved = 0;
        $ambiguous = [];

        foreach ($conflicts as $conflict) {
            $mbid = $conflict['mbid'];
            $spotifyId = $conflict['spotify_id'];

            if (!$spotifyId) {
                $bar->advance();
                continue;
            }

            // 1. Get Winner from DB
            $winner = Artist::where('spotify_id', $spotifyId)->first();
            if (!$winner) {
                // Should not happen if DB is intact
                $bar->advance();
                continue;
            }

            // 2. Get Loser Name from CSV files (using grep for speed)
            $loserName = $this->grepArtistName($mbid);
            if (!$loserName) {
                $bar->advance();
                continue;
            }

            // 3. Compare Names
            $similarity = 0;
            similar_text(strtolower($winner->name), strtolower($loserName), $similarity);

            if ($similarity > 70 || str_contains($loserName, $winner->name) || str_contains($winner->name, $loserName)) {
                // High confidence: It's an alias/variation
                $this->addAlias($winner, $loserName);
                $resolved++;
            } else {
                // Ambiguous: Log for review
                $ambiguous[] = [
                    'spotify_id' => $spotifyId,
                    'winner_id' => $winner->id,
                    'winner_name' => $winner->name,
                    'loser_mbid' => $mbid,
                    'loser_name' => $loserName,
                    'similarity' => $similarity
                ];
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Resolved {$resolved} conflicts by adding aliases.");
        
        if (count($ambiguous) > 0) {
            $this->warn("Found " . count($ambiguous) . " ambiguous conflicts.");
            $this->table(
                ['Spotify ID', 'Winner', 'Loser (MBID)', 'Loser Name', 'Score'],
                array_map(fn($a) => [
                    $a['spotify_id'], 
                    $a['winner_name'], 
                    $a['loser_mbid'], 
                    $a['loser_name'], 
                    round($a['similarity'], 1) . '%'
                ], array_slice($ambiguous, 0, 50)) // Show top 50
            );
            
            // Save to file
            $fp = fopen(base_path('requirements/data/artist_data/ambiguous_conflicts.csv'), 'w');
            fputcsv($fp, ['Spotify ID', 'Winner Name', 'Loser MBID', 'Loser Name', 'Similarity']);
            foreach ($ambiguous as $row) {
                fputcsv($fp, [$row['spotify_id'], $row['winner_name'], $row['loser_mbid'], $row['loser_name'], $row['similarity']]);
            }
            fclose($fp);
            $this->info("Ambiguous conflicts saved to requirements/data/artist_data/ambiguous_conflicts.csv");
        }
    }

    private function grepArtistName(string $mbid): ?string
    {
        // Grep all parts. We assume CSV structure: MBID,Name,...
        // We look for the MBID at the start of the line
        $cmd = "grep -h \"^{$mbid},\" requirements/data/artist_data/artist_data_part_*.csv | head -n 1";
        $output = shell_exec($cmd);
        
        if ($output) {
            // Parse CSV line
            $data = str_getcsv($output);
            return $data[1] ?? null; // Name is 2nd column
        }
        return null;
    }

    private function addAlias(Artist $artist, string $aliasName): void
    {
        // Don't add if exact same name
        if (strcasecmp($artist->name, $aliasName) === 0) {
            return;
        }

        // Check if alias exists
        if (!$artist->aliases()->where('name', $aliasName)->exists()) {
            $artist->aliases()->create(['name' => $aliasName]);
        }
    }
}
