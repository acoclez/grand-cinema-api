<?php

namespace Tests\Feature\Guichet;

use App\Enums\RoleUtilisateur;
use App\Enums\StatutSeance;
use App\Models\Place;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Tarif;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Un agent d'accueil vend des places au guichet, sur le même plan de salle que le site.
 */
class VenteAuGuichetTest extends TestCase
{
    use RefreshDatabase;

    private Seance $seance;

    /** @var array<string, Place> Places de la salle par libellé: rangées A et B, numéros 1 à 3. */
    private array $places = [];

    /** @var array<string, Tarif> Tarifs de la séance par libellé: Plein (9,50 €) et Réduit (7,00 €). */
    private array $tarifs = [];

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        // Les tests se déroulent le 10 novembre 2026 à 10 h, heure de Paris.
        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'Europe/Paris'));
        $salle = Salle::factory()->create(['nom' => 'Salle 9', 'capacite' => 6]);
        foreach (['A', 'B'] as $rangee) {
            foreach (range(1, 3) as $numero) {
                $this->places[$rangee.$numero] = Place::factory()->create([
                    'salle_id' => $salle->id,
                    'rangee' => $rangee,
                    'numero' => $numero,
                ]);
            }
        }
        $this->seance = Seance::factory()->create(['salle_id' => $salle->id, 'debut' => Carbon::parse('2026-11-12 20:30')]);
        foreach (['Plein' => 950, 'Réduit' => 700] as $libelle => $prix) {
            $this->tarifs[$libelle] = Tarif::factory()->create(['seance_id' => $this->seance->id, 'libelle' => $libelle, 'prix_centimes' => $prix]);
        }
        $this->agent = User::factory()->create(['role' => RoleUtilisateur::Agent]);
    }

    /**
     * @param  array<string, string>  $billets  Tarif par place, par exemple ['A1' => 'Plein']
     */
    private function vendre(array $billets, ?User $vendeur = null, ?Seance $seance = null): TestResponse
    {
        return $this->actingAs($vendeur ?? $this->agent, 'api')->postJson(route('guichet.ventes.store', $seance ?? $this->seance), [
            'billets' => collect($billets)->map(fn (string $tarif, string $place) => [
                'place_id' => $this->places[$place]->id,
                'tarif_id' => $this->tarifs[$tarif]->id,
            ])->values()->all(),
        ]);
    }

    /**
     * @param  list<string>  $libelles
     */
    private function bloquerEnLigne(array $libelles): TestResponse
    {
        return $this->actingAs(User::factory()->create(), 'api')->postJson(route('reservations.store', $this->seance), [
            'places' => array_map(fn (string $libelle) => $this->places[$libelle]->id, $libelles),
        ]);
    }

    private function estLibre(string $libelle): bool
    {
        $place = collect($this->getJson(route('seances.plan', $this->seance))->json('data.places'))
            ->firstWhere('id', $this->places[$libelle]->id);

        return $place['libre'];
    }

    public function test_le_guichet_lit_le_meme_plan_que_le_site_avec_les_tarifs_de_la_seance(): void
    {
        $this->bloquerEnLigne(['A1'])->assertCreated();

        $this->actingAs($this->agent, 'api')->getJson(route('seances.plan', $this->seance))
            ->assertOk()
            ->assertJsonPath('data.places.0.libre', false)
            ->assertJsonPath('data.tarifs', [
                ['id' => $this->tarifs['Plein']->id, 'libelle' => 'Plein', 'prix_centimes' => 950],
                ['id' => $this->tarifs['Réduit']->id, 'libelle' => 'Réduit', 'prix_centimes' => 700],
            ]);
    }

    public function test_une_vente_encaissee_rend_les_places_aussitot_indisponibles_sur_le_site(): void
    {
        $reponse = $this->vendre(['B2' => 'Plein', 'A1' => 'Réduit']);

        $reponse->assertCreated()
            ->assertJsonPath('data.statut', 'confirmee')
            ->assertJsonPath('data.canal', 'guichet')
            ->assertJsonPath('data.montant_centimes', 1650)
            ->assertJsonPath('data.expire_at', null)
            ->assertJsonPath('data.secondes_restantes', 0)
            ->assertJsonPath('data.places', [
                ['id' => $this->places['A1']->id, 'rangee' => 'A', 'numero' => 1, 'pmr' => false, 'tarif' => 'Réduit', 'prix_centimes' => 700],
                ['id' => $this->places['B2']->id, 'rangee' => 'B', 'numero' => 2, 'pmr' => false, 'tarif' => 'Plein', 'prix_centimes' => 950],
            ]);
        $this->assertMatchesRegularExpression('/^R-[A-HJ-NP-Z2-9]{8}$/', $reponse->json('data.numero'));
        $this->assertDatabaseHas('reservations', [
            'id' => $reponse->json('data.id'),
            'user_id' => null,
            'statut' => 'confirmee',
            'canal' => 'guichet',
            'montant_centimes' => 1650,
        ]);
        $this->assertDatabaseHas('billets', ['place_id' => $this->places['B2']->id, 'tarif_id' => $this->tarifs['Plein']->id, 'prix_centimes' => 950]);
        $this->assertFalse($this->estLibre('A1'));
        $this->assertFalse($this->estLibre('B2'));
        $this->assertTrue($this->estLibre('A2'));

        // Un spectateur ne peut plus les bloquer en ligne.
        $this->bloquerEnLigne(['A1'])->assertConflict()->assertJsonPath('places_prises', [$this->places['A1']->id]);
    }

    public function test_une_place_qui_vient_d_etre_prise_en_ligne_ne_se_vend_pas_au_guichet(): void
    {
        $this->bloquerEnLigne(['A2'])->assertCreated();

        $this->vendre(['A1' => 'Plein', 'A2' => 'Plein'])
            ->assertConflict()
            ->assertJsonPath('message', 'Ces places viennent d\'être prises: A2. Choisissez-en d\'autres.')
            ->assertJsonPath('places_prises', [$this->places['A2']->id]);

        // Rien n'est vendu: A1 reste libre.
        $this->assertTrue($this->estLibre('A1'));
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_une_place_dont_le_blocage_en_ligne_est_depasse_se_vend_au_guichet(): void
    {
        $this->bloquerEnLigne(['A1'])->assertCreated();
        $this->travel(11)->minutes();

        $this->vendre(['A1' => 'Plein'])->assertCreated();

        $this->assertDatabaseHas('reservations', ['canal' => 'en_ligne', 'statut' => 'expiree']);
    }

    public function test_la_vente_compte_dans_le_remplissage_au_titre_du_guichet(): void
    {
        $this->vendre(['A1' => 'Plein', 'A2' => 'Réduit', 'A3' => 'Plein'])->assertCreated();
        $administrateur = User::factory()->create(['role' => RoleUtilisateur::Administrateur]);

        $this->actingAs($administrateur, 'api')->getJson(route('admin.seances.show', $this->seance))
            ->assertOk()
            ->assertJsonPath('data.remplissage', ['en_ligne' => 0, 'guichet' => 3, 'en_attente' => 0, 'taux' => 50]);
    }

    public function test_chaque_billet_a_une_place_de_la_salle_et_un_tarif_de_la_seance(): void
    {
        $this->actingAs($this->agent, 'api')->postJson(route('guichet.ventes.store', $this->seance), ['billets' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['billets' => 'Choisissez au moins une place.']);

        $this->vendre(['A1' => 'Plein'])->assertCreated();
        $this->actingAs($this->agent, 'api')->postJson(route('guichet.ventes.store', $this->seance), [
            'billets' => [
                ['place_id' => $this->places['B1']->id, 'tarif_id' => $this->tarifs['Plein']->id],
                ['place_id' => $this->places['B1']->id, 'tarif_id' => $this->tarifs['Plein']->id],
                ['place_id' => Place::factory()->create()->id, 'tarif_id' => Tarif::factory()->create()->id],
                ['place_id' => $this->places['B3']->id],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'billets.0.place_id' => 'Une même place est choisie deux fois.',
                'billets.2.place_id' => 'Cette place n\'existe pas dans la salle de la séance.',
                'billets.2.tarif_id' => 'Ce tarif n\'existe pas pour cette séance.',
                'billets.3.tarif_id' => 'Choisissez un tarif pour chaque place.',
            ]);
        $this->assertDatabaseCount('billets', 1);
    }

    public function test_seuls_les_agents_d_accueil_et_les_administrateurs_vendent_au_guichet(): void
    {
        $this->postJson(route('guichet.ventes.store', $this->seance))->assertUnauthorized();
        $this->vendre(['A1' => 'Plein'], User::factory()->create())->assertForbidden();

        $administrateur = User::factory()->create(['role' => RoleUtilisateur::Administrateur]);
        $this->vendre(['A1' => 'Plein'], $administrateur)->assertCreated();
        $this->vendre(['A2' => 'Plein'])->assertCreated();
    }

    public function test_une_seance_commencee_ou_non_publiee_ne_se_vend_pas(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 20:31', 'Europe/Paris'));
        $this->vendre(['A1' => 'Plein'])
            ->assertConflict()
            ->assertJsonPath('message', 'Cette séance a déjà commencé: ses places ne se réservent plus.');

        $brouillon = Seance::factory()->create([
            'salle_id' => $this->seance->salle_id,
            'debut' => Carbon::parse('2026-11-20 20:30'),
            'statut' => StatutSeance::Brouillon,
        ]);
        $tarif = Tarif::factory()->create(['seance_id' => $brouillon->id]);
        $this->actingAs($this->agent, 'api')->postJson(route('guichet.ventes.store', $brouillon), [
            'billets' => [['place_id' => $this->places['A1']->id, 'tarif_id' => $tarif->id]],
        ])->assertNotFound();
        $this->assertDatabaseCount('reservations', 0);
    }
}
