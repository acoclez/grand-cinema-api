<?php

namespace Tests\Doubles;

use App\Models\Reservation;
use App\Services\Paiements\EtatDuPaiement;
use App\Services\Paiements\NotificationDePaiement;
use App\Services\Paiements\PrestataireDePaiement;
use App\Services\Paiements\SessionDePaiement;
use RuntimeException;

/**
 * Prestataire de paiement des tests: aucune requête vers Stripe. Il garde la trace des pages
 * ouvertes, fermées et remboursées, et donne l'état de paiement choisi par le test.
 */
class PrestataireDePaiementSimule implements PrestataireDePaiement
{
    /** @var list<array{reference: string, montant: int, retour: string, abandon: string}> */
    public array $ouvertes = [];

    /** @var list<string> */
    public array $fermees = [];

    /** @var list<string> */
    public array $remboursees = [];

    /** @var array<string, EtatDuPaiement> État de chaque page, en cours par défaut */
    public array $etats = [];

    /** La fermeture échoue, comme pour une page que le spectateur vient de payer. */
    public bool $fermetureImpossible = false;

    public function nom(): string
    {
        return 'stripe';
    }

    public function ouvrir(Reservation $reservation, string $urlRetour, string $urlAbandon): SessionDePaiement
    {
        $reference = 'cs_test_'.(count($this->ouvertes) + 1);
        $this->ouvertes[] = [
            'reference' => $reference,
            'montant' => $reservation->montant_centimes,
            'retour' => $urlRetour,
            'abandon' => $urlAbandon,
        ];

        return new SessionDePaiement($reference, 'https://checkout.stripe.com/c/pay/'.$reference);
    }

    public function consulter(string $reference): EtatDuPaiement
    {
        return $this->etats[$reference] ?? EtatDuPaiement::EnCours;
    }

    public function fermer(string $reference): void
    {
        if ($this->fermetureImpossible) {
            throw new RuntimeException('Cette page de paiement est déjà payée.');
        }
        $this->fermees[] = $reference;
    }

    public function rembourser(string $reference): void
    {
        $this->remboursees[] = $reference;
    }

    /**
     * Le contenu de la notification simulée est « référence:état », par exemple cs_test_1:Accepte.
     */
    public function lireNotification(string $contenu, string $signature): ?NotificationDePaiement
    {
        [$reference, $etat] = explode(':', $contenu);

        return new NotificationDePaiement($reference, constant(EtatDuPaiement::class.'::'.$etat));
    }
}
