<?php

namespace Database\Factories;

use App\Models\Seance;
use App\Models\Tarif;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tarif>
 */
class TarifFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seance_id' => Seance::factory(),
            'libelle' => 'Plein',
            'prix_centimes' => 950,
        ];
    }
}
