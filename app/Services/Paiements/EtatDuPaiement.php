<?php

namespace App\Services\Paiements;

/**
 * État d'un paiement chez le prestataire.
 */
enum EtatDuPaiement
{
    /** La page de paiement est ouverte: le spectateur n'a pas encore payé. */
    case EnCours;

    /** Le paiement est accepté: la réservation peut être confirmée. */
    case Accepte;

    /** La page de paiement est fermée sans paiement. */
    case Abandonne;
}
