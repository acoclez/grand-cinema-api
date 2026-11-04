<?php

namespace Database\Factories;

use App\Enums\LangueSeance;
use App\Enums\StatutSeance;
use App\Models\Film;
use App\Models\Salle;
use App\Models\Seance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seance>
 */
class SeanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'film_id' => Film::factory(),
            'salle_id' => Salle::factory(),
            'debut' => fake()->dateTimeBetween('+1 day', '+7 days'),
            'langue' => fake()->randomElement(LangueSeance::cases()),
            'statut' => StatutSeance::Publiee,
        ];
    }

    public function brouillon(): static
    {
        return $this->state(fn (array $attributes) => ['statut' => StatutSeance::Brouillon]);
    }
}
