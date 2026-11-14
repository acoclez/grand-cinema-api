<?php

namespace App\Http\Resources;

use App\Models\Billet;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réservation en ligne ou vente au guichet: la séance, les places avec leur tarif et leur prix,
 * le montant et le temps restant avant la fin du blocage.
 *
 * @mixin Reservation
 */
class ReservationResource extends JsonResource
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
            // Somme des prix des billets, 0 tant que les tarifs ne sont pas choisis.
            'montant_centimes' => $this->montant_centimes,
            // Fin du blocage des places, null une fois la réservation confirmée, annulée ou expirée.
            'expire_at' => $this->expire_at?->toIso8601String(),
            'secondes_restantes' => $this->secondesRestantes(),
            'seance' => [
                'id' => $seance->id,
                'film' => ['id' => $seance->film->id, 'titre' => $seance->film->titre],
                'salle' => ['nom' => $seance->salle->nom, 'type' => $seance->salle->type],
                'debut' => $seance->debut->toIso8601String(),
                'langue' => $seance->langue,
            ],
            'places' => $this->billets->map(fn (Billet $billet) => [
                'id' => $billet->place->id,
                'rangee' => $billet->place->rangee,
                'numero' => $billet->place->numero,
                'pmr' => $billet->place->pmr,
                // Tarif choisi pour la place, null tant qu'il n'est pas choisi.
                'tarif' => $billet->tarif?->libelle,
                'prix_centimes' => $billet->prix_centimes,
            ])->sortBy(['rangee', 'numero'])->values(),
        ];
    }
}
