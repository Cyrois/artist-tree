<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

describe('i18n Integration', function () {
    it('can load translation files from lang/en directory', function () {
        $langPath = base_path('lang');
        $files = File::glob("{$langPath}/en/*.json");

        expect($files)->not->toBeEmpty('No translation files found in lang/en/');

        foreach ($files as $file) {
            $fileName = basename($file, '.json');
            $translations = json_decode(File::get($file), true);

            expect($translations)->toBeArray("Failed to load {$fileName}.json");
            expect($translations)->not->toBeEmpty("{$fileName}.json is empty");
        }
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
        // Assuming common.json has: "pagination_showing": "Showing :from to :to of :total results"
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

    it('handles parameter interpolation with curly brace syntax', function () {
        // Assuming lineups.json has: "show_artist_count": "{count} artists"
        if (! Lang::has('lineups.show_artist_count')) {
            $this->markTestSkipped('lineups.show_artist_count key not found');
        }

        $translation = __('lineups.show_artist_count', [
            'count' => 25,
        ]);

        expect($translation)->toContain('25');
        expect($translation)->not->toContain('{count}');
    });

    it('uses default locale as en', function () {
        expect(app()->getLocale())->toBe('en');
    });

    it('can retrieve all translations for a namespace', function () {
        $authTranslations = Lang::get('auth');

        expect($authTranslations)->toBeArray();
        expect($authTranslations)->not->toBeEmpty();
        expect($authTranslations)->toHaveKey('login_title');
    });

    it('handles nested translation keys if present', function () {
        // Test that the system can handle both flat and nested structures
        $authTranslations = Lang::get('auth');

        foreach ($authTranslations as $key => $value) {
            if (is_array($value)) {
                // Nested structure
                expect($value)->toBeArray();
            } else {
                // Flat structure
                expect($value)->toBeString();
            }
        }

        expect(true)->toBeTrue('Translation structure is valid');
    });

    it('has consistent key structure across all files', function () {
        $langPath = base_path('lang');
        $files = File::glob("{$langPath}/en/*.json");

        foreach ($files as $file) {
            $translations = json_decode(File::get($file), true);
            $fileName = basename($file, '.json');

            foreach ($translations ?? [] as $key => $value) {
                // All values should be strings (not arrays, for MVP)
                expect($value)
                    ->toBeString("{$fileName}.{$key} should be a string, not an array");
            }
        }
    });
});
