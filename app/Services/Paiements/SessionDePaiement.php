<?php

namespace App\Services\Paiements;

/**
 * Page de paiement ouverte chez le prestataire.
 */
final readonly class SessionDePaiement
{
    /**
     * @param  string  $reference  Identifiant de l'opération chez le prestataire
     * @param  string  $url  Adresse de la page de paiement, où le spectateur saisit sa carte
     */
    public function __construct(public string $reference, public string $url) {}
}
