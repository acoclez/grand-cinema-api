<?php

namespace Database\Factories;

use App\Models\Place;
use App\Models\Salle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salle_id' => Salle::factory(),
            'rangee' => fake()->randomElement(range('A', 'J')),
            'numero' => fake()->unique()->numberBetween(1, 999),
            'pmr' => false,
        ];
    }
}
