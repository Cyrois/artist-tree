<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpotifyController extends Controller
{
    /**
     * Get the Spotify redirect URI consistently.
     * This ensures the same URI is used in both authorization and callback.
     */
    private function getRedirectUri(): string
    {
        // Use explicit config if set, otherwise generate from route
        $redirectUri = config('services.spotify.redirect_uri');
        
        if (!$redirectUri) {
            // Use route helper to generate full URL
            $redirectUri = route('api.spotify.callback');
        }

        // Log the redirect URI for debugging
        Log::debug('Spotify redirect URI', ['redirect_uri' => $redirectUri]);

        return $redirectUri;
    }

    /**
     * Get Spotify OAuth token for the current user.
     * This endpoint initiates the OAuth flow or returns existing token.
     *
     * GET /api/spotify/token?return_url=/artist/123
     */
    public function token(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has a stored Spotify access token
        // For now, we'll use a session-based approach
        // In production, you'd want to store this in the database
        $sessionToken = $request->session()->get('spotify_access_token');

        if ($sessionToken) {
            // Verify token is still valid
            try {
                $response = Http::withToken($sessionToken)
                    ->get('https://api.spotify.com/v1/me');

                if ($response->successful()) {
                    return response()->json([
                        'access_token' => $sessionToken,
                    ]);
                }

                // Token is invalid (401, 403, etc.) - clear it
                // This handles cases where email changed or token expired
                if ($response->status() === 401 || $response->status() === 403) {
                    Log::info('Spotify access token invalid, clearing from session', [
                        'status' => $response->status(),
                        'user_id' => $user->id ?? null,
                    ]);
                    $request->session()->forget(['spotify_access_token', 'spotify_refresh_token']);
                }
            } catch (\Exception $e) {
                // Network or other errors - clear token to be safe
                Log::warning('Spotify token validation error, clearing token', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id ?? null,
                ]);
                $request->session()->forget('spotify_access_token');
                $request->session()->forget('spotify_refresh_token');
            }
        }

        // Store return URL for redirect after OAuth callback
        $returnUrl = $request->query('return_url') 
            ?? $request->header('Referer') 
            ?? route('dashboard');
        
        // Ensure return URL is within our application domain for security
        $returnUrl = $this->sanitizeReturnUrl($returnUrl);
        $request->session()->put('spotify_oauth_return_url', $returnUrl);

        // If no valid token, redirect to OAuth
        return response()->json([
            'error' => 'not_authenticated',
            'message' => 'Please authenticate with Spotify first',
            'auth_url' => $this->getAuthUrl($request),
        ], 401);
    }

    /**
     * Clear Spotify access tokens from session.
     * Useful for forcing re-authentication when tokens are invalid.
     *
     * DELETE /api/spotify/token
     */
    public function clearToken(Request $request): JsonResponse
    {
        $user = $request->user();
        
        Log::info('Clearing Spotify tokens from session', [
            'user_id' => $user->id ?? null,
        ]);

        $request->session()->forget('spotify_access_token');
        $request->session()->forget('spotify_refresh_token');

        return response()->json([
            'message' => 'Spotify tokens cleared successfully',
        ]);
    }

    /**
     * Handle Spotify OAuth callback.
     *
     * GET /api/spotify/callback
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $returnUrl = $request->session()->get('spotify_oauth_return_url', route('dashboard'));

        // Clear the return URL from session
        $request->session()->forget('spotify_oauth_return_url');

        if (!$code) {
            Log::warning('Spotify OAuth callback missing code', [
                'query' => $request->query(),
            ]);
            return redirect($returnUrl)->with('error', 'Authorization code not provided');
        }

        // Verify state to prevent CSRF
        $sessionState = $request->session()->get('spotify_oauth_state');
        if ($state !== $sessionState) {
            Log::warning('Spotify OAuth callback invalid state', [
                'received_state' => $state,
                'session_state' => $sessionState,
            ]);
            return redirect($returnUrl)->with('error', 'Invalid authentication state. Please try again.');
        }

        try {
            $clientId = config('services.spotify.client_id');
            $clientSecret = config('services.spotify.client_secret');
            $redirectUri = $this->getRedirectUri();

            Log::info('Spotify OAuth token exchange', [
                'redirect_uri' => $redirectUri,
            ]);

            $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                Log::error('Spotify OAuth token exchange failed', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                    'redirect_uri_used' => $redirectUri,
                ]);

                return redirect($returnUrl)->with('error', 'Failed to authenticate with Spotify. Please try again.');
            }

            $data = $response->json();
            $accessToken = $data['access_token'];
            $refreshToken = $data['refresh_token'] ?? null;

            // Store tokens in session
            $request->session()->put('spotify_access_token', $accessToken);
            if ($refreshToken) {
                $request->session()->put('spotify_refresh_token', $refreshToken);
            }

            Log::info('Spotify OAuth successful', [
                'return_url' => $returnUrl,
            ]);

            return redirect($returnUrl)->with('success', 'Successfully authenticated with Spotify');
        } catch (\Exception $e) {
            Log::error('Spotify OAuth callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect($returnUrl)->with('error', 'An error occurred during authentication. Please try again.');
        }
    }

    /**
     * Generate Spotify OAuth authorization URL.
     */
    private function getAuthUrl(Request $request): string
    {
        $clientId = config('services.spotify.client_id');
        $redirectUri = $this->getRedirectUri();
        $scopes = 'user-read-playback-state user-modify-playback-state streaming user-read-email user-read-private';

        // Generate state for CSRF protection
        $state = bin2hex(random_bytes(16));
        $request->session()->put('spotify_oauth_state', $state);

        $returnUrl = $request->session()->get('spotify_oauth_return_url', route('dashboard'));
        
        Log::info('Spotify OAuth authorization URL generated', [
            'redirect_uri' => $redirectUri,
            'return_url' => $returnUrl,
        ]);

        $params = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => $scopes,
            'state' => $state,
        ]);

        return "https://accounts.spotify.com/authorize?{$params}";
    }

    /**
     * Sanitize and validate return URL to ensure it's within our application.
     * 
     * @param string $url
     * @return string
     */
    private function sanitizeReturnUrl(string $url): string
    {
        // If it's already a route name or relative path, use it as-is
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        // If it's a full URL, extract the path
        $parsed = parse_url($url);
        if (isset($parsed['path'])) {
            return $parsed['path'] . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
        }

        // Default to dashboard if URL is invalid
        return route('dashboard');
    }
}

