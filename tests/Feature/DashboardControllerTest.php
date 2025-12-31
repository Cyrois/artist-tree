<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\Lineup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

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
            $targetLineup->artists()->attach($artist->id, ['tier' => 'undercard']);
        }

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        
        // Check lineups limit (3)
        $this->assertCount(3, $response->viewData('page')['props']['lineups']['data']);

        // Check previewArtists limit (5)
        $this->assertCount(5, $response->viewData('page')['props']['lineups']['data'][0]['previewArtists']);

        // Check total artist count (should be 10, not 5)
        $this->assertEquals(10, $response->viewData('page')['props']['lineups']['data'][0]['stats']['artistCount']);

        // Check avgScore exists (value depends on factory data, but check key existence)
        $this->assertArrayHasKey('avgScore', $response->viewData('page')['props']['lineups']['data'][0]['stats']);
    }
}
