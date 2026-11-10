<?php

namespace App\Models;

use App\Enums\CanalVente;
use App\Enums\StatutReservation;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property string $numero
 * @property StatutReservation $statut
 * @property CanalVente $canal
 * @property Carbon|null $expire_at
 */
#[Fillable(['numero', 'user_id', 'seance_id', 'statut', 'canal', 'montant_centimes', 'expire_at'])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    /** Durée pendant laquelle les places sont bloquées avant paiement, en minutes */
    public const int BLOCAGE_MINUTES = 10;

    /** Nombre maximal de places par réservation en ligne. */
    public const int PLACES_MAX = 10;

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation): void {
            $reservation->numero ??= self::genererNumero();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'statut' => StatutReservation::class,
            'canal' => CanalVente::class,
            'montant_centimes' => 'integer',
            'expire_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Seance, $this>
     */
    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    /**
     * @return HasMany<Billet, $this>
     */
    public function billets(): HasMany
    {
        return $this->hasMany(Billet::class);
    }

    /**
     * @return HasMany<Paiement, $this>
     */
    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    /**
     * Réservations en attente dont le blocage de 10 minutes est dépassé.
     *
     * @param  Builder<Reservation>  $query
     */
    #[Scope]
    protected function blocageDepasse(Builder $query): void
    {
        $query->where('statut', StatutReservation::EnAttente)->where('expire_at', '<=', now());
    }

    /**
     * Les places sont bloquées pour le spectateur: réservation en attente et blocage en cours.
     */
    public function estBloquee(): bool
    {
        return $this->statut === StatutReservation::EnAttente
            && $this->expire_at !== null
            && $this->expire_at->isFuture();
    }

    /**
     * Secondes restantes avant la fin du blocage, 0 une fois le blocage terminé.
     * Calculées par l'API, pour ne pas dépendre de l'horloge de l'appareil du spectateur.
     */
    public function secondesRestantes(): int
    {
        if (! $this->estBloquee()) {
            return 0;
        }

        return (int) max(0, now()->diffInSeconds($this->expire_at, false));
    }

    /**
     * Libère les places: les billets sont supprimés et la réservation garde son historique
     * avec le statut indiqué (annulée par le spectateur ou expirée).
     */
    public function liberer(StatutReservation $statut): void
    {
        DB::transaction(function () use ($statut): void {
            $this->billets()->delete();
            $this->update(['statut' => $statut, 'expire_at' => null]);
        });
    }

    /**
     * Passe au statut expiré les réservations en attente dont le blocage est dépassé et
     * supprime leurs billets, ce qui libère les places. Toutes les séances par défaut,
     * ou une seule avant d'y bloquer de nouvelles places.
     *
     * @return int Nombre de réservations expirées
     */
    public static function expirerLesBlocagesDepasses(?int $seanceId = null): int
    {
        $reservations = self::query()
            ->blocageDepasse()
            ->when($seanceId !== null, fn (Builder $query) => $query->where('seance_id', $seanceId))
            ->get();

        $reservations->each(fn (Reservation $reservation) => $reservation->liberer(StatutReservation::Expiree));

        return $reservations->count();
    }

    /**
     * Numéro lisible, sans caractères pouvant porter à confusion
     * Caractères bannis: 0, O, 1, I
     * Exemple: R-7KX4M2PA
     */
    public static function genererNumero(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $numero = 'R-'.Str::upper(substr(str_shuffle(str_repeat($alphabet, 3)), 0, 8));
        } while (self::where('numero', $numero)->exists());

        return $numero;
    }
}
