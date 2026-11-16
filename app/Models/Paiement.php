<?php

namespace App\Models;

use App\Enums\StatutPaiement;
use Database\Factories\PaiementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property StatutPaiement $statut
 */
#[Fillable(['reservation_id', 'prestataire', 'reference_externe', 'statut', 'montant_centimes'])]
class Paiement extends Model
{
    /** @use HasFactory<PaiementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'statut' => StatutPaiement::class,
            'montant_centimes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
