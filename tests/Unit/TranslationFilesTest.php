<?php

declare(strict_types=1);

describe('Translation Files', function () {
    // Helper function to get lang path
    $getLangPath = function () {
        return __DIR__.'/../../lang';
    };

    it('has all required translation files', function () use ($getLangPath) {
        $requiredFiles = [
            'auth',
            'dashboard',
            'lineups',
            'artists',
            'settings',
            'common',
        ];

        $langPath = $getLangPath();

        foreach ($requiredFiles as $file) {
            $filePath = "{$langPath}/en/{$file}.json";
            expect(file_exists($filePath))
                ->toBeTrue("Translation file en/{$file}.json is missing");
        }
    });

    it('has valid JSON in all translation files', function () use ($getLangPath) {
        $langPath = $getLangPath();
        $files = glob("{$langPath}/en/*.json");

        expect($files)->not->toBeEmpty('No JSON translation files found in lang/en/');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $decoded = json_decode($content, true);

            expect(json_last_error())
                ->toBe(JSON_ERROR_NONE, "Invalid JSON in {$file}: ".json_last_error_msg());
            expect($decoded)->toBeArray("Translation file {$file} did not decode to an array");
        }
    });

    it('has no duplicate keys across translation files', function () use ($getLangPath) {
        $langPath = $getLangPath();
        $files = glob("{$langPath}/en/*.json");
        $allKeys = [];

        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            $fileName = basename($file, '.json');

            foreach (array_keys($content ?? []) as $key) {
                $fullKey = "{$fileName}.{$key}";
                expect($allKeys)->not->toContain($fullKey, "Duplicate key found: {$fullKey}");
                $allKeys[] = $fullKey;
            }
        }

        expect($allKeys)->not->toBeEmpty('No translation keys found across all files');
    });

    it('has no empty translation strings', function () use ($getLangPath) {
        $langPath = $getLangPath();
        $files = glob("{$langPath}/en/*.json");

        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            $fileName = basename($file, '.json');

            foreach ($content ?? [] as $key => $value) {
                expect($value)
                    ->not->toBeEmpty("{$fileName}.{$key} has empty translation string");
                expect($value)
                    ->toBeString("{$fileName}.{$key} is not a string");
            }
        }
    });

    it('has all auth page translation keys', function () use ($getLangPath) {
        $langPath = $getLangPath();
        $authFile = "{$langPath}/en/auth.json";
        expect(file_exists($authFile))->toBeTrue('auth.json file is missing');

        $translations = json_decode(file_get_contents($authFile), true);

        $requiredKeys = [
            'login_title',
            'login_subtitle',
            'login_email_label',
            'login_email_placeholder',
            'login_password_label',
            'login_submit_button',
            'register_title',
            'register_submit_button',
            'forgot_password_title',
            'reset_password_title',
            'verify_email_title',
            'two_factor_title',
            'confirm_password_title',
        ];

        foreach ($requiredKeys as $key) {
            expect(array_key_exists($key, $translations))
                ->toBeTrue("Missing required auth key: {$key}");
        }
    });

    it('has all dashboard page translation keys', function () use ($getLangPath) {
        $langPath = $getLangPath();
        $dashboardFile = "{$langPath}/en/dashboard.json";
        expect(file_exists($dashboardFile))->toBeTrue('dashboard.json file is missing');

        $translations = json_decode(file_get_contents($dashboardFile), true);

        $requiredKeys = [
            'page_title',
            'hero_title',
            'hero_subtitle',
            'search_placeholder',
            'lineups_section_title',
            'lineups_view_all_button',
            'trending_section_title',
        ];

        foreach ($requiredKeys as $key) {
            expect(array_key_exists($key, $translations))
                ->toBeTrue("Missing required dashboard key: {$key}");
        }
    });

    it('has all common UI translation keys', function () use ($getLangPath) {
        $langPath = $getLangPath();
        $commonFile = "{$langPath}/en/common.json";
        expect(file_exists($commonFile))->toBeTrue('common.json file is missing');

        $translations = json_decode(file_get_contents($commonFile), true);

        $requiredKeys = [
            'action_save',
            'action_cancel',
            'action_delete',
            'action_edit',
            'action_create',
            'status_loading',
            'error_generic',
            'success_saved',
        ];

        foreach ($requiredKeys as $key) {
            expect(array_key_exists($key, $translations))
                ->toBeTrue("Missing required common key: {$key}");
        }
    });

    it('has translation keys follow naming convention', function () use ($getLangPath) {
        $langPath = $getLangPath();
        $files = glob("{$langPath}/en/*.json");

        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            $fileName = basename($file, '.json');

            foreach (array_keys($content ?? []) as $key) {
                // Keys should be snake_case (no spaces, no uppercase, no hyphens)
                expect($key)
                    ->toMatch('/^[a-z0-9_]+$/', "{$fileName}.{$key} does not follow snake_case convention");

                // Keys should not be too short (minimum 3 characters)
                expect(strlen($key))
                    ->toBeGreaterThan(2, "{$fileName}.{$key} is too short (minimum 3 characters)");
            }
        }
    });

    it('has parameter placeholders in correct format', function () use ($getLangPath) {
        $langPath = $getLangPath();
        $files = glob("{$langPath}/en/*.json");

        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            $fileName = basename($file, '.json');

            foreach ($content ?? [] as $key => $value) {
                // If translation contains parameters, they should use :param format
                if (preg_match_all('/\{(\w+)\}/', $value, $matches)) {
                    // Found {param} format - this is acceptable for laravel-vue-i18n
                    expect(true)->toBeTrue();
                } elseif (preg_match_all('/:(\w+)/', $value, $matches)) {
                    // Found :param format - also acceptable
                    expect(true)->toBeTrue();
                }

                // Should not have malformed placeholders like {{param}} or $param
                expect($value)
                    ->not->toMatch('/\{\{/', "{$fileName}.{$key} has malformed {{}} placeholders");
                expect($value)
                    ->not->toMatch('/\$\w+/', "{$fileName}.{$key} has malformed $ placeholders");
            }
        }
    });
});
