<?php

namespace Tests\Feature\Api;

use App\Models\User;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpotifyAuthTest extends TestCase
{

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_token_endpoint_returns_auth_url_when_no_token_present(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.spotify.token'));

        $response->assertStatus(401)
            ->assertJsonStructure([
                'error',
                'message',
                'auth_url',
            ]);

        $authUrl = $response->json('auth_url');
        $this->assertStringContainsString('accounts.spotify.com/authorize', $authUrl);

        // Verify Scopes
        // Scopes should include: streaming user-read-email user-read-private user-read-playback-state user-modify-playback-state
        // URL encoded spaces are usually + or %20
        $this->assertStringContainsString('scope=', $authUrl);

        $scopes = [
            'streaming',
            'user-read-email',
            'user-read-private',
            'user-read-playback-state',
            'user-modify-playback-state',
        ];

        foreach ($scopes as $scope) {
            $this->assertStringContainsString($scope, urldecode($authUrl), "Auth URL missing scope: {$scope}");
        }
    }

    public function test_token_endpoint_validates_and_returns_existing_token(): void
    {
        // Simulate existing session token
        $token = 'existing_valid_token';

        // Mock Spotify /me endpoint to verify token
        Http::fake([
            'https://api.spotify.com/v1/me' => Http::response(['id' => 'spotify_user'], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['spotify_access_token' => $token])
            ->getJson(route('api.spotify.token'));

        $response->assertOk()
            ->assertJson([
                'access_token' => $token,
            ]);

        Http::assertSent(function ($request) use ($token) {
            return $request->url() === 'https://api.spotify.com/v1/me' &&
                   $request->header('Authorization')[0] === "Bearer {$token}";
        });
    }

    public function test_token_endpoint_clears_invalid_token(): void
    {
        $token = 'invalid_token';

        // Mock Spotify /me endpoint to fail (401)
        Http::fake([
            'https://api.spotify.com/v1/me' => Http::response(['error' => 'Invalid token'], 401),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['spotify_access_token' => $token])
            ->getJson(route('api.spotify.token'));

        // Should return 401 with new auth URL because the token was invalid
        $response->assertStatus(401)
            ->assertJsonStructure(['auth_url']);

        // Session should be cleared
        $this->assertNull(session('spotify_access_token'));
    }

    public function test_callback_endpoint_exchanges_code_for_token(): void
    {
        $state = 'test_state_123';
        $code = 'auth_code_123';
        $accessToken = 'new_access_token';
        $refreshToken = 'new_refresh_token';

        // Mock Token Exchange
        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['spotify_oauth_state' => $state])
            ->get(route('api.spotify.callback', [
                'code' => $code,
                'state' => $state,
            ]));

        // Should redirect to dashboard (default)
        $response->assertRedirect(route('dashboard'));

        // Session should have tokens
        $this->assertEquals($accessToken, session('spotify_access_token'));
        $this->assertEquals($refreshToken, session('spotify_refresh_token'));
    }

    public function test_clear_token_endpoint_removes_tokens_from_session(): void
    {
        $this->actingAs($this->user)
            ->withSession([
                'spotify_access_token' => 'some_token',
                'spotify_refresh_token' => 'some_refresh_token',
            ])
            ->deleteJson(route('api.spotify.token.clear'))
            ->assertOk()
            ->assertJson(['message' => 'Spotify tokens cleared successfully']);

        $this->assertNull(session('spotify_access_token'));
        $this->assertNull(session('spotify_refresh_token'));
    }

    public function test_callback_endpoint_handles_error(): void
    {
        $state = 'test_state_123';
        $code = 'bad_code';

        // Mock Token Exchange Failure
        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['spotify_oauth_state' => $state])
            ->get(route('api.spotify.callback', [
                'code' => $code,
                'state' => $state,
            ]));

        // Should redirect with error
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
        $this->assertNull(session('spotify_access_token'));
    }
}
