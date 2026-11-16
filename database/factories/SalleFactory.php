<?php

namespace Database\Factories;

use App\Enums\TypeSalle;
use App\Models\Salle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Salle>
 */
class SalleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => 'Salle '.fake()->unique()->numberBetween(1, 999),
            'type' => TypeSalle::Standard,
            'capacite' => 180,
        ];
    }
}
