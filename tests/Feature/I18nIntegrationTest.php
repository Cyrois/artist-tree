<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

describe('i18n Integration', function () {
    it('can load main translation file from lang directory', function () {
        $langPath = base_path('lang');
        $mainFile = "{$langPath}/en.json";

        expect(File::exists($mainFile))->toBeTrue('Main translation file /lang/en.json not found');

        $translations = json_decode(File::get($mainFile), true);

        expect($translations)->toBeArray('Failed to load en.json');
        expect($translations)->not->toBeEmpty('en.json is empty');

        // Verify it uses flat key structure (e.g., "auth.login_title")
        $keys = array_keys($translations);
        expect($keys[0])->toContain('.');
    });

    it('loads auth translation keys correctly', function () {
        expect(Lang::has('auth.login_title'))->toBeTrue('auth.login_title key not found');
        expect(Lang::has('auth.login_email_label'))->toBeTrue('auth.login_email_label key not found');
        expect(Lang::has('auth.register_title'))->toBeTrue('auth.register_title key not found');
    });

    it('loads dashboard translation keys correctly', function () {
        expect(Lang::has('dashboard.hero_title'))->toBeTrue('dashboard.hero_title key not found');
        expect(Lang::has('dashboard.search_placeholder'))->toBeTrue('dashboard.search_placeholder key not found');
    });

    it('loads common translation keys correctly', function () {
        expect(Lang::has('common.action_save'))->toBeTrue('common.action_save key not found');
        expect(Lang::has('common.action_cancel'))->toBeTrue('common.action_cancel key not found');
        expect(Lang::has('common.error_generic'))->toBeTrue('common.error_generic key not found');
    });

    it('returns correct translation for existing key', function () {
        $translation = __('auth.login_title');

        expect($translation)->not->toBe('auth.login_title', 'Translation returned the key itself');
        expect($translation)->toBeString('Translation is not a string');
        expect($translation)->not->toBeEmpty('Translation is empty');
    });

    it('falls back to key when translation is missing', function () {
        $translation = __('nonexistent.missing_key');

        // Laravel returns the key itself when translation is missing
        expect($translation)->toBe('nonexistent.missing_key');
    });

    it('handles parameter interpolation with colon syntax', function () {
        // JSON translations use :param syntax: "pagination_showing": "Showing :from to :to of :total results"
        if (! Lang::has('common.pagination_showing')) {
            $this->markTestSkipped('common.pagination_showing key not found');
        }

        $translation = __('common.pagination_showing', [
            'from' => 1,
            'to' => 10,
            'total' => 100,
        ]);

        expect($translation)->toContain('1');
        expect($translation)->toContain('10');
        expect($translation)->toContain('100');
        expect($translation)->not->toContain(':from');
        expect($translation)->not->toContain(':to');
        expect($translation)->not->toContain(':total');
    });

    it('handles parameter interpolation with single parameter', function () {
        // Test with single parameter: "show_artists_count": ":count artists"
        if (! Lang::has('lineups.show_artists_count')) {
            $this->markTestSkipped('lineups.show_artists_count key not found');
        }

        $translation = __('lineups.show_artists_count', [
            'count' => 25,
        ]);

        expect($translation)->toContain('25');
        expect($translation)->not->toContain(':count');
    });

    it('uses default locale as en', function () {
        expect(app()->getLocale())->toBe('en');
    });

    it('can retrieve translations by full key', function () {
        // With flat JSON structure, we access translations by full key (namespace.key)
        $loginTitle = Lang::get('auth.login_title');
        $registerTitle = Lang::get('auth.register_title');

        expect($loginTitle)->toBeString();
        expect($loginTitle)->not->toBe('auth.login_title', 'Should return translation, not key');
        expect($registerTitle)->toBeString();
        expect($registerTitle)->not->toBe('auth.register_title', 'Should return translation, not key');
    });

    it('all translation values are strings', function () {
        // With flat JSON structure, all values should be strings
        $langPath = base_path('lang/en.json');
        $translations = json_decode(File::get($langPath), true);

        foreach ($translations as $key => $value) {
            expect($value)->toBeString("Translation '{$key}' should be a string");
        }
    });

    it('has consistent key structure in main translation file', function () {
        $langPath = base_path('lang');
        $mainFile = "{$langPath}/en.json";

        $translations = json_decode(File::get($mainFile), true);

        foreach ($translations ?? [] as $key => $value) {
            // All values should be strings (not arrays, for MVP)
            expect($value)
                ->toBeString("Translation key '{$key}' should be a string, not an array");

            // All keys should use namespace.key format
            expect($key)->toContain('.');
        }
    });
});
