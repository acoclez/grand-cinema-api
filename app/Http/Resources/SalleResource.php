<?php

namespace App\Http\Resources;

use App\Models\Salle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Salle avec le nombre de places de son plan.
 *
 * @mixin Salle
 */
class SalleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'type' => $this->type,
            'capacite' => $this->capacite,
            // Places du plan de salle, chargées par withCount dans le contrôleur.
            'places' => (int) $this->getAttribute('places_count'),
            'places_pmr' => (int) $this->getAttribute('places_pmr_count'),
        ];
    }
}
