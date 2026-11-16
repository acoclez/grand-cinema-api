<?php

namespace App\Models;

use App\Enums\TypeSalle;
use Database\Factories\SalleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property TypeSalle $type
 */
#[Fillable(['nom', 'type',  'capacite'])]
class Salle extends Model
{
    /** @use HasFactory<SalleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeSalle::class,
            'capacite' => 'integer',
        ];
    }

    /**
     * @return HasMany<Place, $this>
     */
    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    /**
     * @return HasMany<Seance, $this>
     */
    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }
}
