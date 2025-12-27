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

    // Get artist by database ID or Spotify ID
    Route::get('/artists/{id?}', [ArtistController::class, 'show'])
        ->where('id', '[0-9]+')
        ->name('api.artists.show');

    // Select an artist from Spotify (create/update in database)
    Route::post('/artists/select', [ArtistController::class, 'select'])->name('api.artists.select');

    // Refresh artist data from Spotify
    Route::post('/artists/{id}/refresh', [ArtistController::class, 'refresh'])->name('api.artists.refresh');

    // Async Spotify data endpoints
    Route::get('/artists/{id}/top-tracks', [ArtistController::class, 'topTracks'])->name('api.artists.top-tracks');
    Route::get('/artists/{id}/albums', [ArtistController::class, 'albums'])->name('api.artists.albums');
    Route::get('/artists/{id}/similar', [ArtistController::class, 'similar'])->name('api.artists.similar');
});

// User info endpoint (example)
Route::middleware(['auth:web'])->get('/user', function (Request $request) {
    return $request->user();
});

// Spotify OAuth endpoints
Route::middleware(['auth:web'])->group(function () {
    Route::get('/spotify/token', [\App\Http\Controllers\SpotifyController::class, 'token'])->name('api.spotify.token');
    Route::delete('/spotify/token', [\App\Http\Controllers\SpotifyController::class, 'clearToken'])->name('api.spotify.token.clear');
    Route::get('/spotify/callback', [\App\Http\Controllers\SpotifyController::class, 'callback'])->name('api.spotify.callback');
});
