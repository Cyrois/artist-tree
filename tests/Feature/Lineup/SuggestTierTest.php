<?php

namespace Tests\Feature\Lineup;

use App\Models\Artist;
use App\Models\Lineup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestTierTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggest_tier_endpoint_returns_suggestion()
    {
        $user = User::factory()->create();
        $lineup = Lineup::factory()->create();
        $user->lineups()->attach($lineup, ['role' => 'owner']);

        // Create artists in lineup with metrics
        // Tier 1 (headliner): High score
        $headliner = Artist::factory()->create();
        $headliner->metrics()->create([
            'spotify_popularity' => 90, 
            'spotify_followers' => 1000000
        ]);
        $lineup->artists()->attach($headliner->id, ['tier' => 'headliner']);

        // Tier 2 (undercard): Low score
        $undercard = Artist::factory()->create();
        $undercard->metrics()->create([
            'spotify_popularity' => 10,
            'spotify_followers' => 1000
        ]);
        $lineup->artists()->attach($undercard->id, ['tier' => 'undercard']);

        // Target artist: High score (should match headliner)
        $targetArtist = Artist::factory()->create();
        $targetArtist->metrics()->create([
            'spotify_popularity' => 85,
            'spotify_followers' => 900000
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('lineups.suggest-tier', [
                'lineup' => $lineup->id,
                'artist_id' => $targetArtist->id
            ]));

        $response->assertOk()
            ->assertJson([
                'suggested_tier' => 'headliner'
            ]);
            
        // Target artist: Low score (should match undercard)
        $targetArtistLow = Artist::factory()->create();
        $targetArtistLow->metrics()->create([
            'spotify_popularity' => 15,
            'spotify_followers' => 1500
        ]);

        $responseLow = $this->actingAs($user)
            ->getJson(route('lineups.suggest-tier', [
                'lineup' => $lineup->id,
                'artist_id' => $targetArtistLow->id
            ]));

        $responseLow->assertOk()
            ->assertJson([
                'suggested_tier' => 'undercard'
            ]);
    }

    public function test_suggest_tier_accepts_raw_score()
    {
        $user = User::factory()->create();
        $lineup = Lineup::factory()->create();
        $user->lineups()->attach($lineup, ['role' => 'owner']);

        // Setup lineup with a headliner (Avg 90)
        $headliner = Artist::factory()->create();
        $headliner->metrics()->create(['spotify_popularity' => 90, 'spotify_followers' => 1000000]);
        $lineup->artists()->attach($headliner->id, ['tier' => 'headliner']);

        // Request with score=88 (should be headliner)
        $response = $this->actingAs($user)
            ->getJson(route('lineups.suggest-tier', [
                'lineup' => $lineup->id,
                'score' => 88
            ]));

        $response->assertOk()
            ->assertJson([
                'suggested_tier' => 'headliner'
            ]);
    }
    
    public function test_suggest_tier_validates_input()
    {
        $user = User::factory()->create();
        $lineup = Lineup::factory()->create();
        
        $response = $this->actingAs($user)
            ->getJson(route('lineups.suggest-tier', [
                'lineup' => $lineup->id,
                // Missing artist_id and score
            ]));
            
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['artist_id', 'score']);
    }
}
