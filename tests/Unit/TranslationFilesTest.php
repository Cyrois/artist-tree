<?php

declare(strict_types=1);

describe('Translation Files', function () {
    // Helper to get translations from the main file
    $getTranslations = function () {
        $langPath = __DIR__.'/../../lang/en.json';
        expect(file_exists($langPath))->toBeTrue('Main translation file lang/en.json is missing');

        $content = file_get_contents($langPath);
        $decoded = json_decode($content, true);
        expect(json_last_error())->toBe(JSON_ERROR_NONE, 'Invalid JSON in lang/en.json: '.json_last_error_msg());

        return $decoded;
    };

    it('has main translation file', function () {
        $langPath = __DIR__.'/../../lang/en.json';
        expect(file_exists($langPath))->toBeTrue('Main translation file lang/en.json is missing');
    });

    it('has valid JSON in translation file', function () use ($getTranslations) {
        $translations = $getTranslations();
        expect($translations)->toBeArray('Translation file did not decode to an array');
        expect($translations)->not->toBeEmpty('Translation file is empty');
    });

    it('has all required namespaces', function () use ($getTranslations) {
        $translations = $getTranslations();
        $requiredNamespaces = ['auth', 'dashboard', 'lineups', 'artists', 'settings', 'common'];

        foreach ($requiredNamespaces as $namespace) {
            $hasNamespace = false;
            foreach (array_keys($translations) as $key) {
                if (str_starts_with($key, "{$namespace}.")) {
                    $hasNamespace = true;
                    break;
                }
            }
            expect($hasNamespace)->toBeTrue("Missing required namespace: {$namespace}");
        }
    });

    it('has no empty translation strings', function () use ($getTranslations) {
        $translations = $getTranslations();

        foreach ($translations as $key => $value) {
            expect($value)->not->toBeEmpty("{$key} has empty translation string");
            expect($value)->toBeString("{$key} is not a string");
        }
    });

    it('has all auth page translation keys', function () use ($getTranslations) {
        $translations = $getTranslations();

        $requiredKeys = [
            'auth.login_title',
            'auth.login_subtitle',
            'auth.login_email_label',
            'auth.login_email_placeholder',
            'auth.login_password_label',
            'auth.login_submit_button',
            'auth.register_title',
            'auth.register_submit_button',
            'auth.forgot_password_title',
            'auth.reset_password_title',
            'auth.verify_email_title',
            'auth.confirm_password_title',
        ];

        foreach ($requiredKeys as $key) {
            expect(array_key_exists($key, $translations))
                ->toBeTrue("Missing required auth key: {$key}");
        }
    });

    it('has all dashboard page translation keys', function () use ($getTranslations) {
        $translations = $getTranslations();

        $requiredKeys = [
            'dashboard.page_title',
            'dashboard.hero_title',
            'dashboard.hero_subtitle',
            'dashboard.search_placeholder',
            'dashboard.lineups_section_title',
            'dashboard.lineups_view_all_button',
            'dashboard.trending_section_title',
        ];

        foreach ($requiredKeys as $key) {
            expect(array_key_exists($key, $translations))
                ->toBeTrue("Missing required dashboard key: {$key}");
        }
    });

    it('has all common UI translation keys', function () use ($getTranslations) {
        $translations = $getTranslations();

        $requiredKeys = [
            'common.action_save',
            'common.action_cancel',
            'common.action_delete',
            'common.action_edit',
            'common.action_create',
            'common.status_loading',
            'common.error_generic',
            'common.success_saved',
        ];

        foreach ($requiredKeys as $key) {
            expect(array_key_exists($key, $translations))
                ->toBeTrue("Missing required common key: {$key}");
        }
    });

    it('has translation keys follow naming convention', function () use ($getTranslations) {
        $translations = $getTranslations();

        foreach (array_keys($translations) as $key) {
            // Keys should be namespace.snake_case format
            expect($key)
                ->toMatch('/^[a-z]+\.[a-z0-9_]+$/', "{$key} does not follow namespace.snake_case convention");

            // Keys should not be too short (minimum namespace.xxx = 5 chars)
            expect(strlen($key))
                ->toBeGreaterThan(5, "{$key} is too short");
        }
    });

    it('has parameter placeholders in correct format', function () use ($getTranslations) {
        $translations = $getTranslations();

        foreach ($translations as $key => $value) {
            // Should use :param format (Laravel standard)
            // Should not have malformed placeholders like {{param}} or $param
            expect($value)
                ->not->toMatch('/\{\{/', "{$key} has malformed {{}} placeholders");
            expect($value)
                ->not->toMatch('/\$\w+/', "{$key} has malformed $ placeholders");
        }
    });
});
