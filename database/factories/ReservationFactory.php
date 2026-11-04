<?php

namespace Database\Factories;

use App\Enums\CanalVente;
use App\Enums\StatutReservation;
use App\Models\Reservation;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'seance_id' => Seance::factory(),
            'statut' => StatutReservation::EnAttente,
            'canal' => CanalVente::EnLigne,
            'montant_centimes' => 0,
            'expire_at' => now()->addMinutes(Reservation::BLOCAGE_MINUTES),
        ];
    }

    public function confirmee(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutReservation::Confirmee,
            'expire_at' => null,
        ]);
    }

    public function guichet(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'canal' => CanalVente::Guichet,
            'statut' => StatutReservation::Confirmee,
            'expire_at' => null,
        ]);
    }
}
