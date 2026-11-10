<?php

namespace App\Services\Reservations;

use App\Enums\CanalVente;
use App\Enums\StatutReservation;
use App\Exceptions\PlacesDejaPrisesException;
use App\Exceptions\RegleMetierException;
use App\Models\Billet;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Bloque des places d'une séance pendant 10 minutes, le temps du paiement.
 *
 * La base de données arbitre les demandes simultanées: la contrainte d'unicité
 * (séance, place) de la table billets refuse la seconde écriture. La réservation et ses
 * billets sont créés dans une même transaction: si un billet est refusé, rien n'est
 * enregistré et le spectateur apprend quelles places viennent d'être prises.
 */
class BlocageDesPlaces
{
    /**
     * @param  list<int>  $placeIds  Places de la salle de la séance, déjà validées
     *
     * @throws RegleMetierException Séance déjà commencée
     * @throws PlacesDejaPrisesException Une place est déjà vendue ou bloquée
     */
    public function bloquer(Seance $seance, array $placeIds, ?User $spectateur, CanalVente $canal): Reservation
    {
        $seance->verifierReservable();

        try {
            return DB::transaction(function () use ($seance, $placeIds, $spectateur, $canal): Reservation {
                // Les blocages dépassés libèrent leurs places sans attendre la tâche planifiée.
                Reservation::expirerLesBlocagesDepasses($seance->id);

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
        } catch (UniqueConstraintViolationException) {
            throw $this->placesDejaPrises($seance, $placeIds);
        }
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

    /**
     * @param  list<int>  $placeIds
     */
    private function placesDejaPrises(Seance $seance, array $placeIds): PlacesDejaPrisesException
    {
        $prises = Place::query()
            ->whereIn('id', Billet::query()
                ->where('seance_id', $seance->id)
                ->whereIn('place_id', $placeIds)
                ->select('place_id'))
            ->orderBy('rangee')
            ->orderBy('numero')
            ->get();

        return new PlacesDejaPrisesException(
            $prises->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            $prises->map(fn (Place $place) => $place->libelle())->values()->all(),
        );
    }
}
