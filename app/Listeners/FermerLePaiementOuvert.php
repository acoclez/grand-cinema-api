<?php

namespace App\Listeners;

use App\Events\ReservationLiberee;
use App\Services\Paiements\PaiementEnLigne;

/**
 * Les places libérées ne peuvent plus être payées: la page de paiement encore ouverte chez le
 * prestataire est fermée.
 */
readonly class FermerLePaiementOuvert
{
    public function __construct(private PaiementEnLigne $paiement) {}

    public function handle(ReservationLiberee $evenement): void
    {
        $this->paiement->fermerLesPaiementsOuverts($evenement->reservation);
    }
}
