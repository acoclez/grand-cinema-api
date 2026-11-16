<?php

namespace App\Http\Resources;

use App\Models\Seance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Séance telle que la voit un visiteur: son film, sa salle, sa version et ses places restantes.
 *
 * @mixin Seance
 */
class SeancePubliqueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'film' => [
                'id' => $this->film->id,
                'titre' => $this->film->titre,
                'duree_minutes' => $this->film->duree_minutes,
                'genre' => $this->film->genre,
                'classification' => $this->film->classification,
                'affiche_url' => $this->film->affiche_url,
            ],
            'salle' => [
                'nom' => $this->salle->nom,
                'type' => $this->salle->type,
            ],
            // Heure de Paris avec son décalage, par exemple 2026-11-20T20:30:00+01:00.
            'debut' => $this->debut->toIso8601String(),
            'fin' => $this->fin()->toIso8601String(),
            'langue' => $this->langue,
            'places_restantes' => $this->placesRestantes(),
            'bientot_complet' => $this->estBientotComplete(),
            'complet' => $this->estComplete(),
        ];
    }
}
