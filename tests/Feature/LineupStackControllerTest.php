<?php

use App\Models\Artist;
use App\Models\Lineup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('unauthenticated users cannot access stack endpoints', function () {
    $lineup = Lineup::factory()->create();

    $this->postJson(route('api.lineups.stacks.store', $lineup->id), [])
        ->assertStatus(401);
});

test('authenticated users cannot manage stacks for lineups they do not own', function () {
    $otherUser = User::factory()->create();
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($otherUser->id, ['role' => 'owner']);

    $artist = Artist::factory()->create();
    $lineup->artists()->attach($artist->id);

    $this->actingAs($this->user)
        ->postJson(route('api.lineups.stacks.store', $lineup->id), [
            'artist_id' => $artist->id,
        ])
        ->assertStatus(403);
});

test('authenticated users can create a new stack for their lineup', function () {
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($this->user->id, ['role' => 'owner']);

    $artist = Artist::factory()->create();
    $lineup->artists()->attach($artist->id);

    $this->actingAs($this->user)
        ->postJson(route('api.lineups.stacks.store', $lineup->id), [
            'artist_id' => $artist->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Stack updated.');

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist->id,
        'is_stack_primary' => true,
    ]);

    $pivot = DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->where('artist_id', $artist->id)
        ->first();

    expect($pivot->stack_id)->not->toBeNull();
});

test('stack creation requires a valid artist in the lineup', function () {
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($this->user->id, ['role' => 'owner']);

    // Artist exists in DB but NOT in lineup
    $artist = Artist::factory()->create();

    $this->actingAs($this->user)
        ->postJson(route('api.lineups.stacks.store', $lineup->id), [
            'artist_id' => $artist->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('error', 'Artist not in lineup.');
});

test('authenticated users can add to existing stack', function () {
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($this->user->id, ['role' => 'owner']);

    $artist1 = Artist::factory()->create();
    $artist2 = Artist::factory()->create();
    $lineup->artists()->attach([$artist1->id, $artist2->id]);

    $stackId = (string) Str::uuid();
    DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->where('artist_id', $artist1->id)
        ->update(['stack_id' => $stackId, 'is_stack_primary' => true]);

    $this->actingAs($this->user)
        ->postJson(route('api.lineups.stacks.store', $lineup->id), [
            'artist_id' => $artist2->id,
            'stack_id' => $stackId,
        ])
        ->assertSuccessful();

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist2->id,
        'stack_id' => $stackId,
        'is_stack_primary' => false,
    ]);
});

test('authenticated users can promote artist in stack', function () {
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($this->user->id, ['role' => 'owner']);

    $artist1 = Artist::factory()->create();
    $artist2 = Artist::factory()->create();
    $lineup->artists()->attach([$artist1->id, $artist2->id]);

    $stackId = (string) Str::uuid();
    DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->where('artist_id', $artist1->id)
        ->update(['stack_id' => $stackId, 'is_stack_primary' => true]);

    DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->where('artist_id', $artist2->id)
        ->update(['stack_id' => $stackId, 'is_stack_primary' => false]);

    $this->actingAs($this->user)
        ->postJson(route('api.lineups.stacks.promote', ['lineup' => $lineup->id, 'stack_id' => $stackId]), [
            'artist_id' => $artist2->id,
        ])
        ->assertSuccessful();

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist1->id,
        'is_stack_primary' => false,
    ]);

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist2->id,
        'is_stack_primary' => true,
    ]);
});

test('authenticated users can remove artist from stack', function () {
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($this->user->id, ['role' => 'owner']);

    $artist = Artist::factory()->create();
    $lineup->artists()->attach($artist->id);

    $stackId = (string) Str::uuid();
    DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->where('artist_id', $artist->id)
        ->update(['stack_id' => $stackId, 'is_stack_primary' => true]);

    $this->actingAs($this->user)
        ->postJson(route('api.lineups.stacks.remove-artist', ['lineup' => $lineup->id, 'artist' => $artist->id]))
        ->assertSuccessful();

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist->id,
        'stack_id' => null,
        'is_stack_primary' => false,
    ]);
});

test('authenticated users can dissolve stack', function () {
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($this->user->id, ['role' => 'owner']);

    $artist1 = Artist::factory()->create();
    $artist2 = Artist::factory()->create();
    $lineup->artists()->attach([$artist1->id, $artist2->id]);

    $stackId = (string) Str::uuid();
    DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->update(['stack_id' => $stackId]);

    $this->actingAs($this->user)
        ->postJson(route('api.lineups.stacks.dissolve', ['lineup' => $lineup->id, 'stack_id' => $stackId]))
        ->assertSuccessful();

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist1->id,
        'stack_id' => null,
    ]);

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist2->id,
        'stack_id' => null,
    ]);
});
