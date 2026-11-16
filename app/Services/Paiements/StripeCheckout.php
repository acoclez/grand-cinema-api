<?php

namespace App\Services\Paiements;

use App\Exceptions\NotificationDePaiementInvalideException;
use App\Models\Billet;
use App\Models\Reservation;
use Illuminate\Support\Collection;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Paiement par carte avec Stripe Checkout: la page de paiement est hébergée par Stripe,
 * certifié PCI DSS. L'application ne reçoit que l'identifiant de la session de paiement.
 * Les moyens de paiement proposés se règlent dans le tableau de bord Stripe: l'API Stripe
 * n'accepte plus de les choisir à la création de la session.
 */
readonly class StripeCheckout implements PrestataireDePaiement
{
    /**
     * Durée minimale d'ouverture d'une page Stripe Checkout. Le blocage des places est plus
     * court (10 minutes): l'application ferme la page dès que les places sont libérées.
     */
    private const int OUVERTURE_MINUTES = 30;

    public function __construct(private StripeClient $stripe, private string $secretDesNotifications) {}

    public function nom(): string
    {
        return 'stripe';
    }

    public function ouvrir(Reservation $reservation, string $urlRetour, string $urlAbandon): SessionDePaiement
    {
        $reservation->loadMissing(['seance.film', 'billets.tarif']);
        $film = $reservation->seance->film->titre;

        // Une ligne par tarif, par exemple « Signal perdu, tarif Plein » × 2.
        $lignes = $reservation->billets
            ->groupBy('tarif_id')
            ->map(function (Collection $billets) use ($film): array {
                $billet = $billets->first();
                assert($billet instanceof Billet);

                return [
                    'price_data' => [
                        'currency' => 'eur',
                        'unit_amount' => $billet->prix_centimes,
                        'product_data' => ['name' => $film.', tarif '.$billet->tarif?->libelle],
                    ],
                    'quantity' => $billets->count(),
                ];
            })
            ->values()
            ->all();

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => $lignes,
            'client_reference_id' => (string) $reservation->id,
            'metadata' => ['reservation' => $reservation->numero],
            'locale' => 'fr',
            'success_url' => $urlRetour,
            'cancel_url' => $urlAbandon,
            'expires_at' => now()->addMinutes(self::OUVERTURE_MINUTES)->getTimestamp(),
        ]);

        return new SessionDePaiement($session->id, (string) $session->url);
    }

    public function consulter(string $reference): EtatDuPaiement
    {
        return $this->etat($this->stripe->checkout->sessions->retrieve($reference));
    }

    public function fermer(string $reference): void
    {
        $this->stripe->checkout->sessions->expire($reference);
    }

    public function rembourser(string $reference): void
    {
        $paiement = $this->stripe->checkout->sessions->retrieve($reference)->payment_intent;

        // La clé d'idempotence évite un second remboursement si la demande est répétée.
        $this->stripe->refunds->create(
            ['payment_intent' => is_string($paiement) ? $paiement : (string) $paiement?->id],
            ['idempotency_key' => 'remboursement-'.$reference],
        );
    }

    public function lireNotification(string $contenu, string $signature): ?NotificationDePaiement
    {
        try {
            $evenement = Webhook::constructEvent($contenu, $signature, $this->secretDesNotifications);
        } catch (SignatureVerificationException|UnexpectedValueException $erreur) {
            throw new NotificationDePaiementInvalideException($erreur->getMessage(), previous: $erreur);
        }

        $session = $evenement->data->object;
        $concerneUnPaiement = in_array($evenement->type, ['checkout.session.completed', 'checkout.session.expired'], true);

        return $concerneUnPaiement && $session instanceof Session
            ? new NotificationDePaiement($session->id, $this->etat($session))
            : null;
    }

    private function etat(Session $session): EtatDuPaiement
    {
        return match (true) {
            $session->payment_status === Session::PAYMENT_STATUS_PAID => EtatDuPaiement::Accepte,
            $session->status === Session::STATUS_EXPIRED => EtatDuPaiement::Abandonne,
            default => EtatDuPaiement::EnCours,
        };
    }
}
