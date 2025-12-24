<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->withoutTwoFactor()->create();
    actingAs($this->user);
});

it('loads lineups index page', function () {
    $page = visit('/lineups');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs()
        ->assertSee('My Lineups');
})->group('smoke', 'lineups');

it('has required layout elements on lineups page', function () {
    $page = visit('/lineups');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'lineups');
