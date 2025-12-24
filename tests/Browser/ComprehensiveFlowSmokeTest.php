<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

it('completes full navigation flow through all main pages', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    actingAs($user);

    $page = visit('/dashboard');

    // Dashboard
    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs()
        ->assertSee('Dashboard');

    // Navigate to Lineups
    $page->click('My Lineups')
        ->assertPathIs('/lineups')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();

    // Navigate to Search
    $page->click('Search Artists')
        ->assertPathIs('/search')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();

    // Navigate back to Dashboard
    $page->click('Dashboard')
        ->assertPathIs('/dashboard')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'comprehensive');

it('all main pages load without errors', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    actingAs($user);

    $pages = visit([
        '/dashboard',
        '/lineups',
        '/search',
        '/settings',
        '/settings/profile',
        '/settings/organization',
        '/settings/password',
        '/settings/appearance',
        '/settings/two-factor',
    ]);

    $pages->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'comprehensive');
