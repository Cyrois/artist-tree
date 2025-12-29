<?php

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
    // Add some artists to DB so the mock generator has something to work with
    Artist::factory()->count(5)->create();

    $this->actingAs($this->user)
        ->get(route('lineups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Lineups/Index')
            ->has('lineups', 3) // LineupController has 3 mock lineups
        );
});

test('unauthenticated users cannot access lineup show', function () {
    $lineup = Lineup::factory()->create();

    $this->get(route('lineups.show', $lineup->id))
        ->assertRedirect(route('login'));
});

test('authenticated users can access lineup show', function () {
    $lineup = Lineup::factory()->create();
    $artist = Artist::factory()->has(ArtistMetric::factory(), 'metrics')->create();
    
    $lineup->artists()->attach($artist->id, ['tier' => 'headliner']);

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
                    ->where('artistCount', 1)
                    ->has('avgScore')
                )
                ->etc()
            )
        );
});

test('lineup show calculates average score correctly', function () {
    $lineup = Lineup::factory()->create();
    
    // Create artists with specific scores
    // We'll mock the scoring service to return predictable scores
    $artist1 = Artist::factory()->has(ArtistMetric::factory(), 'metrics')->create();
    $artist2 = Artist::factory()->has(ArtistMetric::factory(), 'metrics')->create();
    
    $lineup->artists()->attach($artist1->id, ['tier' => 'headliner']);
    $lineup->artists()->attach($artist2->id, ['tier' => 'sub_headliner']);
    
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
            ->where('lineup.stats.avgScore', 70) // (80 + 60) / 2
        );
});

test('lineup show handles empty lineups', function () {
    $lineup = Lineup::factory()->create();

    $this->actingAs($this->user)
        ->get(route('lineups.show', $lineup->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Lineups/Show')
            ->where('lineup.stats.artistCount', 0)
            ->where('lineup.stats.avgScore', 0)
        );
});
