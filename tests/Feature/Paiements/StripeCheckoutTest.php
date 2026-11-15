<?php

namespace Tests\Feature\Paiements;

use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Exceptions\NotificationDePaiementInvalideException;
use App\Models\Billet;
use App\Models\Film;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Models\Seance;
use App\Models\Tarif;
use App\Services\Paiements\EtatDuPaiement;
use App\Services\Paiements\StripeCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Stripe\ApiRequestor;
use Stripe\StripeClient;
use Tests\Doubles\ClientHttpStripeSimule;
use Tests\TestCase;

/**
 * Les requêtes envoyées à Stripe et la lecture de ses notifications signées. Le client HTTP du
 * SDK est simulé: aucune requête ne part vers Stripe.
 */
class StripeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'Europe/Paris'));
        config(['services.stripe.secret' => 'sk_test_cle', 'services.stripe.webhook_secret' => self::SECRET]);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    /**
     * @param  list<array<string, mixed>>  $reponses
     */
    private function stripe(array $reponses = []): ClientHttpStripeSimule
    {
        $client = new ClientHttpStripeSimule($reponses);
        ApiRequestor::setHttpClient($client);

        return $client;
    }

    private function prestataire(): StripeCheckout
    {
        return new StripeCheckout(new StripeClient('sk_test_cle'), self::SECRET);
    }

    /**
     * Notification signée comme Stripe la signe: HMAC SHA-256 de « horodatage.contenu ».
     *
     * @param  array<string, mixed>  $session
     * @return array{0: string, 1: string} Contenu et en-tête Stripe-Signature
     */
    private function notification(string $type, array $session, string $secret = self::SECRET): array
    {
        $contenu = (string) json_encode([
            'id' => 'evt_test',
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => ['object' => 'checkout.session'] + $session],
        ]);
        // Le SDK compare l'horodatage à l'heure réelle, pas à celle figée par le test.
        $horodatage = time();

        return [$contenu, 't='.$horodatage.',v1='.hash_hmac('sha256', $horodatage.'.'.$contenu, $secret)];
    }

    public function test_la_page_de_paiement_reprend_les_places_par_tarif(): void
    {
        $seance = Seance::factory()->create(['film_id' => Film::factory()->create(['titre' => 'Signal perdu'])->id]);
        $plein = Tarif::factory()->create(['seance_id' => $seance->id, 'libelle' => 'Plein', 'prix_centimes' => 950]);
        $reduit = Tarif::factory()->create(['seance_id' => $seance->id, 'libelle' => 'Réduit', 'prix_centimes' => 700]);
        $reservation = Reservation::factory()->create(['seance_id' => $seance->id, 'numero' => 'R-ABCDEFGH']);
        foreach ([$plein, $plein, $reduit] as $tarif) {
            Billet::factory()->create(['reservation_id' => $reservation->id, 'seance_id' => $seance->id, 'tarif_id' => $tarif->id, 'prix_centimes' => $tarif->prix_centimes]);
        }
        $stripe = $this->stripe([['id' => 'cs_test_1', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']]);

        $session = $this->prestataire()->ouvrir($reservation, 'https://site/retour', 'https://site/abandon');

        $this->assertSame('cs_test_1', $session->reference);
        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_1', $session->url);
        $requete = $stripe->requetes[0];
        $this->assertSame('post', $requete['methode']);
        $this->assertSame('https://api.stripe.com/v1/checkout/sessions', $requete['url']);
        $this->assertSame([
            'mode' => 'payment',
            'line_items' => [
                ['price_data' => ['currency' => 'eur', 'unit_amount' => 950, 'product_data' => ['name' => 'Signal perdu, tarif Plein']], 'quantity' => 2],
                ['price_data' => ['currency' => 'eur', 'unit_amount' => 700, 'product_data' => ['name' => 'Signal perdu, tarif Réduit']], 'quantity' => 1],
            ],
            'client_reference_id' => (string) $reservation->id,
            'metadata' => ['reservation' => 'R-ABCDEFGH'],
            'locale' => 'fr',
            'success_url' => 'https://site/retour',
            'cancel_url' => 'https://site/abandon',
            // Durée minimale imposée par Stripe: 30 minutes.
            'expires_at' => Carbon::parse('2026-11-10 10:30', 'Europe/Paris')->getTimestamp(),
        ], $requete['parametres']);
    }

    public function test_l_etat_du_paiement_est_lu_sur_la_session_stripe(): void
    {
        $stripe = $this->stripe([
            ['id' => 'cs_1', 'object' => 'checkout.session', 'status' => 'complete', 'payment_status' => 'paid'],
            ['id' => 'cs_2', 'object' => 'checkout.session', 'status' => 'expired', 'payment_status' => 'unpaid'],
            ['id' => 'cs_3', 'object' => 'checkout.session', 'status' => 'open', 'payment_status' => 'unpaid'],
        ]);
        $prestataire = $this->prestataire();

        $this->assertSame(EtatDuPaiement::Accepte, $prestataire->consulter('cs_1'));
        $this->assertSame(EtatDuPaiement::Abandonne, $prestataire->consulter('cs_2'));
        $this->assertSame(EtatDuPaiement::EnCours, $prestataire->consulter('cs_3'));
        $this->assertSame(['get', 'https://api.stripe.com/v1/checkout/sessions/cs_1'], [$stripe->requetes[0]['methode'], $stripe->requetes[0]['url']]);
    }

    public function test_fermer_expire_la_session_et_rembourser_ne_peut_pas_rembourser_deux_fois(): void
    {
        $stripe = $this->stripe([
            ['id' => 'cs_1', 'object' => 'checkout.session', 'status' => 'expired'],
            ['id' => 'cs_1', 'object' => 'checkout.session', 'payment_intent' => 'pi_123'],
            ['id' => 're_1', 'object' => 'refund'],
        ]);
        $prestataire = $this->prestataire();

        $prestataire->fermer('cs_1');
        $prestataire->rembourser('cs_1');

        $this->assertSame('https://api.stripe.com/v1/checkout/sessions/cs_1/expire', $stripe->requetes[0]['url']);
        $this->assertSame('https://api.stripe.com/v1/refunds', $stripe->requetes[2]['url']);
        $this->assertSame(['payment_intent' => 'pi_123'], $stripe->requetes[2]['parametres']);
        $this->assertContains('Idempotency-Key: remboursement-cs_1', $stripe->requetes[2]['entetes']);
    }

    public function test_seules_les_notifications_signees_par_stripe_sont_lues(): void
    {
        $prestataire = $this->prestataire();

        [$contenu, $signature] = $this->notification('checkout.session.completed', ['id' => 'cs_1', 'status' => 'complete', 'payment_status' => 'paid']);
        $notification = $prestataire->lireNotification($contenu, $signature);
        $this->assertSame('cs_1', $notification?->reference);
        $this->assertSame(EtatDuPaiement::Accepte, $notification->etat);

        [$contenu, $signature] = $this->notification('checkout.session.expired', ['id' => 'cs_2', 'status' => 'expired', 'payment_status' => 'unpaid']);
        $this->assertSame(EtatDuPaiement::Abandonne, $prestataire->lireNotification($contenu, $signature)?->etat);

        [$contenu, $signature] = $this->notification('customer.created', ['id' => 'cus_1']);
        $this->assertNull($prestataire->lireNotification($contenu, $signature));

        [$contenu, $signature] = $this->notification('checkout.session.completed', ['id' => 'cs_1'], 'whsec_autre');
        $this->expectException(NotificationDePaiementInvalideException::class);
        $prestataire->lireNotification($contenu, $signature);
    }

    public function test_la_route_des_notifications_confirme_la_reservation_ou_refuse_une_fausse_notification(): void
    {
        $reservation = Reservation::factory()->create(['statut' => StatutReservation::EnAttente, 'expire_at' => now()->addMinutes(5)]);
        Billet::factory()->create(['reservation_id' => $reservation->id, 'seance_id' => $reservation->seance_id]);
        Paiement::factory()->create(['reservation_id' => $reservation->id, 'reference_externe' => 'cs_1', 'statut' => StatutPaiement::EnAttente]);
        $session = ['id' => 'cs_1', 'status' => 'complete', 'payment_status' => 'paid'];

        [$contenu, $signature] = $this->notification('checkout.session.completed', $session, 'whsec_autre');
        $this->call('POST', route('paiements.notifications'), server: ['HTTP_STRIPE_SIGNATURE' => $signature], content: $contenu)
            ->assertStatus(400)
            ->assertExactJson(['message' => 'Notification de paiement invalide.']);
        $this->assertSame(StatutReservation::EnAttente, $reservation->refresh()->statut);

        [$contenu, $signature] = $this->notification('checkout.session.completed', $session);
        $this->call('POST', route('paiements.notifications'), server: ['HTTP_STRIPE_SIGNATURE' => $signature], content: $contenu)
            ->assertOk();
        $this->assertSame(StatutReservation::Confirmee, $reservation->refresh()->statut);
    }
}
