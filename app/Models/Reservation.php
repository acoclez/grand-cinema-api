<?php

namespace App\Models;

use App\Enums\CanalVente;
use App\Enums\StatutReservation;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property StatutReservation $statut
 * @property CanalVente $canal
 */
#[Fillable(['numero', 'user_id', 'seance_id', 'statut', 'canal', 'montant_centimes', 'expire_at'])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    /** Durée pendant laquelle les places sont bloquées avant paiement, en minutes */
    public const int BLOCAGE_MINUTES = 10;

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
