<?php

namespace App\Http\Resources;

use App\Models\Seance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Séance telle que la voit l'administrateur, avec son film, sa salle et ses tarifs.
 *
 * @mixin Seance
 */
class SeanceResource extends JsonResource
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
                'archive' => $this->film->estArchive(),
            ],
            'salle' => [
                'id' => $this->salle->id,
                'nom' => $this->salle->nom,
                'type' => $this->salle->type,
                'capacite' => $this->salle->capacite,
            ],
            // Heure de Paris avec son décalage, par exemple 2026-11-20T20:30:00+01:00.
            'debut' => $this->debut->toIso8601String(),
            'fin' => $this->fin()->toIso8601String(),
            'langue' => $this->langue,
            'statut' => $this->statut,
            'tarifs' => $this->tarifs->map(fn ($tarif) => [
                'id' => $tarif->id,
                'libelle' => $tarif->libelle,
                'prix_centimes' => $tarif->prix_centimes,
            ])->values()->all(),
            'places_vendues' => $this->placesVendues(),
        ];
    }
}
