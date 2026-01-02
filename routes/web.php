<?php

use App\Http\Controllers\DashboardController;
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
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('search', fn () => Inertia::render('Search'))->name('search');
    Route::get('artist/{id}', fn ($id) => Inertia::render('Artist/Show', [
        'id' => (int) $id,
        'userLineups' => auth()->user()->lineups()->select('lineups.id', 'name')->withCount('artists')->get()
    ]))->name('artist.show');
    Route::get('lineups', [LineupController::class, 'index'])->name('lineups.index');
    Route::post('lineups', [LineupController::class, 'store'])->name('lineups.store');
    Route::put('lineups/{lineup}', [LineupController::class, 'update'])->name('lineups.update');
    Route::delete('lineups/{lineup}', [LineupController::class, 'destroy'])->name('lineups.destroy');
    Route::get('lineups/{id}', [LineupController::class, 'show'])->name('lineups.show');
    Route::get('lineups/{lineup}/suggest-tier', [LineupController::class, 'suggestTier'])->name('lineups.suggest-tier');
    Route::post('lineups/{lineup}/artists', [LineupController::class, 'addArtist'])->name('lineups.artists.store');
    Route::delete('lineups/{lineup}/artists/{artist}', [LineupController::class, 'removeArtist'])->name('lineups.artists.destroy');

    // Lineup Stacking
    Route::post('lineups/{lineup}/stacks', [\App\Http\Controllers\LineupStackController::class, 'store'])->name('lineups.stacks.store');
    Route::post('lineups/{lineup}/stacks/{stackId}/promote', [\App\Http\Controllers\LineupStackController::class, 'promote'])->name('lineups.stacks.promote');
    Route::post('lineups/{lineup}/stacks/artists/{artist}/remove', [\App\Http\Controllers\LineupStackController::class, 'removeArtist'])->name('lineups.stacks.remove-artist');
    Route::delete('lineups/{lineup}/stacks/{stackId}', [\App\Http\Controllers\LineupStackController::class, 'dissolve'])->name('lineups.stacks.dissolve');

    // Settings routes are in routes/settings.php
});

require __DIR__.'/settings.php';
