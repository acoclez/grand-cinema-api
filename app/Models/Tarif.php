<?php

namespace App\Models;

use Database\Factories\TarifFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['seance_id', 'libelle', 'prix_centimes'])]
class Tarif extends Model
{
    /** @use HasFactory<TarifFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prix_centimes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Seance, $this>
     */
    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }
}
