<?php

it('loads welcome page for guests', function () {
    $page = visit('/');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'welcome');

it('welcome page has basic layout elements', function () {
    $page = visit('/');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
})->group('smoke', 'welcome');
