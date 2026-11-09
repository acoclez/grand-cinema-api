<?php

namespace App\Http\Resources;

use App\Models\Place;
use App\Models\Reservation;
use App\Models\Seance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Plan de salle d'une séance: chaque place avec sa rangée, son numéro, si elle est
 * réservée aux personnes à mobilité réduite et si elle est libre.
 *
 * @mixin Seance
 */
class PlanDeSalleResource extends JsonResource
{
    /**
     * @param  list<int>  $placesOccupees
     */
    public function __construct(Seance $seance, private readonly array $placesOccupees)
    {
        parent::__construct($seance);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $occupees = array_flip($this->placesOccupees);

        return [
            'seance' => [
                'id' => $this->id,
                'film' => [
                    'id' => $this->film->id,
                    'titre' => $this->film->titre,
                    'duree_minutes' => $this->film->duree_minutes,
                    'classification' => $this->film->classification,
                    'affiche_url' => $this->film->affiche_url,
                ],
                'salle' => [
                    'nom' => $this->salle->nom,
                    'type' => $this->salle->type,
                    'capacite' => $this->salle->capacite,
                ],
                'debut' => $this->debut->toIso8601String(),
                'fin' => $this->fin()->toIso8601String(),
                'langue' => $this->langue,
            ],
            // Faux quand la séance a commencé: le plan reste consultable, sans réservation.
            'reservable' => $this->estReservable(),
            'places_max' => Reservation::PLACES_MAX,
            'blocage_minutes' => Reservation::BLOCAGE_MINUTES,
            'places_libres' => $this->salle->places->count() - count($occupees),
            // Places triées par rangée puis par numéro.
            'places' => $this->salle->places->map(fn (Place $place) => [
                'id' => $place->id,
                'rangee' => $place->rangee,
                'numero' => $place->numero,
                'pmr' => $place->pmr,
                'libre' => ! isset($occupees[$place->id]),
            ])->values(),
        ];
    }
}
