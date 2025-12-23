<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class I18nTranslationTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test that login page displays translated text, not translation keys.
     */
    public function test_login_page_displays_translated_text(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
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
    }

    /**
     * Test that register page displays translated text.
     */
    public function test_register_page_displays_translated_text(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->waitForText('Create', 10)
                ->assertSee('Create an account')
                ->assertSee('Email address')
                ->assertSee('Password')
                // Ensure translation keys are NOT visible
                ->assertDontSee('auth.register_title')
                ->assertDontSee('auth.register_email_label');
        });
    }

    /**
     * Test that dashboard displays translated text when logged in.
     */
    public function test_dashboard_displays_translated_text(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Build Your Dream Lineup', 10)
                ->assertSee('Build Your Dream Lineup')
                ->assertSee('Search for artists')
                ->assertSee('Your Lineups')
                ->assertSee('Trending Artists')
                // Ensure translation keys are NOT visible
                ->assertDontSee('dashboard.hero_title')
                ->assertDontSee('dashboard.search_placeholder')
                ->assertDontSee('dashboard.lineups_section_title')
                ->assertDontSee('dashboard.trending_section_title');
        });
    }

    /**
     * Test that lineups index page displays translated text.
     */
    public function test_lineups_index_displays_translated_text(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/lineups')
                ->waitFor('body', 10)
                // Should see translated button text
                ->assertSeeIn('body', 'Create')
                // Ensure translation keys are NOT visible
                ->assertDontSee('lineups.index_create_button')
                ->assertDontSee('lineups.index_page_title');
        });
    }

    /**
     * Test that settings page displays translated text.
     */
    public function test_settings_page_displays_translated_text(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/settings')
                ->waitForText('Settings', 10)
                ->assertSee('Profile')
                ->assertSee('Password')
                ->assertSee('Appearance')
                // Ensure translation keys are NOT visible
                ->assertDontSee('settings.navigation_profile')
                ->assertDontSee('settings.navigation_password')
                ->assertDontSee('settings.navigation_appearance');
        });
    }

    /**
     * Test that forgot password page displays translated text.
     */
    public function test_forgot_password_displays_translated_text(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/forgot-password')
                ->waitForText('Forgot', 10)
                ->assertSee('Forgot your password')
                ->assertSee('Email address')
                // Ensure translation keys are NOT visible
                ->assertDontSee('auth.forgot_password_title')
                ->assertDontSee('auth.forgot_password_email_label');
        });
    }

    /**
     * Test that no raw translation keys are visible in common UI elements.
     */
    public function test_no_translation_keys_in_ui(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard');

            // Get the entire page text
            $pageText = $browser->text('body');

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
                $this->assertStringNotContainsString(
                    $pattern,
                    $pageText,
                    "Found translation key pattern '{$pattern}' in rendered UI"
                );
            }
        });
    }

    /**
     * Test that input placeholders are translated.
     */
    public function test_input_placeholders_are_translated(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->waitFor('input[type="email"]', 10);

            // Check that email input has translated placeholder
            $emailPlaceholder = $browser->attribute('input[type="email"]', 'placeholder');
            $this->assertNotEmpty($emailPlaceholder, 'Email input placeholder is empty');
            $this->assertStringNotContainsString(
                'auth.login_email_placeholder',
                $emailPlaceholder,
                'Email placeholder contains raw translation key'
            );

            // Should contain something like "email@example.com"
            $this->assertStringContainsString('@', $emailPlaceholder);
        });
    }

    /**
     * Test that button text is translated.
     */
    public function test_button_text_is_translated(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->waitFor('button[type="submit"]', 10);

            $buttonText = $browser->text('button[type="submit"]');

            $this->assertNotEmpty($buttonText, 'Submit button text is empty');
            $this->assertStringNotContainsString(
                'auth.login_submit_button',
                $buttonText,
                'Submit button contains raw translation key'
            );

            // Should see "Log in" or similar
            $this->assertStringContainsString('Log in', $buttonText);
        });
    }

    /**
     * Test that page titles use translations.
     */
    public function test_page_titles_are_translated(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->waitFor('title', 10);

            $title = $browser->driver->getTitle();

            $this->assertNotEmpty($title, 'Page title is empty');
            $this->assertStringNotContainsString(
                'auth.',
                $title,
                'Page title contains raw translation key'
            );
        });
    }

    /**
     * Test that navigation menu items are translated.
     */
    public function test_navigation_is_translated(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Dashboard', 10)
                // Check navigation items
                ->assertSee('Dashboard')
                ->assertSee('Lineups')
                ->assertSee('Settings')
                // Ensure translation keys are NOT visible
                ->assertDontSee('common.navigation_dashboard')
                ->assertDontSee('common.navigation_lineups')
                ->assertDontSee('common.navigation_settings');
        });
    }

    /**
     * Test that error messages would be translated if displayed.
     */
    public function test_validation_errors_are_translated(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->type('email', 'invalid-email')
                ->type('password', 'wrong')
                ->press('Log in')
                ->waitFor('.text-destructive, .error, [role="alert"]', 10);

            // Get error message text
            $pageText = $browser->text('body');

            // Should NOT contain raw translation keys
            $this->assertStringNotContainsString('auth.', $pageText);
            $this->assertStringNotContainsString('validation.', $pageText);
        });
    }
}
