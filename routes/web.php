<?php

use App\Http\Controllers\LineupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('search', fn () => Inertia::render('Search'))->name('search');
    Route::get('artist/{id}', fn ($id) => Inertia::render('Artist/Show', ['id' => (int) $id]))->name('artist.show');
    Route::get('lineups', [LineupController::class, 'index'])->name('lineups.index');
    Route::get('lineups/{id}', [LineupController::class, 'show'])->name('lineups.show');
    // Settings routes are in routes/settings.php
});

require __DIR__.'/settings.php';
