<?php

namespace Database\Factories;

use App\Enums\StatutPaiement;
use App\Models\Paiement;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paiement>
 */
class PaiementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'prestataire' => 'stripe',
            'reference_externe' => 'pi_'.fake()->unique()->regexify('[A-Za-z0-9]{20}'),
            'statut' => StatutPaiement::Accepte,
            'montant_centimes' => 950,
        ];
    }
}
