<?php

namespace App\Http\Resources;

use App\Services\Billets\ControleDuBillet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Résultat du contrôle d'un billet à l'entrée: valide, déjà utilisé ou invalide, le message à
 * afficher et, si le billet est reconnu, son film, sa salle et sa place.
 *
 * @property ControleDuBillet $resource
 */
class ControleBilletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $billet = $this->resource->billet;

        return [
            'resultat' => $this->resource->resultat,
            'message' => $this->resource->message,
            // Billet reconnu, null pour un QR code inconnu ou un billet non payé.
            'billet' => $billet === null ? null : [
                'film' => $billet->seance->film->titre,
                'salle' => $billet->seance->salle->nom,
                'debut' => $billet->seance->debut->toIso8601String(),
                'place' => [
                    'rangee' => $billet->place->rangee,
                    'numero' => $billet->place->numero,
                    'pmr' => $billet->place->pmr,
                ],
                'tarif' => $billet->tarif?->libelle,
                // Heure du premier passage en salle.
                'utilise_at' => $billet->utilise_at?->toIso8601String(),
            ],
        ];
    }
}
