<?php

namespace App\Services\Reservations;

use App\Enums\CanalVente;
use App\Enums\StatutReservation;
use App\Exceptions\PlacesDejaPrisesException;
use App\Exceptions\RegleMetierException;
use App\Models\Reservation;
use App\Models\Seance;
use App\Models\User;

/**
 * Bloque des places d'une séance pendant 10 minutes, le temps du paiement en ligne.
 * Les demandes simultanées sont arbitrées par AttributionDesPlaces.
 */
readonly class BlocageDesPlaces
{
    public function __construct(private AttributionDesPlaces $attribution) {}

    /**
     * @param  list<int>  $placeIds  Places de la salle de la séance, déjà validées
     *
     * @throws RegleMetierException Séance déjà commencée
     * @throws PlacesDejaPrisesException Une place est déjà vendue ou bloquée
     */
    public function bloquer(Seance $seance, array $placeIds, ?User $spectateur, CanalVente $canal): Reservation
    {
        return $this->attribution->attribuer($seance, $placeIds, function () use ($seance, $placeIds, $spectateur, $canal): Reservation {
            if ($spectateur !== null) {
                $this->abandonnerLaSelectionEnCours($seance, $spectateur);
            }

            $reservation = Reservation::create([
                'user_id' => $spectateur?->id,
                'seance_id' => $seance->id,
                'statut' => StatutReservation::EnAttente,
                'canal' => $canal,
                'expire_at' => now()->addMinutes(Reservation::BLOCAGE_MINUTES),
            ]);

            foreach ($placeIds as $placeId) {
                // Le tarif et le prix sont choisis à l'étape du paiement.
                $reservation->billets()->create([
                    'seance_id' => $seance->id,
                    'place_id' => $placeId,
                    'prix_centimes' => 0,
                ]);
            }

            return $reservation;
        });
    }

    /**
     * Une seule sélection en cours par spectateur et par séance: en choisir une nouvelle
     * libère la précédente, pour qu'un compte ne bloque pas plus de 10 places à la fois.
     */
    private function abandonnerLaSelectionEnCours(Seance $seance, User $spectateur): void
    {
        Reservation::query()
            ->where('seance_id', $seance->id)
            ->where('user_id', $spectateur->id)
            ->where('statut', StatutReservation::EnAttente)
            ->get()
            ->each(fn (Reservation $reservation) => $reservation->liberer(StatutReservation::Annulee));
    }
}
