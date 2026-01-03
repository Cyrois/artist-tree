<?php

use App\Models\Lineup;
use App\Models\User;

test('owner can delete lineup', function () {
    $user = User::factory()->create();
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($user->id, ['role' => 'owner']);

    $response = $this->actingAs($user)
        ->delete(route('lineups.destroy', $lineup));

    $response->assertRedirect(route('lineups.index'));
    $this->assertSoftDeleted('lineups', ['id' => $lineup->id]);
});

test('non-owner member cannot delete lineup', function () {
    $user = User::factory()->create();
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($user->id, ['role' => 'member']);

    $response = $this->actingAs($user)
        ->delete(route('lineups.destroy', $lineup));

    $response->assertForbidden();
    $this->assertDatabaseHas('lineups', ['id' => $lineup->id, 'deleted_at' => null]);
});

test('unrelated user cannot delete lineup', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($otherUser->id, ['role' => 'owner']);

    $response = $this->actingAs($user)
        ->delete(route('lineups.destroy', $lineup));

    $response->assertForbidden();
    $this->assertDatabaseHas('lineups', ['id' => $lineup->id, 'deleted_at' => null]);
});

test('unauthenticated user cannot delete lineup', function () {
    $lineup = Lineup::factory()->create();

    $response = $this->delete(route('lineups.destroy', $lineup));

    $response->assertRedirect(route('login'));
    $this->assertDatabaseHas('lineups', ['id' => $lineup->id, 'deleted_at' => null]);
});