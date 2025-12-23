<?php

use App\Http\Controllers\ArtistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Artist Search & Management API
Route::middleware(['auth:web', 'throttle:api'])->group(function () {
    // Search for artists (hybrid local + Spotify)
    Route::get('/artists/search', [ArtistController::class, 'search'])->name('api.artists.search');

    // Select an artist from Spotify (create/update in database)
    Route::post('/artists/select', [ArtistController::class, 'select'])->name('api.artists.select');

    // Refresh artist data from Spotify
    Route::post('/artists/{id}/refresh', [ArtistController::class, 'refresh'])->name('api.artists.refresh');
});

// User info endpoint (example)
Route::middleware(['auth:web'])->get('/user', function (Request $request) {
    return $request->user();
});
