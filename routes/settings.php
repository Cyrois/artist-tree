<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    // Unified settings page
    Route::get('settings', function () {
        return Inertia::render('Settings', [
            'tab' => 'profile',
            'mustVerifyEmail' => true,
        ]);
    })->name('settings.index');

    // Profile routes
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Password routes
    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    // Appearance route
    Route::get('settings/appearance', function () {
        return Inertia::render('Settings', [
            'tab' => 'appearance',
            'mustVerifyEmail' => true,
        ]);
    })->name('appearance.edit');

    // Two-factor route
    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    // Organization settings
    Route::get('settings/organization', function () {
        return Inertia::render('Settings', [
            'tab' => 'organization',
        ]);
    })->name('settings.organization');
});
