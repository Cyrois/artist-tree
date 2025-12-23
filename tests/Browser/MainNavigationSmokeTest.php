<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->withoutTwoFactor()->create();
    actingAs($this->user);
});

it('can navigate to dashboard from homepage', function () {
    $page = visit('/');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs()
        ->click('Dashboard')
        ->assertPathIs('/dashboard');
})->group('smoke', 'navigation');

it('can navigate to lineups index', function () {
    $page = visit('/dashboard');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs()
        ->click('My Lineups')
        ->assertPathIs('/lineups');
})->group('smoke', 'navigation');

it('can navigate to search page', function () {
    $page = visit('/dashboard');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs()
        ->click('Search Artists')
        ->assertPathIs('/search');
})->group('smoke', 'navigation');
