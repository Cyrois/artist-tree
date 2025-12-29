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
    // Create lineups for the test
    Lineup::factory()->count(3)->create();
    
    // Add some artists to DB 
    Artist::factory()->count(5)->create();

    $this->actingAs($this->user)
        ->get(route('lineups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Lineups/Index')
            ->has('lineups.data', 3)
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

test('authenticated users can create a new lineup', function () {
    $this->withoutMiddleware();
    $payload = [
        'name' => 'New Festival 2026',
        'description' => 'A test description for the new festival.',
    ];

    $this->actingAs($this->user)
        ->post(route('lineups.store'), $payload)
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

test('lineup creation requires a name', function () {
    $this->withoutMiddleware();
    $this->actingAs($this->user)
        ->post(route('lineups.store'), [
            'description' => 'Missing name',
        ])
        ->assertSessionHasErrors('name');
});

test('lineup creation validates name length', function () {
    $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    $this->actingAs($this->user)
        ->post(route('lineups.store'), [
            'name' => str_repeat('a', 256),
        ])
        ->assertSessionHasErrors('name');
});

test('authenticated users can add artist to lineup', function () {
    $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    $lineup = Lineup::factory()->create();
    $artist = Artist::factory()->create();

    $this->actingAs($this->user)
        ->post(route('lineups.artists.store', $lineup->id), [
            'artist_id' => $artist->id,
            'tier' => 'headliner',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist->id,
        'tier' => 'headliner',
    ]);
});

test('adding artist defaults to undercard if tier missing', function () {
    $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    $lineup = Lineup::factory()->create();
    $artist = Artist::factory()->create();

    $this->actingAs($this->user)
        ->post(route('lineups.artists.store', $lineup->id), [
            'artist_id' => $artist->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist->id,
        'tier' => 'undercard',
    ]);
});

test('cannot add duplicate artist to lineup', function () {
    $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    $lineup = Lineup::factory()->create();
    $artist = Artist::factory()->create();

    $lineup->artists()->attach($artist->id, ['tier' => 'headliner']);

    $this->actingAs($this->user)
        ->post(route('lineups.artists.store', $lineup->id), [
            'artist_id' => $artist->id,
            'tier' => 'undercard',
        ])
        ->assertRedirect();

    // Should still be headliner (original), count should be 1
    $this->assertDatabaseCount('lineup_artists', 1);
    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist->id,
        'tier' => 'headliner',
    ]);
});

test('cannot add non-existent artist', function () {
    $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    $lineup = Lineup::factory()->create();

    $this->actingAs($this->user)
        ->post(route('lineups.artists.store', $lineup->id), [
            'artist_id' => 99999,
        ])
        ->assertSessionHasErrors('artist_id');
});
