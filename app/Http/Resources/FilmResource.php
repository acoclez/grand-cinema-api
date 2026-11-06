<?php

namespace App\Http\Resources;

use App\Models\Film;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Film tel que le voit l'administrateur, archivé ou non.
 *
 * @mixin Film
 */
class FilmResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'synopsis' => $this->synopsis,
            'duree_minutes' => $this->duree_minutes,
            'genre' => $this->genre,
            'classification' => $this->classification,
            'langue_originale' => $this->langue_originale,
            'affiche_url' => $this->affiche_url,
            'bande_annonce_url' => $this->bande_annonce_url,
            'archive' => $this->estArchive(),
            'archive_le' => $this->archive_at?->toIso8601String(),
        ];
    }
}
