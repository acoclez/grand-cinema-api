<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réservation dans les résultats d'une recherche au guichet: de quoi la reconnaître, avec son
 * statut. Le détail s'obtient avec GET /api/guichet/reservations/{reservation}.
 *
 * @mixin Reservation
 */
class ReservationTrouveeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $seance = $this->seance;

        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'statut' => $this->statut,
            'canal' => $this->canal,
            // Null pour une vente au guichet et pour un compte supprimé.
            'client' => ReservationAuGuichetResource::client($this->user),
            'seance' => [
                'id' => $seance->id,
                'film' => ['id' => $seance->film->id, 'titre' => $seance->film->titre],
                'salle' => ['nom' => $seance->salle->nom],
                'debut' => $seance->debut->toIso8601String(),
            ],
            // Nombre de places: 0 une fois les places d'une réservation annulée ou expirée libérées.
            'places' => $this->billets_count ?? 0,
            'montant_centimes' => $this->montant_centimes,
            // Statut du dernier paiement en ligne, null pour une vente au guichet.
            'paiement' => $this->paiements->sortByDesc('id')->first()?->statut,
        ];
    }
}
