<?php

namespace Tests\Feature\Paiements;

use App\Models\Place;
use App\Models\Reservation;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Tarif;
use App\Models\User;
use App\Services\Paiements\EtatDuPaiement;
use App\Services\Paiements\PrestataireDePaiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Doubles\PrestataireDePaiementSimule;
use Tests\TestCase;

/**
 * Le spectateur choisit un tarif par place, paie sur la page du prestataire, puis sa
 * réservation est confirmée. Le prestataire est simulé: aucune requête vers Stripe.
 */
class PaiementEnLigneTest extends TestCase
{
    use RefreshDatabase;

    private PrestataireDePaiementSimule $prestataire;

    private Seance $seance;

    /** @var array<string, Place> Places de la salle par libellé: A1 à A4 */
    private array $places = [];

    /** @var array<string, Tarif> */
    private array $tarifs = [];

    private User $camille;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        // Les tests se déroulent le 10 novembre 2026 à 10 h, heure de Paris.
        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'Europe/Paris'));
        $this->prestataire = new PrestataireDePaiementSimule;
        $this->app->instance(PrestataireDePaiement::class, $this->prestataire);

        $salle = Salle::factory()->create(['capacite' => 4]);
        foreach (range(1, 4) as $numero) {
            $this->places['A'.$numero] = Place::factory()->create(['salle_id' => $salle->id, 'rangee' => 'A', 'numero' => $numero]);
        }
        $this->seance = Seance::factory()->create(['salle_id' => $salle->id, 'debut' => Carbon::parse('2026-11-12 20:30')]);
        foreach (['Plein' => 950, 'Réduit' => 700] as $libelle => $prix) {
            $this->tarifs[$libelle] = Tarif::factory()->create(['seance_id' => $this->seance->id, 'libelle' => $libelle, 'prix_centimes' => $prix]);
        }
        $this->camille = User::factory()->create();

        $id = $this->actingAs($this->camille, 'api')
            ->postJson(route('reservations.store', $this->seance), ['places' => [$this->places['A1']->id, $this->places['A2']->id]])
            ->assertCreated()
            ->json('data.id');
        $this->reservation = Reservation::findOrFail($id);
    }

    /**
     * @param  array<string, string>  $tarifs  Tarif choisi par place, par exemple ['A1' => 'Plein']
     */
    private function payer(array $tarifs, ?User $spectateur = null): TestResponse
    {
        $billets = [];
        foreach ($tarifs as $place => $tarif) {
            $billets[] = ['place_id' => $this->places[$place]->id, 'tarif_id' => $this->tarifs[$tarif]->id];
        }

        return $this->actingAs($spectateur ?? $this->camille, 'api')
            ->postJson(route('reservations.paiement', $this->reservation), ['billets' => $billets]);
    }

    private function verifier(): TestResponse
    {
        return $this->actingAs($this->camille, 'api')->postJson(route('reservations.paiement.verification', $this->reservation));
    }

    private function notifier(string $reference, string $etat): TestResponse
    {
        return $this->call('POST', route('paiements.notifications'), content: $reference.':'.$etat);
    }

    private function estLibre(string $libelle): bool
    {
        $place = collect($this->getJson(route('seances.plan', $this->seance))->json('data.places'))
            ->firstWhere('id', $this->places[$libelle]->id);

        return $place['libre'];
    }

    public function test_les_tarifs_choisis_donnent_le_total_et_ouvrent_la_page_de_paiement(): void
    {
        $this->payer(['A1' => 'Plein', 'A2' => 'Réduit'])
            ->assertCreated()
            ->assertExactJson(['data' => ['url' => 'https://checkout.stripe.com/c/pay/cs_test_1']]);

        $this->assertSame([[
            'reference' => 'cs_test_1',
            'montant' => 1650,
            'retour' => 'http://localhost:5173/reservations/'.$this->reservation->id.'?paiement=termine',
            'abandon' => 'http://localhost:5173/reservations/'.$this->reservation->id.'?paiement=abandonne',
        ]], $this->prestataire->ouvertes);
        $this->assertDatabaseHas('paiements', [
            'reservation_id' => $this->reservation->id,
            'prestataire' => 'stripe',
            'reference_externe' => 'cs_test_1',
            'statut' => 'en_attente',
            'montant_centimes' => 1650,
        ]);

        // Le récapitulatif donne le détail par place et le total à payer.
        $this->actingAs($this->camille, 'api')->getJson(route('reservations.show', $this->reservation))
            ->assertOk()
            ->assertJsonPath('data.statut', 'en_attente')
            ->assertJsonPath('data.paiement', 'en_attente')
            ->assertJsonPath('data.montant_centimes', 1650)
            ->assertJsonPath('data.places.0.tarif', 'Plein')
            ->assertJsonPath('data.places.0.prix_centimes', 950)
            ->assertJsonPath('data.places.1.tarif', 'Réduit')
            ->assertJsonPath('data.places.1.prix_centimes', 700)
            ->assertJsonPath('data.seance.tarifs.1', ['id' => $this->tarifs['Réduit']->id, 'libelle' => 'Réduit', 'prix_centimes' => 700]);
    }

    public function test_chaque_place_de_la_reservation_recoit_un_tarif_de_la_seance(): void
    {
        $autreSeance = Tarif::factory()->create(['libelle' => 'Plein', 'prix_centimes' => 100]);

        $this->payer(['A1' => 'Plein'])->assertUnprocessable()->assertJsonValidationErrors('billets');
        $this->payer(['A1' => 'Plein', 'A3' => 'Plein'])->assertUnprocessable()->assertJsonValidationErrors('billets.1.place_id');
        $this->actingAs($this->camille, 'api')->postJson(route('reservations.paiement', $this->reservation), ['billets' => [
            ['place_id' => $this->places['A1']->id, 'tarif_id' => $this->tarifs['Plein']->id],
            ['place_id' => $this->places['A2']->id, 'tarif_id' => $autreSeance->id],
        ]])->assertUnprocessable()->assertJsonValidationErrors('billets.1.tarif_id');

        $this->assertSame([], $this->prestataire->ouvertes);
    }

    public function test_seul_le_spectateur_qui_a_reserve_paie_tant_que_ses_places_sont_bloquees(): void
    {
        $this->payer(['A1' => 'Plein', 'A2' => 'Plein'], User::factory()->create())->assertNotFound();
        $this->payer(['A1' => 'Plein', 'A2' => 'Plein'], User::factory()->agent()->create())->assertForbidden();

        $this->travel(10)->minutes();
        $this->payer(['A1' => 'Plein', 'A2' => 'Plein'])
            ->assertConflict()
            ->assertJsonPath('message', 'Le délai de 10 minutes est dépassé: vos places ont été libérées. Choisissez-en de nouvelles.');
        $this->assertSame([], $this->prestataire->ouvertes);
    }

    public function test_au_retour_le_paiement_accepte_confirme_la_reservation_et_les_places_restent_attribuees(): void
    {
        $this->payer(['A1' => 'Plein', 'A2' => 'Réduit']);
        $this->prestataire->etats['cs_test_1'] = EtatDuPaiement::Accepte;

        $this->verifier()
            ->assertOk()
            ->assertJsonPath('data.statut', 'confirmee')
            ->assertJsonPath('data.paiement', 'accepte')
            ->assertJsonPath('data.expire_at', null)
            ->assertJsonPath('data.montant_centimes', 1650);

        // Les places sont définitivement attribuées: la fin du blocage ne les libère pas.
        $this->travel(15)->minutes();
        $this->artisan('reservations:expirer')->assertSuccessful();
        $this->assertFalse($this->estLibre('A1'));
        $this->assertSame('confirmee', $this->reservation->refresh()->statut->value);
        $this->assertSame([], $this->prestataire->remboursees);
    }

    public function test_la_notification_du_prestataire_confirme_la_reservation_une_seule_fois(): void
    {
        $this->payer(['A1' => 'Plein', 'A2' => 'Plein']);

        $this->notifier('cs_test_1', 'Accepte')->assertOk()->assertExactJson(['recue' => true]);
        $this->notifier('cs_test_1', 'Accepte')->assertOk();
        $this->prestataire->etats['cs_test_1'] = EtatDuPaiement::Accepte;
        $this->verifier()->assertJsonPath('data.statut', 'confirmee');

        $this->assertDatabaseHas('paiements', ['reference_externe' => 'cs_test_1', 'statut' => 'accepte']);
        $this->assertSame([], $this->prestataire->remboursees);
        // Une notification d'un paiement inconnu ou encore en cours ne change rien.
        $this->notifier('cs_inconnu', 'Accepte')->assertOk();
        $this->notifier('cs_test_1', 'EnCours')->assertOk();
    }

    public function test_un_paiement_abandonne_ne_confirme_rien_et_les_places_sont_liberees_a_la_fin_du_blocage(): void
    {
        $this->payer(['A1' => 'Plein', 'A2' => 'Plein']);
        $this->verifier()->assertJsonPath('data.statut', 'en_attente');

        $this->notifier('cs_test_1', 'Abandonne')->assertOk();

        $this->assertDatabaseHas('paiements', ['reference_externe' => 'cs_test_1', 'statut' => 'refuse']);
        $this->assertSame('en_attente', $this->reservation->refresh()->statut->value);
        $this->assertFalse($this->estLibre('A1'));

        $this->travel(10)->minutes();
        $this->artisan('reservations:expirer')->assertSuccessful();
        $this->assertSame('expiree', $this->reservation->refresh()->statut->value);
        $this->assertTrue($this->estLibre('A1'));
    }

    public function test_les_places_liberees_ferment_la_page_de_paiement_et_un_paiement_tardif_est_rembourse(): void
    {
        $this->payer(['A1' => 'Plein', 'A2' => 'Plein']);

        $this->travel(10)->minutes();
        $this->artisan('reservations:expirer')->assertSuccessful();

        $this->assertSame(['cs_test_1'], $this->prestataire->fermees);
        $this->assertDatabaseHas('paiements', ['reference_externe' => 'cs_test_1', 'statut' => 'refuse']);

        // Le spectateur a payé juste avant la fermeture: ses places sont parties, il est remboursé.
        $this->notifier('cs_test_1', 'Accepte')->assertOk();
        $this->assertSame(['cs_test_1'], $this->prestataire->remboursees);
        $this->assertDatabaseHas('paiements', ['reference_externe' => 'cs_test_1', 'statut' => 'rembourse']);
        $this->assertSame('expiree', $this->reservation->refresh()->statut->value);

        $this->notifier('cs_test_1', 'Accepte')->assertOk();
        $this->assertSame(['cs_test_1'], $this->prestataire->remboursees);
    }

    public function test_abandonner_la_reservation_ferme_la_page_meme_si_le_prestataire_refuse(): void
    {
        $this->payer(['A1' => 'Plein', 'A2' => 'Plein']);
        $this->prestataire->fermetureImpossible = true;

        $this->actingAs($this->camille, 'api')->deleteJson(route('reservations.destroy', $this->reservation))->assertNoContent();

        $this->assertDatabaseHas('paiements', ['reference_externe' => 'cs_test_1', 'statut' => 'refuse']);
        $this->assertTrue($this->estLibre('A1'));
    }

    public function test_changer_de_tarifs_ferme_la_page_precedente_et_un_paiement_dessus_est_rembourse(): void
    {
        $this->payer(['A1' => 'Plein', 'A2' => 'Plein']);
        $this->payer(['A1' => 'Plein', 'A2' => 'Réduit'])->assertCreated();

        $this->assertSame(['cs_test_1'], $this->prestataire->fermees);
        $this->assertSame([1900, 1650], array_column($this->prestataire->ouvertes, 'montant'));

        $this->notifier('cs_test_1', 'Accepte');
        $this->assertSame(['cs_test_1'], $this->prestataire->remboursees);
        $this->assertSame('en_attente', $this->reservation->refresh()->statut->value);

        $this->notifier('cs_test_2', 'Accepte');
        $this->assertSame('confirmee', $this->reservation->refresh()->statut->value);
        $this->assertSame(1650, $this->reservation->montant_centimes);
    }

    public function test_un_paiement_arrive_juste_apres_le_delai_confirme_si_les_places_ne_sont_pas_encore_liberees(): void
    {
        $this->payer(['A1' => 'Plein', 'A2' => 'Plein']);
        $this->travel(11)->minutes();

        $this->notifier('cs_test_1', 'Accepte');

        $this->assertSame('confirmee', $this->reservation->refresh()->statut->value);
        $this->assertSame([], $this->prestataire->remboursees);
    }

    public function test_verifier_sans_paiement_ouvert_renvoie_la_reservation_inchangee(): void
    {
        $this->verifier()->assertOk()->assertJsonPath('data.statut', 'en_attente')->assertJsonPath('data.paiement', null);
        $this->actingAs(User::factory()->create(), 'api')
            ->postJson(route('reservations.paiement.verification', $this->reservation))
            ->assertNotFound();
    }
}
