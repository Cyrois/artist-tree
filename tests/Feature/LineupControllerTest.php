<?php

use App\Enums\ArtistTier;
use App\Models\Artist;
use App\Models\ArtistMetric;
use App\Models\Lineup;
use App\Models\User;
use App\Services\ArtistScoringService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('unauthenticated users cannot access lineup index', function () {
    $this->get(route('lineups.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can access lineup index', function () {
    // Create lineups for the test
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($this->user->id, ['role' => 'owner']);

    // Add some artists to DB
    Artist::factory()->count(5)->create();

    $this->actingAs($this->user)
        ->get(route('lineups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Lineups/Index')
            ->has('lineups.data', 1)
        );
});

test('unauthenticated users cannot access lineup show', function () {
    $lineup = Lineup::factory()->create();

    $this->get(route('lineups.show', $lineup->id))
        ->assertRedirect(route('login'));
});

test('authenticated users can access lineup show they belong to', function () {
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($this->user->id, ['role' => 'owner']);
    
    $artist = Artist::factory()->has(ArtistMetric::factory(), 'metrics')->create();

    $lineup->artists()->attach($artist->id, ['tier' => ArtistTier::Headliner->value]);

    $this->actingAs($this->user)
        ->get(route('lineups.show', $lineup->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Lineups/Show')
            ->has('id')
            ->has('lineup', fn (Assert $page) => $page
                ->where('id', $lineup->id)
                ->where('name', $lineup->name)
                ->has('artists.headliner', 1)
                ->has('stats', fn (Assert $page) => $page
                    ->where('artist_count', 1)
                    ->has('avg_score')
                )
                ->etc()
            )
        );
});

test('authenticated users cannot access lineup show they do not belong to', function () {
    $otherUser = User::factory()->create();
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($otherUser->id, ['role' => 'owner']);

    $this->actingAs($this->user)
        ->get(route('lineups.show', $lineup->id))
        ->assertStatus(403);
});

test('lineup show calculates average score correctly', function () {
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($this->user->id, ['role' => 'owner']);

    // Create artists with specific scores
    $artist1 = Artist::factory()->has(ArtistMetric::factory(), 'metrics')->create();
    $artist2 = Artist::factory()->has(ArtistMetric::factory(), 'metrics')->create();

    $lineup->artists()->attach($artist1->id, ['tier' => ArtistTier::Headliner->value]);
    $lineup->artists()->attach($artist2->id, ['tier' => ArtistTier::SubHeadliner->value]);

    $this->mock(ArtistScoringService::class, function ($mock) use ($artist1, $artist2) {
        $mock->shouldReceive('calculateScore')
            ->with(Mockery::on(fn ($a) => $a->id === $artist1->id))
            ->andReturn(80);
        $mock->shouldReceive('calculateScore')
            ->with(Mockery::on(fn ($a) => $a->id === $artist2->id))
            ->andReturn(60);
    });

    $this->actingAs($this->user)
        ->get(route('lineups.show', $lineup->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Lineups/Show')
            ->where('lineup.stats.avg_score', 70) // (80 + 60) / 2
        );
});

test('authenticated users can create a new lineup', function () {
    $payload = [
        'name' => 'New Festival 2026',
        'description' => 'A test description for the new festival.',
    ];

    $this->actingAs($this->user)
        ->postJson(route('lineups.store'), $payload)
        ->assertRedirect();

    $this->assertDatabaseHas('lineups', [
        'name' => 'New Festival 2026',
        'description' => 'A test description for the new festival.',
    ]);

    $lineup = Lineup::where('name', 'New Festival 2026')->first();

    // Check if the user is attached as owner
    $this->assertDatabaseHas('lineup_user', [
        'lineup_id' => $lineup->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);
});

test('authenticated users can add artist to lineup they own', function () {
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($this->user->id, ['role' => 'owner']);
    $artist = Artist::factory()->create();

    $this->actingAs($this->user)
        ->postJson(route('api.lineups.artists.store', $lineup->id), [
            'artist_id' => $artist->id,
            'tier' => ArtistTier::Headliner->value,
        ])
        ->assertSuccessful();

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist->id,
        'tier' => ArtistTier::Headliner->value,
    ]);
});

test('authenticated users cannot add artist to lineup they do not own', function () {
    $otherUser = User::factory()->create();
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($otherUser->id, ['role' => 'owner']);
    $artist = Artist::factory()->create();

    $this->actingAs($this->user)
        ->postJson(route('api.lineups.artists.store', $lineup->id), [
            'artist_id' => $artist->id,
            'tier' => ArtistTier::Headliner->value,
        ])
        ->assertStatus(403);
});

test('adding artist requires a tier', function () {
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($this->user->id, ['role' => 'owner']);
    $artist = Artist::factory()->create();

    $this->actingAs($this->user)
        ->postJson(route('api.lineups.artists.store', $lineup->id), [
            'artist_id' => $artist->id,
            // 'tier' is missing
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tier']);
});

test('lineup API endpoints are reachable with correct prefix', function () {
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($this->user->id, ['role' => 'owner']);
    $artist = Artist::factory()->create();

    // Regression test for the frontend fix: ensure the path includes /api
    // Using hardcoded strings here specifically to verify the URL structure
    $this->actingAs($this->user)
        ->postJson("/api/lineups/{$lineup->id}/artists", [
            'artist_id' => $artist->id,
            'tier' => ArtistTier::Headliner->value,
        ])
        ->assertSuccessful();

    $this->actingAs($this->user)
        ->getJson("/api/lineups/{$lineup->id}/suggest-tier?artist_id={$artist->id}")
        ->assertSuccessful();
});
