<?php

namespace Tests\Feature\Api;

use App\Models\Artist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SimilarArtistsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Artist $artist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->artist = Artist::factory()
            ->withGenres(['rock', 'alternative'])
            ->create([
                'name' => 'Original Artist',
                'spotify_id' => 'original_id',
            ]);
    }

    public function test_similar_artists_endpoint_returns_data(): void
    {
        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'mock_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'https://api.spotify.com/v1/search*' => Http::response([
                'artists' => [
                    'items' => [
                        [
                            'id' => 'similar_id_1',
                            'name' => 'Similar Artist 1',
                            'genres' => ['rock'],
                            'images' => [['url' => 'https://example.com/image1.jpg']],
                            'followers' => ['total' => 1000],
                            'popularity' => 70,
                        ],
                        [
                            'id' => 'original_id', // Should be filtered out
                            'name' => 'Original Artist',
                            'genres' => ['rock'],
                            'images' => [['url' => 'https://example.com/image_orig.jpg']],
                            'followers' => ['total' => 5000],
                            'popularity' => 80,
                        ],
                        [
                            'id' => 'similar_id_2',
                            'name' => 'Similar Artist 2',
                            'genres' => ['rock', 'pop'],
                            'images' => [['url' => 'https://example.com/image2.jpg']],
                            'followers' => ['total' => 2000],
                            'popularity' => 65,
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$this->artist->id}/similar");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'spotify_id',
                        'name',
                        'genres',
                        'image_url',
                        'spotify_popularity',
                        'spotify_followers',
                    ],
                ],
            ]);

        // Should have 2 artists (Original Artist filtered out)
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals('similar_id_1', $response->json('data.0.spotify_id'));
        $this->assertEquals('similar_id_2', $response->json('data.1.spotify_id'));
    }

    public function test_returns_empty_when_no_genres(): void
    {
        $artistNoGenres = Artist::factory()->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/artists/{$artistNoGenres->id}/similar");

        $response->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('message', 'No genres found for this artist to find similar artists.');
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson("/api/artists/{$this->artist->id}/similar");
        $response->assertUnauthorized();
    }
}
