<?php

namespace Database\Factories;

use App\Enums\ClassificationFilm;
use App\Models\Film;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Film>
 */
class FilmFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titre' => ucfirst(fake()->words(3, true)),
            'synopsis' => fake()->paragraph(),
            'duree_minutes' => fake()->numberBetween(80, 180),
            'genre' => fake()->randomElement(['Drame', 'Comédie', 'Documentaire', 'Animation', 'Thriller']),
            'classification' => fake()->randomElement([ClassificationFilm::TousPublics, ClassificationFilm::Moins12, ClassificationFilm::Moins16]),
            'langue_originale' => fake()->randomElement(['Français', 'Anglais', 'Italien', 'Japonais']),
            'affiche_url' => null,
            'bande_annonce_url' => null,
            'archive_at' => null,
        ];
    }

    public function archive(): static
    {
        return $this->state(fn (array $attributes) => ['archive_at' => now()]);
    }
}
