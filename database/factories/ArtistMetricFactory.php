<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ArtistMetric>
 */
class ArtistMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'artist_id' => \App\Models\Artist::factory(),
            'spotify_popularity' => fake()->numberBetween(0, 100),
            'spotify_followers' => $this->generateLogarithmicFollowers(100, 10_000_000),
            'youtube_subscribers' => $this->generateLogarithmicFollowers(100, 5_000_000),
            'refreshed_at' => now(),
        ];
    }

    /**
     * Generate followers/subscribers using logarithmic distribution
     * to simulate realistic artist popularity distribution.
     */
    private function generateLogarithmicFollowers(int $min, int $max): int
    {
        $logMin = log10($min);
        $logMax = log10($max);
        $logRandom = fake()->randomFloat(4, $logMin, $logMax);

        return (int) round(pow(10, $logRandom));
    }

    /**
     * Metrics that are stale (older than 24 hours).
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes) => [
            'refreshed_at' => now()->subHours(25),
        ]);
    }

    /**
     * Fresh metrics (recently refreshed).
     */
    public function fresh(): static
    {
        return $this->state(fn (array $attributes) => [
            'refreshed_at' => now(),
        ]);
    }

    /**
     * Artist without YouTube data.
     */
    public function withoutYouTube(): static
    {
        return $this->state(fn (array $attributes) => [
            'youtube_subscribers' => null,
        ]);
    }

    /**
     * Mega-star with high metrics (for testing edge cases).
     */
    public function megaStar(): static
    {
        return $this->state(fn (array $attributes) => [
            'spotify_popularity' => fake()->numberBetween(90, 100),
            'spotify_followers' => fake()->numberBetween(50_000_000, 100_000_000),
            'youtube_subscribers' => fake()->numberBetween(30_000_000, 50_000_000),
        ]);
    }

    /**
     * Unsigned/indie artist with low metrics (for testing edge cases).
     */
    public function unsigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'spotify_popularity' => fake()->numberBetween(0, 20),
            'spotify_followers' => fake()->numberBetween(100, 5_000),
            'youtube_subscribers' => fake()->numberBetween(50, 2_000),
        ]);
    }
}
