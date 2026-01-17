<?php

namespace Tests\Feature;

use App\Enums\ArtistTier;
use App\Models\Artist;
use App\Models\Lineup;
use App\Models\User;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    //

    public function test_dashboard_loads_correct_number_of_lineups_and_artists()
    {
        $user = User::factory()->create();

        // Create 5 lineups
        $lineups = Lineup::factory()->count(5)->create();
        $user->lineups()->attach($lineups);

        // Add 10 artists to one of the lineups
        $artists = Artist::factory()->count(10)->create();
        $targetLineup = $lineups->first();
        foreach ($artists as $artist) {
            $targetLineup->artists()->attach($artist->id, ['tier' => ArtistTier::Undercard->value]);
        }
        $targetLineup->touch(); // Ensure this lineup is the most recent

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);

        // Check lineups limit (3)
        $this->assertCount(3, $response->viewData('page')['props']['lineups']['data']);

        // Check previewArtists limit (4 as per Resource)
        $this->assertCount(4, $response->viewData('page')['props']['lineups']['data'][0]['preview_artists']);

        // Check total artist count (should be 10, not 5)
        $this->assertEquals(10, $response->viewData('page')['props']['lineups']['data'][0]['artist_count']);

        // Check avgScore exists
        $this->assertArrayHasKey('avg_score', $response->viewData('page')['props']['lineups']['data'][0]);
    }
}
