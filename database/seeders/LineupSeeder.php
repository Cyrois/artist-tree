<?php

namespace Database\Seeders;

use App\Models\Artist;
use App\Models\ArtistMetric;
use App\Models\Lineup;
use Illuminate\Database\Seeder;

class LineupSeeder extends Seeder
{
    public function run(): void
    {
        // Create 20 mock artists if none exist
        if (Artist::count() < 20) {
             Artist::factory(20)->has(ArtistMetric::factory())->create();
        }

        $artists = Artist::all();
        
        $lineup = Lineup::firstOrCreate([
            'name' => 'Coachella 2026',
        ], [
            'description' => 'The big one in the desert.',
        ]);

        // Clear existing associations to prevent duplicates if run multiple times
        $lineup->artists()->detach();
        
        // Attach to first user (Test User) if not already attached
        $user = \App\Models\User::first();
        if ($user && !$lineup->users()->where('user_id', $user->id)->exists()) {
            $lineup->users()->attach($user->id, ['role' => 'owner']);
        }

        // Attach artists with tiers
        foreach ($artists as $index => $artist) {
            // Distribution: 10% headliner, 20% sub, 40% mid, 30% under
            if ($index < 2) $tier = 'headliner';
            elseif ($index < 6) $tier = 'sub_headliner';
            elseif ($index < 14) $tier = 'mid_tier';
            else $tier = 'undercard';
            
            $lineup->artists()->attach($artist->id, [
                'tier' => $tier,
            ]);
        }
    }
}
