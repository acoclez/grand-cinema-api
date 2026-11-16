<?php

namespace App\Models;

use Database\Factories\PlaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['salle_id', 'rangee', 'numero', 'pmr'])]
class Place extends Model
{
    /** @use HasFactory<PlaceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'pmr' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Salle, $this>
     */
    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    /**
     * @return HasMany<Billet, $this>
     */
    public function billets(): HasMany
    {
        return $this->hasMany(Billet::class);
    }

    public function libelle(): string
    {
        return $this->rangee.$this->numero;
    }
}
