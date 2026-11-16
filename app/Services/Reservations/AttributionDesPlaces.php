<?php

namespace App\Services\Reservations;

use App\Exceptions\PlacesDejaPrisesException;
use App\Exceptions\RegleMetierException;
use App\Models\Billet;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Seance;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Attribue des places d'une séance à une réservation, en ligne comme au guichet: le site et
 * le guichet puisent ainsi dans le même stock de places.
 *
 * La base de données arbitre les demandes simultanées: la contrainte d'unicité
 * (séance, place) de la table billets refuse la seconde écriture. La réservation et ses
 * billets sont créés dans une même transaction: si un billet est refusé, rien n'est
 * enregistré et la réponse indique quelles places viennent d'être prises.
 */
class AttributionDesPlaces
{
    /**
     * @param  list<int>  $placeIds  Places demandées, déjà validées
     * @param  Closure(): Reservation  $creer  Crée la réservation et ses billets
     *
     * @throws RegleMetierException Séance déjà commencée
     * @throws PlacesDejaPrisesException Une place est déjà vendue ou bloquée
     */
    public function attribuer(Seance $seance, array $placeIds, Closure $creer): Reservation
    {
        $seance->verifierReservable();

        try {
            return DB::transaction(function () use ($seance, $creer): Reservation {
                // Les blocages dépassés libèrent leurs places sans attendre la tâche planifiée.
                Reservation::expirerLesBlocagesDepasses($seance->id);

                return $creer();
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->placesDejaPrises($seance, $placeIds);
        }
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
