<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Mockup routes (no auth required - for demos and stakeholder review)
Route::prefix('mockup')->name('mockup.')->group(function () {
    Route::get('/', fn() => Inertia::render('mockup/Dashboard'))->name('dashboard');
    Route::get('/search', fn() => Inertia::render('mockup/Search'))->name('search');
    Route::get('/artist/{id}', fn($id) => Inertia::render('mockup/Artist/Show', ['id' => (int) $id]))->name('artist');
    Route::get('/lineups', fn() => Inertia::render('mockup/Lineups/Index'))->name('lineups');
    Route::get('/lineups/{id}', fn($id) => Inertia::render('mockup/Lineups/Show', ['id' => (int) $id]))->name('lineup');
    Route::get('/settings', fn() => Inertia::render('mockup/Settings'))->name('settings');
});

require __DIR__.'/settings.php';
