<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Spotify API Spike Test
|--------------------------------------------------------------------------
| Temporary command to test Spotify API connectivity and search.
| Usage: php artisan spotify:test "Tame Impalla"
| This proves: 1) Auth works 2) Search works 3) Typo handling works
*/
Artisan::command('spotify:test {query}', function (string $query) {
    $clientId = config('services.spotify.client_id');
    $clientSecret = config('services.spotify.client_secret');

    if (empty($clientId) || empty($clientSecret)) {
        $this->error('Missing SPOTIFY_CLIENT_ID or SPOTIFY_CLIENT_SECRET in .env');

        return 1;
    }

    $this->info("Testing Spotify API with query: {$query}");
    $this->newLine();

    // Step 1: Get access token
    $this->comment('Step 1: Authenticating...');
    $tokenResponse = Http::asForm()->post('https://accounts.spotify.com/api/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ]);

    if ($tokenResponse->failed()) {
        $this->error('Authentication failed: ' . $tokenResponse->body());
        return 1;
    }

    $token = $tokenResponse->json('access_token');
    $this->info('✓ Authentication successful');
    $this->newLine();

    // Step 2: Search for artists
    $this->comment('Step 2: Searching for artists...');
    $searchResponse = Http::withToken($token)
        ->get('https://api.spotify.com/v1/search', [
            'q' => $query,
            'type' => 'artist',
            'limit' => 5,
        ]);

    if ($searchResponse->failed()) {
        $this->error('Search failed: ' . $searchResponse->body());
        return 1;
    }

    $artists = $searchResponse->json('artists.items');
    $this->info('✓ Search successful - Found ' . count($artists) . ' artists');
    $this->newLine();

    // Step 3: Display results
    $this->comment('Results:');
    $this->table(
        ['Name', 'Spotify ID', 'Popularity', 'Followers', 'Genres'],
        collect($artists)->map(fn ($artist) => [
            $artist['name'],
            $artist['id'],
            $artist['popularity'],
            number_format($artist['followers']['total']),
            implode(', ', array_slice($artist['genres'], 0, 2)) ?: 'N/A',
        ])
    );

    $this->newLine();
    $this->info('Spike test complete! The Spotify API is working correctly.');

    // Show raw first result for schema reference
    if (!empty($artists)) {
        $this->newLine();
        $this->comment('Raw first result (for schema reference):');
        $this->line(json_encode($artists[0], JSON_PRETTY_PRINT));
    }

    return 0;
})->purpose('Test Spotify API connectivity and artist search');
