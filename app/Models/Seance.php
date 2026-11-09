<?php

namespace App\Models;

use App\Enums\CanalVente;
use App\Enums\LangueSeance;
use App\Enums\StatutReservation;
use App\Enums\StatutSeance;
use App\Exceptions\RegleMetierException;
use Database\Factories\SeanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $debut
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

    /** Part des places vendues à partir de laquelle la séance est annoncée « Bientôt complet ». */
    public const float SEUIL_BIENTOT_COMPLET = 0.9;

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
     * Tarifs dans l'ordre où l'administrateur les a saisis.
     *
     * @return HasMany<Tarif, $this>
     */
    public function tarifs(): HasMany
    {
        return $this->hasMany(Tarif::class)->orderBy('id');
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

    /**
     * La séance apparaît sur le site public: publiée et film à l'affiche.
     */
    public function estVisibleDuPublic(): bool
    {
        return $this->statut === StatutSeance::Publiee && ! $this->film->estArchive();
    }

    /**
     * Des places peuvent encore être réservées: la séance n'a pas commencé.
     */
    public function estReservable(): bool
    {
        return $this->estVisibleDuPublic() && $this->debut->isFuture();
    }

    /**
     * @throws RegleMetierException
     */
    public function verifierReservable(): void
    {
        if (! $this->estReservable()) {
            throw new RegleMetierException('Cette séance a déjà commencé: ses places ne se réservent plus.');
        }
    }

    /**
     * Fin de la projection: début plus durée du film, sans le temps de nettoyage.
     */
    public function fin(): Carbon
    {
        return $this->debut->copy()->addMinutes($this->film->duree_minutes);
    }

    /**
     * Places vendues ou bloquées par une réservation en cours: chaque place prise a un billet.
     * Les billets d'une réservation expirée sont supprimés, ce qui libère la place.
     */
    public function placesVendues(): int
    {
        // Compte déjà chargé par withCount('billets') dans les listes, sinon une requête.
        $compte = $this->getAttribute('billets_count');

        return is_int($compte) ? $compte : $this->billets()->count();
    }

    /**
     * Places encore libres: capacité de la salle moins les places vendues ou bloquées.
     */
    public function placesRestantes(): int
    {
        return max(0, $this->salle->capacite - $this->placesVendues());
    }

    /**
     * Plus aucune place libre: la séance ne se réserve plus.
     */
    public function estComplete(): bool
    {
        return $this->placesRestantes() === 0;
    }

    /**
     * Au moins 90 % des places sont vendues, sans que la séance soit complète.
     */
    public function estBientotComplete(): bool
    {
        return ! $this->estComplete()
            && $this->placesVendues() >= $this->salle->capacite * self::SEUIL_BIENTOT_COMPLET;
    }

    /**
     * Comptes à charger avec withCount ou loadCount pour le remplissage: tous les billets,
     * les billets des réservations confirmées par canal de vente, et ceux des réservations
     * en attente de paiement. Les chiffres sont relus en base à chaque appel.
     *
     * @return array<int|string, mixed>
     */
    public static function comptesDeRemplissage(): array
    {
        return [
            'billets',
            'billets as billets_en_ligne_count' => fn ($billets) => $billets->whereHas('reservation', fn ($reservation) => $reservation
                ->where('statut', StatutReservation::Confirmee)
                ->where('canal', CanalVente::EnLigne)),
            'billets as billets_guichet_count' => fn ($billets) => $billets->whereHas('reservation', fn ($reservation) => $reservation
                ->where('statut', StatutReservation::Confirmee)
                ->where('canal', CanalVente::Guichet)),
            'billets as billets_en_attente_count' => fn ($billets) => $billets->whereHas('reservation', fn ($reservation) => $reservation
                ->where('statut', StatutReservation::EnAttente)),
        ];
    }

    /**
     * Remplissage de la séance (indicateur O4): places vendues en ligne et au guichet,
     * places bloquées par une réservation en attente de paiement, et taux de remplissage
     * en pourcentage arrondi. Seules les ventes confirmées comptent dans le taux.
     * Les comptes viennent de comptesDeRemplissage().
     *
     * @return array{en_ligne: int, guichet: int, en_attente: int, taux: int}
     */
    public function remplissage(): array
    {
        $enLigne = (int) $this->getAttribute('billets_en_ligne_count');
        $guichet = (int) $this->getAttribute('billets_guichet_count');

        return [
            'en_ligne' => $enLigne,
            'guichet' => $guichet,
            'en_attente' => (int) $this->getAttribute('billets_en_attente_count'),
            'taux' => (int) round(($enLigne + $guichet) * 100 / max(1, $this->salle->capacite)),
        ];
    }

    /**
     * Une séance dont des places sont vendues ne change plus: les spectateurs ont acheté
     * un film, une salle et un horaire précis.
     *
     * @throws RegleMetierException
     */
    public function verifierModifiable(): void
    {
        if ($this->placesVendues() > 0) {
            throw new RegleMetierException('Des places sont déjà vendues pour cette séance: elle ne peut plus être modifiée.');
        }
    }

    /**
     * La séance apparaît sur le site public.
     *
     * @throws RegleMetierException
     */
    public function publier(): void
    {
        if ($this->film->estArchive()) {
            throw new RegleMetierException('Le film de cette séance est archivé: remettez-le à l\'affiche avant de publier la séance.');
        }
        if ($this->debut->isPast()) {
            throw new RegleMetierException('Cette séance a déjà commencé: elle ne peut plus être publiée.');
        }

        $this->update(['statut' => StatutSeance::Publiee]);
    }

    /**
     * La séance repasse en brouillon et disparaît du site public.
     *
     * @throws RegleMetierException
     */
    public function depublier(): void
    {
        if ($this->placesVendues() > 0) {
            throw new RegleMetierException('Des places sont déjà vendues pour cette séance: elle ne peut pas être dépubliée.');
        }

        $this->update(['statut' => StatutSeance::Brouillon]);
    }

    /**
     * Seule une séance en brouillon se supprime. Une séance publiée se dépublie d'abord.
     *
     * @throws RegleMetierException
     */
    public function supprimer(): void
    {
        if ($this->statut !== StatutSeance::Brouillon) {
            throw new RegleMetierException('Seule une séance en brouillon peut être supprimée: dépubliez-la d\'abord.');
        }

        $this->delete();
    }
}
