<?php

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
    Route::get('dashboard', fn() => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('search', fn() => Inertia::render('Search'))->name('search');
    Route::get('artist/{id}', fn($id) => Inertia::render('Artist/Show', ['id' => (int) $id]))->name('artist.show');
    Route::get('lineups', fn() => Inertia::render('Lineups/Index'))->name('lineups.index');
    Route::get('lineups/{id}', fn($id) => Inertia::render('Lineups/Show', ['id' => (int) $id]))->name('lineups.show');
    // Settings routes are in routes/settings.php
});

require __DIR__.'/settings.php';
