<?php

namespace App\Http\Resources;

use App\Models\Billet;
use App\Services\Billets\QrCodeDuBillet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;

/**
 * Billet électronique d'une place: la séance, la place, le tarif et le QR code à présenter à
 * l'entrée.
 *
 * @mixin Billet
 */
class BilletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $seance = $this->seance;

        return [
            'id' => $this->id,
            'reservation' => ['id' => $this->reservation->id, 'numero' => $this->reservation->numero],
            'seance' => [
                'id' => $seance->id,
                'film' => ['id' => $seance->film->id, 'titre' => $seance->film->titre],
                'salle' => ['nom' => $seance->salle->nom],
                'debut' => $seance->debut->toIso8601String(),
                'langue' => $seance->langue,
            ],
            'place' => [
                'rangee' => $this->place->rangee,
                'numero' => $this->place->numero,
                'pmr' => $this->place->pmr,
            ],
            'tarif' => $this->tarif?->libelle,
            'prix_centimes' => $this->prix_centimes,
            // Heure du passage en salle, null tant que le billet n'a pas servi.
            'utilise_at' => $this->utilise_at?->toIso8601String(),
            // QR code à présenter à l'entrée: image SVG en adresse data:, qui ne contient que le
            // jeton aléatoire du billet.
            'qr_code' => App::make(QrCodeDuBillet::class)->svg($this->resource),
        ];
    }
}
