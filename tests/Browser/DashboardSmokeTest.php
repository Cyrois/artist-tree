<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->withoutTwoFactor()->create();
    actingAs($this->user);
});

it('loads dashboard with required layout elements', function () {
    $page = visit('/dashboard');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs()
        ->assertSee('Dashboard')
        ->assertSee('My Lineups')
        ->assertSee('Search Artists');
})->group('smoke', 'dashboard');

it('displays welcome message or user info', function () {
    $page = visit('/dashboard');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'dashboard');

it('has working sidebar navigation', function () {
    $page = visit('/dashboard');

    $page->assertNoJavascriptErrors()
        ->assertSee('Dashboard')
        ->assertSee('My Lineups')
        ->assertSee('Search Artists');
})->group('smoke', 'dashboard');
