<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Artist>
 */
class ArtistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spotify_id' => fake()->unique()->uuid(),
            'name' => fake()->firstName().' '.fake()->randomElement(['and the', '&']).' '.fake()->word(),
            'genres' => fake()->randomElements(
                ['rock', 'indie', 'electronic', 'pop', 'hip-hop', 'jazz', 'metal', 'folk', 'r&b', 'country', 'alternative', 'punk', 'soul', 'reggae', 'blues'],
                rand(1, 3)
            ),
            'image_url' => fake()->imageUrl(640, 640, 'music', true, 'artist'),
        ];
    }

    /**
     * Artist without Spotify ID (not yet linked).
     */
    public function withoutSpotifyId(): static
    {
        return $this->state(fn (array $attributes) => [
            'spotify_id' => null,
        ]);
    }

    /**
     * Artist with specific genres.
     */
    public function withGenres(array $genres): static
    {
        return $this->state(fn (array $attributes) => [
            'genres' => $genres,
        ]);
    }
}
