<?php

namespace Database\Factories;

use App\Models\Lineup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lineup>
 */
class LineupFactory extends Factory
{
    protected $model = Lineup::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Festival',
            'description' => $this->faker->sentence(),
        ];
    }
}
