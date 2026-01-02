<?php

namespace Database\Seeders;

use App\Enums\ArtistTier;
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
            if ($index < 2) $tier = ArtistTier::Headliner->value;
            elseif ($index < 6) $tier = ArtistTier::SubHeadliner->value;
            elseif ($index < 14) $tier = ArtistTier::MidTier->value;
            else $tier = ArtistTier::Undercard->value;
            
            $lineup->artists()->attach($artist->id, [
                'tier' => $tier,
            ]);
        }
    }
}
