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
            'youtube_refreshed_at' => now(),
            'youtube_avg_views' => fake()->numberBetween(1000, 1_000_000),
            'youtube_avg_likes' => fake()->numberBetween(50, 50_000),
            'youtube_avg_comments' => fake()->numberBetween(10, 5_000),
            'youtube_videos_analyzed' => fake()->numberBetween(1, 15),
            'youtube_analytics_refreshed_at' => now(),
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
            'youtube_refreshed_at' => null,
            'youtube_avg_views' => null,
            'youtube_avg_likes' => null,
            'youtube_avg_comments' => null,
            'youtube_videos_analyzed' => null,
            'youtube_analytics_refreshed_at' => null,
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
            'youtube_avg_views' => fake()->numberBetween(5_000_000, 50_000_000),
            'youtube_avg_likes' => fake()->numberBetween(100_000, 1_000_000),
            'youtube_avg_comments' => fake()->numberBetween(10_000, 100_000),
            'youtube_videos_analyzed' => fake()->numberBetween(10, 15),
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
            'youtube_avg_views' => fake()->numberBetween(100, 10_000),
            'youtube_avg_likes' => fake()->numberBetween(5, 500),
            'youtube_avg_comments' => fake()->numberBetween(1, 50),
            'youtube_videos_analyzed' => fake()->numberBetween(1, 10),
        ]);
    }

    /**
     * YouTube analytics that are stale (older than 7 days).
     */
    public function staleYouTubeAnalytics(): static
    {
        return $this->state(fn (array $attributes) => [
            'youtube_analytics_refreshed_at' => now()->subDays(8),
        ]);
    }

    /**
     * Fresh YouTube analytics (recently refreshed).
     */
    public function freshYouTubeAnalytics(): static
    {
        return $this->state(fn (array $attributes) => [
            'youtube_analytics_refreshed_at' => now(),
        ]);
    }
}
