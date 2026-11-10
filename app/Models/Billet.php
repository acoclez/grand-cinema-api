<?php

namespace App\Models;

use App\Enums\StatutReservation;
use Database\Factories\BilletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['reservation_id', 'seance_id', 'place_id', 'tarif_id', 'prix_centimes', 'qr_token', 'utilise_at'])]
class Billet extends Model
{
    /** @use HasFactory<BilletFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Billet $billet): void {
            $billet->qr_token ??= Str::random(48);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prix_centimes' => 'integer',
            'utilise_at' => 'datetime',
        ];
    }

    /**
     * Billets qui occupent leur place: réservation confirmée, ou en attente avec un blocage
     * encore en cours. Le plan de salle ne dépend donc pas du passage de la tâche
     * qui expire les blocages dépassés.
     *
     * @param  Builder<Billet>  $query
     */
    #[Scope]
    protected function occupantSaPlace(Builder $query): void
    {
        $query->whereHas('reservation', fn (Builder $reservation) => $reservation
            ->where('statut', StatutReservation::Confirmee)
            ->orWhere(fn (Builder $enAttente) => $enAttente
                ->where('statut', StatutReservation::EnAttente)
                ->where('expire_at', '>', now())));
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<Seance, $this>
     */
    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    /**
     * @return BelongsTo<Place, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * @return BelongsTo<Tarif, $this>
     */
    public function tarif(): BelongsTo
    {
        return $this->belongsTo(Tarif::class);
    }
}
