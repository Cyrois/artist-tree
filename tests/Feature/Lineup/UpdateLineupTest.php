<?php

use App\Models\Lineup;
use App\Models\User;

test('authenticated user can update lineup name and description', function () {
    $user = User::factory()->create();
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($user->id, ['role' => 'owner']);

    $response = $this->actingAs($user)
        ->put(route('lineups.update', $lineup), [
            'name' => 'Updated Lineup Name',
            'description' => 'Updated Description',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('lineups', [
        'id' => $lineup->id,
        'name' => 'Updated Lineup Name',
        'description' => 'Updated Description',
    ]);
});

test('user cannot update lineup they do not belong to', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($otherUser->id, ['role' => 'owner']);

    $response = $this->actingAs($user)
        ->put(route('lineups.update', $lineup), [
            'name' => 'Hacked Lineup Name',
        ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('lineups', [
        'id' => $lineup->id,
        'name' => 'Hacked Lineup Name',
    ]);
});

test('update requires name', function () {
    $user = User::factory()->create();
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($user->id, ['role' => 'owner']);

    $response = $this->actingAs($user)
        ->put(route('lineups.update', $lineup), [
            'name' => '',
            'description' => 'Valid Description',
        ]);

    $response->assertSessionHasErrors('name');
});

test('unauthenticated user cannot update lineup', function () {
    $lineup = Lineup::factory()->create();

    $response = $this->put(route('lineups.update', $lineup), [
        'name' => 'New Name',
    ]);

    $response->assertRedirect(route('login'));
});
