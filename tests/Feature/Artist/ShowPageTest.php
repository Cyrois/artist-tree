<?php

use App\Models\Artist;
use App\Models\Lineup;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('artist show page includes user lineups for the add to lineup modal', function () {
    $artist = Artist::factory()->create();
    
    // Create 3 lineups for this user
    $lineups = Lineup::factory()->count(3)->create();
    foreach ($lineups as $lineup) {
        $lineup->users()->attach($this->user->id, ['role' => 'owner']);
    }
    
    // Create one lineup for a different user
    $otherUser = User::factory()->create();
    $otherLineup = Lineup::factory()->create();
    $otherLineup->users()->attach($otherUser->id, ['role' => 'owner']);

    $this->actingAs($this->user)
        ->get(route('artist.show', $artist->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Artist/Show')
            ->has('id')
            ->has('userLineups', 3) // Should only see the user's own lineups
            ->has('userLineups.0', fn (Assert $page) => $page
                ->has('id')
                ->has('name')
                ->has('artists_count')
                ->etc()
            )
        );
});
