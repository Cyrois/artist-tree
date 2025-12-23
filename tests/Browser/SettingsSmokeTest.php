<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->withoutTwoFactor()->create();
    actingAs($this->user);
});

it('loads settings index page', function () {
    $page = visit('/settings');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'settings');

it('loads profile settings page', function () {
    $page = visit('/settings/profile');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'settings');

it('loads organization settings page', function () {
    $page = visit('/settings/organization');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'settings');

it('loads password settings page', function () {
    $page = visit('/settings/password');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'settings');

it('loads appearance settings page', function () {
    $page = visit('/settings/appearance');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'settings');

it('loads two factor settings page', function () {
    $page = visit('/settings/two-factor');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'settings');

it('settings pages have proper layout', function () {
    $pages = visit([
        '/settings/profile',
        '/settings/organization',
        '/settings/password',
        '/settings/appearance',
        '/settings/two-factor',
    ]);

    $pages->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'settings');
