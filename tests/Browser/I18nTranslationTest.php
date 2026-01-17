<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

it('displays translated text on login page', function () {
    visit('/login')
        ->waitForText('Log in', 10)
        ->assertSee('Log in to your account')
        ->assertSee('Email address')
        ->assertSee('Password')
        ->assertSee('Remember me')
        ->assertSee('Forgot password')
        // Ensure translation keys are NOT visible
        ->assertDontSee('auth.login_title')
        ->assertDontSee('auth.login_email_label')
        ->assertDontSee('auth.login_password_label')
        ->assertDontSee('auth.login_remember_label');
});

it('displays translated text on register page', function () {
    visit('/register')
        ->waitForText('Create', 10)
        ->assertSee('Create an account')
        ->assertSee('Email address')
        ->assertSee('Password')
        // Ensure translation keys are NOT visible
        ->assertDontSee('auth.register_title')
        ->assertDontSee('auth.register_email_label');
});

it('displays translated text on dashboard', function () {
    $user = User::factory()->create();
    actingAs($user);

    visit('/dashboard')
        ->waitForText('Build Your Dream Lineup', 10)
        ->assertSee('Build Your Dream Lineup')
        ->assertSee('Search Artists')
        ->assertSee('My Lineups')
        // Ensure translation keys are NOT visible
        ->assertDontSee('dashboard.hero_title')
        ->assertDontSee('dashboard.search_placeholder')
        ->assertDontSee('dashboard.lineups_section_title');
});

it('displays translated text on lineups index page', function () {
    $user = User::factory()->create();
    actingAs($user);

    visit('/lineups')
        ->waitForText('Create', 10) // Wait for button text
        ->assertSee('Create')
        // Ensure translation keys are NOT visible
        ->assertDontSee('lineups.index_create_button')
        ->assertDontSee('lineups.index_page_title');
});

it('displays translated text on settings page', function () {
    $user = User::factory()->create();
    actingAs($user);

    visit('/settings')
        ->waitForText('Settings', 10)
        ->assertSee('Profile information')
        ->assertSee('Theme')
        ->assertSee('Password')
        // Ensure translation keys are NOT visible
        ->assertDontSee('settings.profile_title')
        ->assertDontSee('settings.theme_title');
});

it('displays translated text on forgot password page', function () {
    visit('/forgot-password')
        ->waitForText('Forgot', 10)
        ->assertSee('Forgot password')
        ->assertSee('Email address')
        // Ensure translation keys are NOT visible
        ->assertDontSee('auth.forgot_password_title')
        ->assertDontSee('auth.forgot_password_email_label');
});

it('ensures no translation keys are visible in common UI elements', function () {
    $user = User::factory()->create();
    actingAs($user);

    $browser = visit('/dashboard');

    // Common translation key patterns that should NOT appear
    $forbiddenPatterns = [
        'auth.',
        'dashboard.',
        'lineups.',
        'artists.',
        'settings.',
        'common.action_',
        'common.status_',
        'common.error_',
        '_title',
        '_subtitle',
        '_label',
        '_placeholder',
        '_button',
    ];

    foreach ($forbiddenPatterns as $pattern) {
        $browser->assertDontSee($pattern);
    }
});

it('translates input placeholders', function () {
    $browser = visit('/login')
        ->assertVisible('input[type="email"]');

    // Check that email input has translated placeholder
    $emailPlaceholder = $browser->attribute('input[type="email"]', 'placeholder');

    expect($emailPlaceholder)->not->toBeEmpty();
    expect($emailPlaceholder)->not->toContain('auth.login_email_placeholder');
    // Should contain something like "email@example.com"
    expect($emailPlaceholder)->toContain('@');
});

it('translates button text', function () {
    $browser = visit('/login')
        ->assertVisible('button[type="submit"]');

    // assertSeeIn is safer than text() if text() behaves weirdly
    $browser->assertSeeIn('button[type="submit"]', 'Log in');
    $browser->assertDontSeeIn('button[type="submit"]', 'auth.login_submit_button');
});

it('translates page titles', function () {
    $browser = visit('/login');
    // Using assertTitleContains to verify translation
    // If it contains "Log in", it means it's not showing the raw key (unless key is Log in, which it isn't)
    $browser->assertTitleContains('Log in');
});

it('translates navigation menu items', function () {
    $user = User::factory()->create();
    actingAs($user);

    visit('/dashboard')
        ->waitForText('Dashboard', 10)
        // Check navigation items
        ->assertSee('Dashboard')
        ->assertSee('My Lineups')
        // Open user menu to see Settings
        ->click('[data-test="sidebar-menu-button"]')
        ->waitForText('Settings', 5)
        ->assertSee('Settings')

        // Ensure translation keys are NOT visible
        ->assertDontSee('common.navigation_dashboard')
        ->assertDontSee('common.navigation_lineups')
        ->assertDontSee('common.navigation_settings');
});

it('translates validation errors', function () {
    $browser = visit('/login')
        ->type('email', 'calvin@example.com') // Use valid email format to trigger auth error
        ->type('password', 'wrong')
        ->press('Log in')
        ->waitForText('These credentials do not match our records.', 10)
        ->assertDontSee('auth.')
        ->assertDontSee('validation.');
});
