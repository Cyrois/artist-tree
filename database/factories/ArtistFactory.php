<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\Country;
use App\Models\Genre;
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
            'musicbrainz_id' => fake()->unique()->uuid(),
            'spotify_id' => fake()->unique()->uuid(),
            'name' => fake()->firstName().' '.fake()->randomElement(['and the', '&']).' '.fake()->word(),
            'image_url' => fake()->imageUrl(640, 640, 'music', true, 'artist'),
            'country_id' => Country::query()->inRandomOrder()->value('id') ?? Country::factory(),
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
     * Attach genres to the artist.
     */
    public function withGenres(array $genreNames = ['rock', 'pop']): static
    {
        return $this->afterCreating(function (Artist $artist) use ($genreNames) {
            foreach ($genreNames as $name) {
                $genre = Genre::findOrCreateSmart($name);
                $artist->genres()->attach($genre);
            }
        });
    }
}