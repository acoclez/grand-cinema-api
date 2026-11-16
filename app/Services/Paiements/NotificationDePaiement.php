<?php

namespace App\Services\Paiements;

/**
 * Résultat d'un paiement annoncé par le prestataire.
 */
final readonly class NotificationDePaiement
{
    public function __construct(public string $reference, public EtatDuPaiement $etat) {}
}
