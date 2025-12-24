<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->withoutTwoFactor()->create();
    actingAs($this->user);
});

it('loads search page', function () {
    $page = visit('/search');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs()
        ->assertSee('Search Artists');
})->group('smoke', 'search');

it('has search input field', function () {
    $page = visit('/search');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'search');

it('search page layout renders correctly', function () {
    $page = visit('/search');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'search');
