<?php

use App\Models\Lineup;
use App\Models\User;

test('owner can delete lineup', function () {
    $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    $user = User::factory()->create();
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($user->id, ['role' => 'owner']);

    $response = $this->actingAs($user)
        ->delete(route('lineups.destroy', $lineup));

    $response->assertRedirect(route('lineups.index'))
        ->assertSessionHas('success', 'Lineup deleted successfully.');

    $this->assertDatabaseMissing('lineups', ['id' => $lineup->id]);
});

test('non-owner member cannot delete lineup', function () {
    $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($owner->id, ['role' => 'owner']);
    $lineup->users()->attach($user->id, ['role' => 'member']);

    $response = $this->actingAs($user)
        ->delete(route('lineups.destroy', $lineup));

    $response->assertForbidden();

    $this->assertDatabaseHas('lineups', ['id' => $lineup->id]);
});

test('unrelated user cannot delete lineup', function () {
    $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    $user = User::factory()->create();
    $lineup = Lineup::factory()->create();

    $response = $this->actingAs($user)
        ->delete(route('lineups.destroy', $lineup));

    $response->assertForbidden();

    $this->assertDatabaseHas('lineups', ['id' => $lineup->id]);
});

test('unauthenticated user cannot delete lineup', function () {
    $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    $lineup = Lineup::factory()->create();

    $response = $this->delete(route('lineups.destroy', $lineup));

    $response->assertRedirect(route('login'));

    $this->assertDatabaseHas('lineups', ['id' => $lineup->id]);
});
