<?php

namespace App\Models;

use Database\Factories\FilmFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['titre', 'synopsis', 'duree_minutes', 'genre', 'classification', 'affiche_url', 'bande_annonce_url', 'archive_at'])]
class Film extends Model
{
    /** @use HasFactory<FilmFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duree_minutes' => 'integer',
            'archive_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Seance, $this>
     */
    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }

    /**
     * Films
     *
     * @param  Builder<Film>  $query
     */
    #[Scope]
    protected function aLAffiche(Builder $query): void
    {
        $query->whereNull('archive_at');
    }
}
