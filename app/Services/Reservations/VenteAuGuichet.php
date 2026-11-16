<?php

namespace App\Services\Reservations;

use App\Enums\CanalVente;
use App\Enums\StatutReservation;
use App\Exceptions\PlacesDejaPrisesException;
use App\Exceptions\RegleMetierException;
use App\Models\Reservation;
use App\Models\Seance;

/**
 * Vend des places au guichet: l'agent encaisse le client au comptoir, la réservation est donc
 * confirmée tout de suite, sans blocage ni paiement en ligne. Elle n'a pas de compte
 * spectateur et porte le canal guichet, qui la distingue dans le taux de remplissage.
 * Les places viennent du même stock que le site: AttributionDesPlaces arbitre les ventes
 * simultanées.
 */
readonly class VenteAuGuichet
{
    public function __construct(private AttributionDesPlaces $attribution) {}

    /**
     * @param  list<array{place_id: int, tarif_id: int}>  $billets  Une place et son tarif par billet, déjà validés
     *
     * @throws RegleMetierException Séance déjà commencée
     * @throws PlacesDejaPrisesException Une place est déjà vendue ou bloquée
     */
    public function vendre(Seance $seance, array $billets): Reservation
    {
        $tarifs = $seance->tarifs()->get()->keyBy('id');

        return $this->attribution->attribuer($seance, array_column($billets, 'place_id'), function () use ($seance, $billets, $tarifs): Reservation {
            $reservation = Reservation::create([
                'user_id' => null,
                'seance_id' => $seance->id,
                'statut' => StatutReservation::Confirmee,
                'canal' => CanalVente::Guichet,
                'montant_centimes' => collect($billets)->sum(fn (array $billet) => $tarifs[$billet['tarif_id']]->prix_centimes),
                'expire_at' => null,
            ]);

            foreach ($billets as $billet) {
                $tarif = $tarifs[$billet['tarif_id']];
                $reservation->billets()->create([
                    'seance_id' => $seance->id,
                    'place_id' => $billet['place_id'],
                    'tarif_id' => $tarif->id,
                    'prix_centimes' => $tarif->prix_centimes,
                ]);
            }

            return $reservation;
        });
    }
}
