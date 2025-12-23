# i18n Architecture Design Document
## Artist-Tree Internationalization Implementation

**Project**: Artist-Tree (Laravel 12 + Inertia.js v2 + Vue 3)
**Package**: laravel-vue-i18n v2.8.0
**Scope**: English only (MVP), PHP translation files, ~19 page components
**Date**: 2025-12-23

---

## 1. Translation File Structure

### Directory Organization

```
/lang/en/
├── auth.php          # Authentication pages (login, register, password reset, 2FA, email verification)
├── dashboard.php     # Dashboard page content
├── lineups.php       # Lineup management (index, show, create/edit)
├── artists.php       # Artist profiles and search
├── settings.php      # Settings pages (profile, password, 2FA, appearance, scoring)
├── common.php        # Shared UI elements (buttons, actions, errors, navigation)
└── validation.php    # Custom validation messages (Laravel default, extend if needed)
```

### Namespace Strategy

- **Domain-based namespacing**: Each file represents a domain area
- **Flat structure for MVP**: No subdirectories (keep simple for 19 pages)
- **Laravel-native PHP arrays**: Standard Laravel translation format

### Key Naming Pattern

**Format**: `{file}.{section}_{element}_{type}`

**Examples**:
- `auth.login_title` - Page title
- `auth.login_email_label` - Form field label
- `auth.login_email_placeholder` - Form field placeholder
- `auth.login_submit_button` - Button text
- `common.action_save` - Reusable button text
- `common.action_cancel` - Reusable button text
- `dashboard.hero_title` - Section heading
- `dashboard.hero_subtitle` - Section subheading

**Naming Conventions**:
- Use **underscores** for separators (Laravel convention)
- Use **singular** nouns (`artist` not `artists` in keys)
- Prefix with section/context (`login_`, `hero_`, `form_`)
- Suffix with element type when helpful (`_title`, `_label`, `_placeholder`, `_button`)

---

## 2. laravel-vue-i18n Configuration

### Installation Verification

Package already installed in `/package.json`:
```json
{
  "dependencies": {
    "laravel-vue-i18n": "^2.8.0"
  }
}
```

### Configuration in `/resources/js/app.ts`

**REPLACE** the existing setup function with:

```typescript
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue } from 'laravel-vue-i18n';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18nVue, {
                resolve: async (lang: string) => {
                    // Load PHP translation files from Laravel's lang directory
                    const langs = import.meta.glob<{ default: Record<string, any> }>(
                        '../../lang/*.php',
                        { eager: false }
                    );
                    const langModule = await langs[`../../lang/${lang}.php`]();
                    return langModule.default;
                },
                fallbackLang: 'en',
                onLoad: (lang: string) => {
                    console.log(`[i18n] Loaded language: ${lang}`);
                }
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
```

### Vite Configuration Update

**Add** to `/vite.config.ts` to support PHP file imports:

```typescript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import wayfinder from '@laravel/vite-plugin-wayfinder';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.ts',
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder(),
        {
            name: 'php-loader',
            transform(code, id) {
                // Transform PHP files to JSON for Vite
                if (id.endsWith('.php') && id.includes('/lang/')) {
                    // Extract the array from PHP return statement
                    const match = code.match(/return\s+(\[[\s\S]*\]);/);
                    if (match) {
                        // Convert PHP array syntax to JSON
                        const phpArray = match[1];
                        const json = phpArray
                            .replace(/=>/g, ':')
                            .replace(/'/g, '"');
                        return `export default ${json}`;
                    }
                }
            },
        },
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
```

### Alternative: Use JSON Files (Simpler)

If PHP file loading proves complex, use JSON files instead:

**Directory**: `/lang/en.json` (single file) OR `/lang/en/*.json` (multiple files)

**Vite config** (simpler):
```typescript
.use(i18nVue, {
    resolve: async (lang: string) => {
        const langs = import.meta.glob<{ default: Record<string, any> }>(
            '../../lang/*.json',
            { eager: false }
        );
        const langModule = await langs[`../../lang/${lang}.json`]();
        return langModule.default;
    }
})
```

**Recommendation**: Start with JSON for simplicity, migrate to PHP later if needed.

---

## 3. Translation Key Conventions

### File: `/lang/en/auth.json` (or `auth.php`)

```json
{
  "login_title": "Log in to your account",
  "login_subtitle": "Enter your email and password below to log in",
  "login_email_label": "Email address",
  "login_email_placeholder": "email@example.com",
  "login_password_label": "Password",
  "login_password_placeholder": "Password",
  "login_remember_label": "Remember me",
  "login_forgot_password_link": "Forgot password?",
  "login_submit_button": "Log in",
  "login_no_account_text": "Don't have an account?",
  "login_signup_link": "Sign up",

  "register_title": "Create an account",
  "register_subtitle": "Enter your information to get started",
  "register_name_label": "Full name",
  "register_name_placeholder": "John Doe",
  "register_email_label": "Email address",
  "register_email_placeholder": "email@example.com",
  "register_password_label": "Password",
  "register_password_placeholder": "Password",
  "register_password_confirmation_label": "Confirm password",
  "register_password_confirmation_placeholder": "Confirm password",
  "register_submit_button": "Sign up",
  "register_have_account_text": "Already have an account?",
  "register_login_link": "Log in",

  "forgot_password_title": "Forgot your password?",
  "forgot_password_subtitle": "Enter your email address and we'll send you a reset link",
  "forgot_password_email_label": "Email address",
  "forgot_password_email_placeholder": "email@example.com",
  "forgot_password_submit_button": "Send reset link",
  "forgot_password_back_to_login": "Back to login",

  "reset_password_title": "Reset your password",
  "reset_password_subtitle": "Enter your new password below",
  "reset_password_email_label": "Email address",
  "reset_password_password_label": "New password",
  "reset_password_password_confirmation_label": "Confirm new password",
  "reset_password_submit_button": "Reset password",

  "verify_email_title": "Verify your email",
  "verify_email_message": "Thanks for signing up! Please verify your email address by clicking the link we sent you.",
  "verify_email_resend_button": "Resend verification email",
  "verify_email_logout_link": "Log out",

  "two_factor_title": "Two-factor authentication",
  "two_factor_subtitle": "Enter the code from your authenticator app",
  "two_factor_code_label": "Authentication code",
  "two_factor_code_placeholder": "000000",
  "two_factor_recovery_link": "Use a recovery code",
  "two_factor_submit_button": "Verify",

  "confirm_password_title": "Confirm your password",
  "confirm_password_subtitle": "This is a secure area. Please confirm your password before continuing.",
  "confirm_password_password_label": "Password",
  "confirm_password_submit_button": "Confirm"
}
```

### File: `/lang/en/dashboard.json`

```json
{
  "page_title": "Dashboard",
  "hero_title": "Build Your Dream Lineup",
  "hero_subtitle": "Search for artists, compare metrics, and create the perfect festival lineup.",
  "search_placeholder": "Search for artists...",
  "lineups_section_title": "Your Lineups",
  "lineups_section_subtitle": "Manage your festival lineups",
  "lineups_view_all_button": "View All",
  "trending_section_title": "Trending Artists"
}
```

### File: `/lang/en/lineups.json`

```json
{
  "index_page_title": "Lineups",
  "index_create_button": "Create Lineup",
  "index_empty_state_title": "No lineups yet",
  "index_empty_state_message": "Create your first lineup to get started",

  "show_page_title": "Lineup",
  "show_edit_button": "Edit",
  "show_delete_button": "Delete",
  "show_add_artist_button": "Add Artist",
  "show_tier_headliner": "Headliner",
  "show_tier_sub_headliner": "Sub-Headliner",
  "show_tier_mid_tier": "Mid-Tier",
  "show_tier_undercard": "Undercard",
  "show_artist_count": "{count} artists",
  "show_total_score": "Total Score: {score}",

  "create_title": "Create Lineup",
  "create_name_label": "Lineup name",
  "create_name_placeholder": "Summer Music Festival 2025",
  "create_description_label": "Description",
  "create_description_placeholder": "A brief description of your lineup...",
  "create_submit_button": "Create",
  "create_cancel_button": "Cancel"
}
```

### File: `/lang/en/artists.json`

```json
{
  "show_page_title": "Artist Profile",
  "show_metrics_title": "Metrics",
  "show_spotify_listeners_label": "Spotify Monthly Listeners",
  "show_spotify_popularity_label": "Spotify Popularity",
  "show_youtube_subscribers_label": "YouTube Subscribers",
  "show_score_label": "Score",
  "show_genres_label": "Genres",
  "show_add_to_lineup_button": "Add to Lineup",
  "show_refresh_metrics_button": "Refresh Metrics",

  "search_page_title": "Search Artists",
  "search_input_placeholder": "Search by artist name...",
  "search_results_count": "{count} results",
  "search_no_results_title": "No artists found",
  "search_no_results_message": "Try a different search query"
}
```

### File: `/lang/en/settings.json`

```json
{
  "page_title": "Settings",
  "navigation_profile": "Profile",
  "navigation_password": "Password",
  "navigation_two_factor": "Two-Factor Authentication",
  "navigation_appearance": "Appearance",
  "navigation_scoring": "Scoring Weights",

  "profile_title": "Profile Information",
  "profile_subtitle": "Update your account profile information",
  "profile_name_label": "Name",
  "profile_email_label": "Email",
  "profile_save_button": "Save",
  "profile_success_message": "Profile updated successfully",

  "password_title": "Change Password",
  "password_subtitle": "Ensure your account is using a strong password",
  "password_current_label": "Current password",
  "password_new_label": "New password",
  "password_confirmation_label": "Confirm new password",
  "password_save_button": "Update password",
  "password_success_message": "Password updated successfully",

  "two_factor_title": "Two-Factor Authentication",
  "two_factor_subtitle": "Add additional security to your account",
  "two_factor_enable_button": "Enable",
  "two_factor_disable_button": "Disable",
  "two_factor_qr_code_label": "Scan this QR code with your authenticator app",
  "two_factor_recovery_codes_title": "Recovery Codes",
  "two_factor_recovery_codes_message": "Store these recovery codes in a secure location",

  "appearance_title": "Appearance",
  "appearance_subtitle": "Customize how the app looks",
  "appearance_theme_label": "Theme",
  "appearance_theme_light": "Light",
  "appearance_theme_dark": "Dark",
  "appearance_theme_system": "System",

  "scoring_title": "Scoring Weights",
  "scoring_subtitle": "Customize how artist scores are calculated",
  "scoring_preset_label": "Preset",
  "scoring_preset_balanced": "Balanced",
  "scoring_preset_streaming_focused": "Streaming Focused",
  "scoring_preset_social_media_focused": "Social Media Focused",
  "scoring_spotify_listeners_label": "Spotify Monthly Listeners",
  "scoring_spotify_popularity_label": "Spotify Popularity",
  "scoring_youtube_subscribers_label": "YouTube Subscribers",
  "scoring_weight_total_error": "Weights must total 100%",
  "scoring_save_button": "Save Weights",
  "scoring_reset_button": "Reset to Preset"
}
```

### File: `/lang/en/common.json`

```json
{
  "action_save": "Save",
  "action_cancel": "Cancel",
  "action_delete": "Delete",
  "action_edit": "Edit",
  "action_create": "Create",
  "action_view": "View",
  "action_close": "Close",
  "action_back": "Back",
  "action_next": "Next",
  "action_previous": "Previous",
  "action_submit": "Submit",
  "action_search": "Search",
  "action_filter": "Filter",
  "action_sort": "Sort",
  "action_export": "Export",
  "action_import": "Import",

  "status_loading": "Loading...",
  "status_saving": "Saving...",
  "status_deleting": "Deleting...",
  "status_processing": "Processing...",

  "error_generic": "Something went wrong. Please try again.",
  "error_network": "Network error. Please check your connection.",
  "error_unauthorized": "You are not authorized to perform this action.",
  "error_not_found": "The requested resource was not found.",
  "error_validation": "Please check your input and try again.",

  "success_saved": "Saved successfully",
  "success_deleted": "Deleted successfully",
  "success_created": "Created successfully",
  "success_updated": "Updated successfully",

  "confirm_delete_title": "Are you sure?",
  "confirm_delete_message": "This action cannot be undone.",
  "confirm_delete_confirm": "Yes, delete",
  "confirm_delete_cancel": "No, keep it",

  "pagination_previous": "Previous",
  "pagination_next": "Next",
  "pagination_showing": "Showing {from} to {to} of {total} results",

  "breadcrumb_home": "Home",
  "breadcrumb_dashboard": "Dashboard",

  "navigation_dashboard": "Dashboard",
  "navigation_lineups": "Lineups",
  "navigation_artists": "Artists",
  "navigation_search": "Search",
  "navigation_settings": "Settings",
  "navigation_logout": "Log out"
}
```

---

## 4. Component Refactoring Guidelines

### Pattern 1: Simple Text Replacement

**Before**:
```vue
<h1>Log in to your account</h1>
```

**After**:
```vue
<h1>{{ $t('auth.login_title') }}</h1>
```

### Pattern 2: Attribute Bindings

**Before**:
```vue
<Input
  type="email"
  placeholder="email@example.com"
/>
```

**After**:
```vue
<Input
  type="email"
  :placeholder="$t('auth.login_email_placeholder')"
/>
```

### Pattern 3: Dynamic Content with Parameters

**Before**:
```vue
<p>Showing 10 to 20 of 100 results</p>
```

**After**:
```vue
<p>{{ $t('common.pagination_showing', { from: 10, to: 20, total: 100 }) }}</p>
```

**Translation file**:
```json
{
  "pagination_showing": "Showing :from to :to of :total results"
}
```

### Pattern 4: Pluralization

**Before**:
```vue
<span>{{ count }} artist{{ count !== 1 ? 's' : '' }}</span>
```

**After**:
```vue
<span>{{ $t('lineups.show_artist_count', { count: count }) }}</span>
```

**Translation file** (using Laravel's pluralization):
```json
{
  "show_artist_count": "{0} No artists|{1} 1 artist|[2,*] :count artists"
}
```

### Pattern 5: Conditional Text

**Before**:
```vue
<Button>{{ processing ? 'Saving...' : 'Save' }}</Button>
```

**After**:
```vue
<Button>{{ processing ? $t('common.status_saving') : $t('common.action_save') }}</Button>
```

### Pattern 6: Head Title (Inertia)

**Before**:
```vue
<Head title="Dashboard - Artist-Tree" />
```

**After**:
```vue
<Head :title="$t('dashboard.page_title')" />
```

### Pattern 7: Validation Errors (Backend)

**No changes needed** - Laravel's validation messages are already in `/lang/en/validation.php`

### Pattern 8: TypeScript Support

Create `/resources/js/types/i18n.d.ts`:

```typescript
import { ComponentCustomProperties } from 'vue';

declare module '@vue/runtime-core' {
  interface ComponentCustomProperties {
    $t: (key: string, params?: Record<string, any>) => string;
    trans: (key: string, params?: Record<string, any>) => string;
    transChoice: (key: string, count: number, params?: Record<string, any>) => string;
  }
}
```

---

## 5. Testing Strategy

### Test Categories

#### 5.1 Unit Tests (Pest - PHP)

**File**: `/tests/Unit/TranslationFilesTest.php`

```php
<?php

use Illuminate\Support\Facades\File;

describe('Translation Files', function () {
    it('has all required translation files', function () {
        $requiredFiles = [
            'auth',
            'dashboard',
            'lineups',
            'artists',
            'settings',
            'common',
        ];

        foreach ($requiredFiles as $file) {
            $filePath = lang_path("en/{$file}.json");
            expect(File::exists($filePath))
                ->toBeTrue("Translation file en/{$file}.json is missing");
        }
    });

    it('has valid JSON in all translation files', function () {
        $files = File::glob(lang_path('en/*.json'));

        foreach ($files as $file) {
            $content = File::get($file);
            $decoded = json_decode($content, true);

            expect(json_last_error())
                ->toBe(JSON_ERROR_NONE, "Invalid JSON in {$file}: " . json_last_error_msg());
            expect($decoded)->toBeArray();
        }
    });

    it('has no duplicate keys across translation files', function () {
        $files = File::glob(lang_path('en/*.json'));
        $allKeys = [];

        foreach ($files as $file) {
            $content = json_decode(File::get($file), true);
            $fileName = basename($file, '.json');

            foreach (array_keys($content) as $key) {
                $fullKey = "{$fileName}.{$key}";
                expect($allKeys)->not->toContain($fullKey, "Duplicate key found: {$fullKey}");
                $allKeys[] = $fullKey;
            }
        }
    });

    it('has no empty translation strings', function () {
        $files = File::glob(lang_path('en/*.json'));

        foreach ($files as $file) {
            $content = json_decode(File::get($file), true);
            $fileName = basename($file, '.json');

            foreach ($content as $key => $value) {
                expect($value)
                    ->not->toBeEmpty("{$fileName}.{$key} has empty translation");
            }
        }
    });
});
```

#### 5.2 Feature Tests (Pest - PHP + Vue)

**File**: `/tests/Feature/I18nIntegrationTest.php`

```php
<?php

use Illuminate\Support\Facades\Lang;

describe('i18n Integration', function () {
    it('loads translation files correctly', function () {
        expect(Lang::has('auth.login_title'))->toBeTrue();
        expect(Lang::has('dashboard.hero_title'))->toBeTrue();
        expect(Lang::has('common.action_save'))->toBeTrue();
    });

    it('returns correct translation for existing key', function () {
        $translation = __('auth.login_title');
        expect($translation)->not->toBe('auth.login_title');
        expect($translation)->toBeString();
    });

    it('falls back to key when translation missing', function () {
        $translation = __('nonexistent.key');
        expect($translation)->toBe('nonexistent.key');
    });

    it('handles parameter interpolation', function () {
        $translation = __('common.pagination_showing', [
            'from' => 1,
            'to' => 10,
            'total' => 100
        ]);

        expect($translation)->toContain('1');
        expect($translation)->toContain('10');
        expect($translation)->toContain('100');
    });
});
```

#### 5.3 Browser Tests (Playwright)

**File**: `/tests/Browser/TranslatedUiTest.php` (using Laravel Dusk) OR `/tests/e2e/i18n.spec.ts` (using Playwright directly)

```typescript
import { test, expect } from '@playwright/test';

test.describe('i18n UI Translation', () => {
    test('login page displays translated text', async ({ page }) => {
        await page.goto('/login');

        // Check for translation keys NOT appearing in UI
        const bodyText = await page.textContent('body');
        expect(bodyText).not.toContain('auth.login_title');
        expect(bodyText).not.toContain('auth.login_email_label');

        // Check for actual translated text
        await expect(page.locator('h1')).toContainText('Log in to your account');
        await expect(page.getByLabel('Email address')).toBeVisible();
        await expect(page.getByPlaceholder('email@example.com')).toBeVisible();
    });

    test('dashboard displays translated text', async ({ page }) => {
        // Assuming user is logged in
        await page.goto('/dashboard');

        // Check no translation keys visible
        const bodyText = await page.textContent('body');
        expect(bodyText).not.toContain('dashboard.hero_title');
        expect(bodyText).not.toContain('dashboard.hero_subtitle');

        // Check translated content
        await expect(page.locator('h1')).toContainText('Build Your Dream Lineup');
        await expect(page.getByPlaceholder('Search for artists...')).toBeVisible();
    });

    test('no missing translation keys in console', async ({ page }) => {
        const consoleErrors: string[] = [];
        page.on('console', msg => {
            if (msg.type() === 'error' || msg.type() === 'warning') {
                consoleErrors.push(msg.text());
            }
        });

        await page.goto('/dashboard');

        // Check for i18n warnings
        const i18nErrors = consoleErrors.filter(err =>
            err.includes('translation') || err.includes('i18n')
        );

        expect(i18nErrors).toHaveLength(0);
    });
});
```

#### 5.4 Component Tests (Vitest - Optional)

**File**: `/resources/js/__tests__/i18n.spec.ts`

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18nVue } from 'laravel-vue-i18n';

describe('i18n Plugin', () => {
    it('provides $t function to components', async () => {
        const TestComponent = {
            template: '<div>{{ $t("auth.login_title") }}</div>'
        };

        const wrapper = mount(TestComponent, {
            global: {
                plugins: [[i18nVue, {
                    resolve: async () => ({
                        auth: { login_title: 'Log in to your account' }
                    })
                }]]
            }
        });

        expect(wrapper.text()).toBe('Log in to your account');
    });

    it('handles missing keys gracefully', async () => {
        const TestComponent = {
            template: '<div>{{ $t("missing.key") }}</div>'
        };

        const wrapper = mount(TestComponent, {
            global: {
                plugins: [[i18nVue, {
                    resolve: async () => ({ auth: {} })
                }]]
            }
        });

        // Should fallback to key name
        expect(wrapper.text()).toBe('missing.key');
    });
});
```

### Test Execution Sequence

1. **Unit Tests**: Verify translation files are valid
2. **Feature Tests**: Verify Laravel can load translations
3. **Browser Tests**: Verify Vue components display translated text
4. **Component Tests** (optional): Verify $t() function works in isolation

### Success Criteria Checklist

- [ ] All translation files exist in `/lang/en/`
- [ ] All translation files have valid JSON syntax
- [ ] No duplicate keys across files
- [ ] No empty translation strings
- [ ] Laravel can load translations via `__()` helper
- [ ] Vue components can access translations via `$t()`
- [ ] No raw translation keys visible in rendered UI
- [ ] No i18n errors/warnings in browser console
- [ ] All automated tests pass

---

## 6. Implementation Checklist

### Phase 1: Setup (vue-laravel-developer)
- [ ] Update `/resources/js/app.ts` with i18nVue configuration
- [ ] Update `/vite.config.ts` to support JSON imports (or PHP if using PHP files)
- [ ] Create `/resources/js/types/i18n.d.ts` for TypeScript support
- [ ] Test that Vite builds successfully: `npm run build`

### Phase 2: Translation Files (vue-laravel-developer)
- [ ] Create `/lang/en/auth.json` with all auth-related translations
- [ ] Create `/lang/en/dashboard.json` with dashboard translations
- [ ] Create `/lang/en/lineups.json` with lineup management translations
- [ ] Create `/lang/en/artists.json` with artist profile translations
- [ ] Create `/lang/en/settings.json` with settings page translations
- [ ] Create `/lang/en/common.json` with shared UI translations
- [ ] Validate all JSON files (use `json_decode` or online validator)

### Phase 3: Component Refactoring (vue-laravel-developer)

**Priority 1 - Auth Pages**:
- [ ] `/resources/js/pages/auth/Login.vue`
- [ ] `/resources/js/pages/auth/Register.vue`
- [ ] `/resources/js/pages/auth/ForgotPassword.vue`
- [ ] `/resources/js/pages/auth/ResetPassword.vue`
- [ ] `/resources/js/pages/auth/VerifyEmail.vue`
- [ ] `/resources/js/pages/auth/TwoFactorChallenge.vue`
- [ ] `/resources/js/pages/auth/ConfirmPassword.vue`

**Priority 2 - Main Pages**:
- [ ] `/resources/js/pages/Dashboard.vue`
- [ ] `/resources/js/pages/Lineups/Index.vue`
- [ ] `/resources/js/pages/Lineups/Show.vue`
- [ ] `/resources/js/pages/Artist/Show.vue`
- [ ] `/resources/js/pages/Search.vue`

**Priority 3 - Settings Pages**:
- [ ] `/resources/js/pages/Settings.vue`
- [ ] `/resources/js/pages/settings/Profile.vue`
- [ ] `/resources/js/pages/settings/Password.vue`
- [ ] `/resources/js/pages/settings/TwoFactor.vue`
- [ ] `/resources/js/pages/settings/Appearance.vue`
- [ ] `/resources/js/pages/settings/Scoring.vue`

**Priority 4 - Layouts**:
- [ ] `/resources/js/layouts/AuthLayout.vue`
- [ ] `/resources/js/layouts/MainLayout.vue`

### Phase 4: Testing (test-engineer)
- [ ] Write unit tests in `/tests/Unit/TranslationFilesTest.php`
- [ ] Write feature tests in `/tests/Feature/I18nIntegrationTest.php`
- [ ] Write browser tests in `/tests/Browser/TranslatedUiTest.php`
- [ ] Run all tests: `php artisan test`
- [ ] Run browser tests: `php artisan dusk` or `npx playwright test`
- [ ] Manual QA: Check for missing keys in browser console

---

## 7. Edge Cases & Considerations

### Handling Dynamic Content
- **User-generated content** (lineup names, artist names): Do NOT translate
- **API responses**: Backend returns English data, no translation needed
- **Dates/times**: Use `Intl.DateTimeFormat` for localization (future enhancement)
- **Numbers**: Use `Intl.NumberFormat` for localization (future enhancement)

### SSR Compatibility (Inertia SSR)
- laravel-vue-i18n supports SSR out of the box
- Ensure translation files load on server-side render
- Test with `npm run build:ssr` if using SSR

### Performance Optimization
- Translation files loaded asynchronously (Vite code-splitting)
- Cached after first load
- Minimal performance impact (<50ms initial load)

### Fallback Strategy
- Missing keys display the key itself (e.g., `auth.login_title`)
- Browser console warning for missing keys (development only)
- Never show raw error messages to users

### Future Multi-Language Support
- **Current**: English only (`/lang/en/*.json`)
- **Future**: Add `/lang/es/*.json`, `/lang/fr/*.json`, etc.
- **Language switcher**: Add to settings page
- **User preference**: Store in database (`users.locale` column)
- **Backend**: Use `App::setLocale($user->locale)` in middleware

---

## 8. Risk Mitigation

### Risk: Missing Translation Keys Break UI
**Mitigation**:
- Comprehensive tests verify all keys exist
- Fallback to key name if missing (graceful degradation)
- Browser console warnings in development

### Risk: Performance Impact from Loading Translations
**Mitigation**:
- Lazy-load translation files via Vite async imports
- Cache translations after first load
- Measure performance impact (target: <100ms)

### Risk: TypeScript Errors from $t() Helper
**Mitigation**:
- Proper type declarations in `/resources/js/types/i18n.d.ts`
- Update `tsconfig.json` to include type definitions

### Risk: Vite Build Errors with PHP Files
**Mitigation**:
- Use JSON files instead of PHP for simplicity
- If PHP required, use custom Vite plugin to transform PHP to JSON

### Risk: Breaking Existing Functionality
**Mitigation**:
- TDD approach: Write tests first
- Refactor incrementally (one page at a time)
- Run tests after each refactor
- Manual QA testing before deployment

---

## 9. Decision: JSON vs PHP Translation Files

### Recommendation: Use JSON Files

**Rationale**:
1. **Simpler Vite integration**: No custom plugin needed
2. **Frontend-friendly**: Native JSON parsing in JavaScript
3. **Better IDE support**: Most editors have JSON validation
4. **Faster builds**: No PHP-to-JSON transformation step
5. **Standard laravel-vue-i18n usage**: Most examples use JSON

### PHP Files (Alternative)
**Pros**: Laravel-native, supports advanced pluralization, better for server-side rendering
**Cons**: Requires custom Vite plugin, more complex setup

### Final Decision: JSON
- Create `/lang/en/*.json` files
- Migrate to PHP later if needed (easy migration path)

---

## 10. Next Steps

1. **User approval** of this architecture design
2. **test-engineer**: Write comprehensive TDD tests (all failing initially)
3. **vue-laravel-developer**: Implement configuration and refactor components
4. **test-engineer**: Validate all tests pass (GREEN phase)
5. **Deploy to staging** and perform manual QA
6. **Production deployment** once all tests pass

---

**Document Status**: Ready for implementation
**Last Updated**: 2025-12-23
**Author**: solution-architect agent (simulated by project-manager)
