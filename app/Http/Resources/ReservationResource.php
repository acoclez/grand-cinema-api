<?php

namespace App\Http\Resources;

use App\Models\Billet;
use App\Models\Reservation;
use App\Models\Tarif;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réservation en ligne ou vente au guichet: la séance, les places avec leur tarif et leur prix,
 * le montant, le temps restant avant la fin du blocage, le statut du paiement en ligne et
 * l'état de chaque billet.
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
        $paiement = $this->paiements->sortByDesc('id')->first();

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
            // Statut du dernier paiement en ligne, null avant le paiement et pour une vente au guichet.
            'paiement' => $paiement?->statut,
            'seance' => [
                'id' => $seance->id,
                'film' => ['id' => $seance->film->id, 'titre' => $seance->film->titre],
                'salle' => ['nom' => $seance->salle->nom, 'type' => $seance->salle->type],
                'debut' => $seance->debut->toIso8601String(),
                'langue' => $seance->langue,
                // Tarifs à choisir pour chaque place avant de payer.
                'tarifs' => $seance->tarifs->map(fn (Tarif $tarif) => [
                    'id' => $tarif->id,
                    'libelle' => $tarif->libelle,
                    'prix_centimes' => $tarif->prix_centimes,
                ])->values(),
            ],
            'places' => $this->billets->map(fn (Billet $billet) => [
                'id' => $billet->place->id,
                'rangee' => $billet->place->rangee,
                'numero' => $billet->place->numero,
                'pmr' => $billet->place->pmr,
                // Tarif choisi pour la place, null tant qu'il n'est pas choisi.
                'tarif' => $billet->tarif?->libelle,
                'prix_centimes' => $billet->prix_centimes,
                // Heure du passage en salle, null tant que le billet n'a pas servi.
                'utilise_at' => $billet->utilise_at?->toIso8601String(),
            ])->sortBy(['rangee', 'numero'])->values(),
        ];
    }
}
