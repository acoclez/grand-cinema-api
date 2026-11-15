<?php

namespace App\Services\Paiements;

use App\Exceptions\NotificationDePaiementInvalideException;
use App\Models\Reservation;

/**
 * Prestataire certifié qui encaisse les paiements par carte. Le spectateur saisit sa carte sur
 * la page de paiement du prestataire: l'application ne voit ni n'enregistre aucune donnée de
 * carte, seulement la référence de l'opération.
 *
 * L'interface isole l'application du prestataire choisi (Stripe): les tests la remplacent par
 * un double, et changer de prestataire ne touche que son implémentation.
 */
interface PrestataireDePaiement
{
    /** Nom enregistré dans la table paiements. */
    public function nom(): string;

    /**
     * Ouvre une page de paiement pour le montant de la réservation, tarifs déjà choisis.
     *
     * @param  string  $urlRetour  Page de l'application web ouverte après le paiement
     * @param  string  $urlAbandon  Page ouverte si le spectateur revient sans payer
     */
    public function ouvrir(Reservation $reservation, string $urlRetour, string $urlAbandon): SessionDePaiement;

    /** Demande au prestataire où en est le paiement. */
    public function consulter(string $reference): EtatDuPaiement;

    /** Ferme la page de paiement: elle n'accepte plus de paiement. */
    public function fermer(string $reference): void;

    /** Rembourse un paiement accepté, sans risque de rembourser deux fois. */
    public function rembourser(string $reference): void;

    /**
     * Lit une notification envoyée par le prestataire, après avoir vérifié sa signature.
     * Renvoie null pour un événement qui ne concerne pas le paiement d'une réservation.
     *
     * @throws NotificationDePaiementInvalideException Signature ou contenu invalide
     */
    public function lireNotification(string $contenu, string $signature): ?NotificationDePaiement;
}
