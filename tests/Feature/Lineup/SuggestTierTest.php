<?php

use App\Enums\ArtistTier;
use App\Models\Artist;
use App\Models\Lineup;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('suggest tier endpoint returns suggestion', function () {
    $lineup = Lineup::factory()->create();
    $this->user->lineups()->attach($lineup, ['role' => 'owner']);

    // Create artists in lineup with metrics
    // Tier 1 (headliner): High score
    $headliner = Artist::factory()->create();
    $headliner->metrics()->create([
        'spotify_popularity' => 90,
        'spotify_followers' => 1000000,
    ]);
    $lineup->artists()->attach($headliner->id, ['tier' => ArtistTier::Headliner->value]);

    // Tier 2 (undercard): Low score
    $undercard = Artist::factory()->create();
    $undercard->metrics()->create([
        'spotify_popularity' => 10,
        'spotify_followers' => 1000,
    ]);
    $lineup->artists()->attach($undercard->id, ['tier' => ArtistTier::Undercard->value]);

    // Target artist: High score (should match headliner)
    $targetArtist = Artist::factory()->create();
    $targetArtist->metrics()->create([
        'spotify_popularity' => 85,
        'spotify_followers' => 900000,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.lineups.suggest-tier', [
            'lineup' => $lineup->id,
            'artist_id' => $targetArtist->id,
        ]));

    $response->assertOk()
        ->assertJson([
            'suggested_tier' => ArtistTier::Headliner->value,
        ]);

    // Target artist: Low score (should match undercard)
    $targetArtistLow = Artist::factory()->create();
    $targetArtistLow->metrics()->create([
        'spotify_popularity' => 15,
        'spotify_followers' => 1500,
    ]);

    $responseLow = $this->actingAs($this->user)
        ->getJson(route('api.lineups.suggest-tier', [
            'lineup' => $lineup->id,
            'artist_id' => $targetArtistLow->id,
        ]));

    $responseLow->assertOk()
        ->assertJson([
            'suggested_tier' => ArtistTier::Undercard->value,
        ]);
});

test('suggest tier accepts raw score', function () {
    $lineup = Lineup::factory()->create();
    $this->user->lineups()->attach($lineup, ['role' => 'owner']);

    // Setup lineup with a headliner (Avg 90)
    $headliner = Artist::factory()->create();
    $headliner->metrics()->create(['spotify_popularity' => 90, 'spotify_followers' => 1000000]);
    $lineup->artists()->attach($headliner->id, ['tier' => ArtistTier::Headliner->value]);

    // Request with score=88 (should be headliner)
    $response = $this->actingAs($this->user)
        ->getJson(route('api.lineups.suggest-tier', [
            'lineup' => $lineup->id,
            'score' => 88,
        ]));

    $response->assertOk()
        ->assertJson([
            'suggested_tier' => ArtistTier::Headliner->value,
        ]);
});

test('suggest tier validates input', function () {
    $lineup = Lineup::factory()->create();
    $this->user->lineups()->attach($lineup, ['role' => 'owner']);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.lineups.suggest-tier', [
            'lineup' => $lineup->id,
            // Missing artist_id and score
        ]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['artist_id', 'score']);
});

test('suggest tier requires authorization', function () {
    $otherUser = User::factory()->create();
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($otherUser->id, ['role' => 'owner']);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.lineups.suggest-tier', [
            'lineup' => $lineup->id,
            'score' => 80,
        ]));

    $response->assertStatus(403);
});
