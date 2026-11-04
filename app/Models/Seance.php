<?php

namespace App\Models;

use App\Enums\LangueSeance;
use App\Enums\StatutSeance;
use Database\Factories\SeanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property LangueSeance $langue
 * @property StatutSeance $statut
 */
#[Fillable(['film_id', 'salle_id', 'debut', 'langue', 'statut'])]
class Seance extends Model
{
    /** @use HasFactory<SeanceFactory> */
    use HasFactory;

    /** Temps de nettoyage entre deux séances d'une même salle, en minutes. */
    public const int NETTOYAGE_MINUTES = 15;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debut' => 'datetime',
            'langue' => LangueSeance::class,
            'statut' => StatutSeance::class,
        ];
    }

    /**
     * @return BelongsTo<Film, $this>
     */
    public function film(): BelongsTo
    {
        return $this->belongsTo(Film::class);
    }

    /**
     * @return BelongsTo<Salle, $this>
     */
    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    /**
     * @return HasMany<Tarif, $this>
     */
    public function tarifs(): HasMany
    {
        return $this->hasMany(Tarif::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * @return HasMany<Billet, $this>
     */
    public function billets(): HasMany
    {
        return $this->hasMany(Billet::class);
    }

    /**
     * @param  Builder<Seance>  $query
     */
    #[Scope]
    protected function publiee(Builder $query): void
    {
        $query->where('statut', StatutSeance::Publiee);
    }
}
