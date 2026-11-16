<?php

namespace App\Models;

use App\Enums\ClassificationFilm;
use Database\Factories\FilmFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ClassificationFilm $classification
 * @property Carbon|null $archive_at
 */
#[Fillable(['titre', 'synopsis', 'duree_minutes', 'genre', 'classification', 'langue_originale', 'affiche_url', 'bande_annonce_url', 'archive_at'])]
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
            'classification' => ClassificationFilm::class,
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

    public function estArchive(): bool
    {
        return $this->archive_at !== null;
    }

    /**
     * Retire le film du site public, il reste en base pour l'historique et les statistiques.
     * Archiver un film déjà archivé ne change pas sa date d'archivage.
     */
    public function archiver(): void
    {
        if (! $this->estArchive()) {
            $this->update(['archive_at' => now()]);
        }
    }

    /**
     * Remet le film à l'affiche.
     */
    public function restaurer(): void
    {
        $this->update(['archive_at' => null]);
    }
}
