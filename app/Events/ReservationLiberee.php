<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Les places d'une réservation en attente viennent d'être libérées: abandon par le spectateur,
 * nouvelle sélection sur la même séance ou blocage de 10 minutes dépassé.
 * Diffusé une fois la transaction validée, pour ne pas appeler le prestataire de paiement
 * pendant qu'elle est ouverte.
 */
class ReservationLiberee implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Reservation $reservation) {}
}
