<?php

namespace App\Http\Resources;

use App\Models\Billet;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réservation du spectateur: sa séance, ses places et le temps restant avant la fin du blocage.
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
            ])->sortBy(['rangee', 'numero'])->values(),
        ];
    }
}
