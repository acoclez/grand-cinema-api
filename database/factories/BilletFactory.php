<?php

namespace Database\Factories;

use App\Models\Billet;
use App\Models\Place;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Billet>
 */
class BilletFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'seance_id' => fn (array $attributes) => Reservation::findOrFail($attributes['reservation_id'])->seance_id,
            'place_id' => fn (array $attributes) => Place::factory()->create([
                'salle_id' => Reservation::findOrFail($attributes['reservation_id'])->seance->salle_id,
            ])->id,
            'tarif_id' => null,
            'prix_centimes' => 950,
            'utilise_at' => null,
        ];
    }

    public function utilise(): static
    {
        return $this->state(fn (array $attributes) => ['utilise_at' => now()]);
    }
}
