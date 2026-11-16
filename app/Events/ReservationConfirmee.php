<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Le paiement en ligne d'une réservation vient d'être accepté: ses places sont définitivement
 * attribuées. Diffusé une fois la transaction validée.
 */
class ReservationConfirmee implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Reservation $reservation) {}
}
