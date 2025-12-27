# i18n Implementation Guide for vue-laravel-developer

## Context
You are implementing internationalization for the Artist-Tree application using `laravel-vue-i18n` v2.8.0 (already installed).

## Objectives
1. Configure `laravel-vue-i18n` in `/resources/js/app.ts`
2. Create 6 JSON translation files in `/lang/en/`
3. Refactor ~19 Vue page components to use `$t()` helper
4. Add TypeScript support for i18n

## Step 1: Configure laravel-vue-i18n in app.ts

**File**: `/resources/js/app.ts`

**Current code** (lines 1-30):
```typescript
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
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
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
```

**Updated code** (ADD i18nVue import and configuration):
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
                lang: 'en',
                resolve: async (lang: string) => {
                    const langs = import.meta.glob<{ default: Record<string, any> }>(
                        '../../lang/*.json',
                        { eager: false }
                    );
                    return await langs[`../../lang/${lang}.json`]();
                },
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

**Changes**:
- Import `i18nVue` from `'laravel-vue-i18n'`
- Add `.use(i18nVue, { ... })` before `.mount(el)`
- Configure language as `'en'`
- Use Vite's `import.meta.glob` to dynamically load JSON files

---

## Step 2: Create TypeScript Type Declarations

**File**: `/resources/js/types/i18n.d.ts` (CREATE NEW)

```typescript
import { ComponentCustomProperties } from 'vue';

declare module '@vue/runtime-core' {
  interface ComponentCustomProperties {
    $t: (key: string, replacements?: Record<string, any>) => string;
    trans: (key: string, replacements?: Record<string, any>) => string;
    transChoice: (key: string, number: number, replacements?: Record<string, any>) => string;
  }
}

export {};
```

---

## Step 3: Create Translation Files

Create directory structure:
```
/lang/
└── en/
    ├── auth.json
    ├── dashboard.json
    ├── lineups.json
    ├── artists.json
    ├── settings.json
    └── common.json
```

**File**: `/lang/en.json` (CREATE NEW - single file approach for all translations)

```json
{
  "auth": {
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
  },

  "dashboard": {
    "page_title": "Dashboard",
    "hero_title": "Build Your Dream Lineup",
    "hero_subtitle": "Search for artists, compare metrics, and create the perfect festival lineup.",
    "search_placeholder": "Search for artists...",
    "lineups_section_title": "Your Lineups",
    "lineups_section_subtitle": "Manage your festival lineups",
    "lineups_view_all_button": "View All",
    "trending_section_title": "Trending Artists"
  },

  "lineups": {
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
  },

  "artists": {
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
  },

  "settings": {
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
  },

  "common": {
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
}
```

**IMPORTANT**: Use flat JSON structure with nested namespaces as shown above. laravel-vue-i18n will access keys like `$t('auth.login_title')`.

---

## Step 4: Refactor Vue Components

### Priority 1: Auth Pages (7 files)

#### 4.1: `/resources/js/pages/auth/Login.vue`

**Search for hardcoded strings and replace with `$t()` calls:**

| Before | After |
|--------|-------|
| `title="Log in to your account"` | `:title="$t('auth.login_title')"` |
| `description="Enter your email and password below to log in"` | `:description="$t('auth.login_subtitle')"` |
| `<Label for="email">Email address</Label>` | `<Label for="email">{{ $t('auth.login_email_label') }}</Label>` |
| `placeholder="email@example.com"` | `:placeholder="$t('auth.login_email_placeholder')"` |
| `<Label for="password">Password</Label>` | `<Label for="password">{{ $t('auth.login_password_label') }}</Label>` |
| `placeholder="Password"` | `:placeholder="$t('auth.login_password_placeholder')"` |
| `Forgot password?` | `{{ $t('auth.login_forgot_password_link') }}` |
| `<span>Remember me</span>` | `<span>{{ $t('auth.login_remember_label') }}</span>` |
| `Log in` (button) | `{{ $t('auth.login_submit_button') }}` |
| `Don't have an account?` | `{{ $t('auth.login_no_account_text') }}` |
| `Sign up` (link) | `{{ $t('auth.login_signup_link') }}` |
| `<Head title="Log in" />` | `<Head :title="$t('auth.login_title')" />` |

#### 4.2-4.7: Other Auth Pages
Apply the same pattern to:
- `/resources/js/pages/auth/Register.vue` → Use `auth.register_*` keys
- `/resources/js/pages/auth/ForgotPassword.vue` → Use `auth.forgot_password_*` keys
- `/resources/js/pages/auth/ResetPassword.vue` → Use `auth.reset_password_*` keys
- `/resources/js/pages/auth/VerifyEmail.vue` → Use `auth.verify_email_*` keys
- `/resources/js/pages/auth/TwoFactorChallenge.vue` → Use `auth.two_factor_*` keys
- `/resources/js/pages/auth/ConfirmPassword.vue` → Use `auth.confirm_password_*` keys

---

### Priority 2: Main Pages (5 files)

#### 4.8: `/resources/js/pages/Dashboard.vue`

| Before | After |
|--------|-------|
| `<Head title="Dashboard - Artist-Tree" />` | `<Head :title="$t('dashboard.page_title')" />` |
| `Build Your Dream Lineup` | `{{ $t('dashboard.hero_title') }}` |
| `Search for artists, compare metrics, and create the perfect festival lineup.` | `{{ $t('dashboard.hero_subtitle') }}` |
| `placeholder="Search for artists..."` | `:placeholder="$t('dashboard.search_placeholder')"` |
| `<h2>Your Lineups</h2>` | `<h2>{{ $t('dashboard.lineups_section_title') }}</h2>` |
| `<p>Manage your festival lineups</p>` | `<p>{{ $t('dashboard.lineups_section_subtitle') }}</p>` |
| `View All` (button) | `{{ $t('dashboard.lineups_view_all_button') }}` |
| `<h2>Trending Artists</h2>` | `<h2>{{ $t('dashboard.trending_section_title') }}</h2>` |

#### 4.9-4.12: Other Main Pages
Apply pattern to:
- `/resources/js/pages/Lineups/Index.vue` → Use `lineups.index_*` keys
- `/resources/js/pages/Lineups/Show.vue` → Use `lineups.show_*` keys
- `/resources/js/pages/Artist/Show.vue` → Use `artists.show_*` keys
- `/resources/js/pages/Search.vue` → Use `artists.search_*` keys

---

### Priority 3: Settings Pages (6 files)

#### 4.13: `/resources/js/pages/Settings.vue`

| Before | After |
|--------|-------|
| `<Head title="Settings" />` | `<Head :title="$t('settings.page_title')" />` |
| Navigation items (Profile, Password, etc.) | Use `settings.navigation_*` keys |

#### 4.14-4.18: Settings Subpages
- `/resources/js/pages/settings/Profile.vue` → Use `settings.profile_*` keys
- `/resources/js/pages/settings/Password.vue` → Use `settings.password_*` keys
- `/resources/js/pages/settings/TwoFactor.vue` → Use `settings.two_factor_*` keys
- `/resources/js/pages/settings/Appearance.vue` → Use `settings.appearance_*` keys
- `/resources/js/pages/settings/Scoring.vue` → Use `settings.scoring_*` keys

---

### Priority 4: Layouts (2 files)

#### 4.19: `/resources/js/layouts/AuthLayout.vue`
- Extract any hardcoded UI text to `common.*` keys

#### 4.20: `/resources/js/layouts/MainLayout.vue`
- Navigation menu items → Use `common.navigation_*` keys
- Breadcrumbs → Use `common.breadcrumb_*` keys

---

## Step 5: Handle Special Cases

### Dynamic Content with Parameters
```vue
<!-- Before -->
<span>Showing 10 to 20 of 100 results</span>

<!-- After -->
<span>{{ $t('common.pagination_showing', { from: 10, to: 20, total: 100 }) }}</span>
```

### Conditional Text
```vue
<!-- Before -->
<Button>{{ processing ? 'Saving...' : 'Save' }}</Button>

<!-- After -->
<Button>{{ processing ? $t('common.status_saving') : $t('common.action_save') }}</Button>
```

### Component Props with Translations
```vue
<!-- Before -->
<AuthBase
    title="Log in to your account"
    description="Enter your email and password below to log in"
>

<!-- After -->
<AuthBase
    :title="$t('auth.login_title')"
    :description="$t('auth.login_subtitle')"
>
```

---

## Step 6: Verification Checklist

After refactoring each component:
- [ ] No hardcoded English text in templates (use search: `>"[A-Z]`)
- [ ] All `placeholder` attributes use `:placeholder="$t(...)"`
- [ ] All `<Head title="..."` use `:title="$t(...)"`
- [ ] All button text uses `{{ $t(...) }}`
- [ ] Component builds without errors: `npm run build`
- [ ] No console warnings about missing translation keys

---

## Step 7: Build and Test

```bash
# Build assets
npm run build

# Run tests
php artisan test --filter=Translation
php artisan test --filter=I18n

# Run browser tests
php artisan dusk --filter=I18nTranslation

# Manual testing
npm run dev
# Visit pages and check for:
# 1. No raw translation keys visible (auth.login_title)
# 2. All text displays correctly
# 3. No console errors
```

---

## Expected Test Results

After implementation, all these tests should PASS (GREEN phase):
- 9 unit tests in `TranslationFilesTest.php`
- 10 feature tests in `I18nIntegrationTest.php`
- 12 browser tests in `I18nTranslationTest.php`

---

## Troubleshooting

### Issue: `$t is not a function`
**Solution**: Ensure `i18nVue` plugin is registered in `app.ts` before `.mount(el)`

### Issue: Translation keys showing instead of text
**Solution**: Check that `/lang/en.json` exists and contains the key

### Issue: Vite build errors
**Solution**: Verify `import.meta.glob` pattern matches actual file location

### Issue: TypeScript errors on `$t()`
**Solution**: Ensure `/resources/js/types/i18n.d.ts` is created and TypeScript compiler picks it up

---

## Implementation Order

1. Update `app.ts` → Build → Verify no errors
2. Create TypeScript declarations → Build → Verify no type errors
3. Create `/lang/en.json` → Run unit tests → Should pass
4. Refactor auth pages → Build → Test manually
5. Refactor main pages → Build → Test manually
6. Refactor settings pages → Build → Test manually
7. Refactor layouts → Build → Test manually
8. Run full test suite → All tests should pass

---

## Final Notes

- **DO NOT** translate user-generated content (lineup names, artist names, etc.)
- **DO NOT** translate API responses - backend returns English data
- **DO** translate all UI labels, buttons, placeholders, titles
- **DO** use descriptive translation keys (not generic like `title1`, `button2`)
- **DO** test each page after refactoring before moving to the next

---

**Document Status**: Ready for implementation by vue-laravel-developer
**Estimated Time**: 6-8 hours
**Dependencies**: laravel-vue-i18n@^2.8.0 (already installed)
