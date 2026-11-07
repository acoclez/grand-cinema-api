<?php

namespace Tests\Feature\Administration;

use App\Enums\StatutSeance;
use App\Models\Billet;
use App\Models\Film;
use App\Models\Reservation;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\SalleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * L'administrateur consulte les salles, programme les séances et les publie.
 */
class GestionSeancesTest extends TestCase
{
    use RefreshDatabase;

    private Film $film;

    private Salle $salle;

    protected function setUp(): void
    {
        parent::setUp();

        // Les tests se déroulent le 10 novembre 2026 à 10 h, heure de Paris.
        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'Europe/Paris'));
        $this->film = Film::factory()->create(['titre' => 'Le Souffle du Festival', 'duree_minutes' => 112]);
        $this->salle = Salle::factory()->create(['nom' => 'Salle 3']);
        $this->actingAs(User::factory()->administrateur()->create(), 'api');
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesSeance(array $changements = []): array
    {
        return [
            'film_id' => $this->film->id,
            'salle_id' => $this->salle->id,
            'debut' => '2026-11-20T20:30',
            'langue' => 'vo',
            'tarifs' => [
                ['libelle' => 'Plein', 'prix_centimes' => 950],
                ['libelle' => 'Réduit', 'prix_centimes' => 700],
            ],
            ...$changements,
        ];
    }

    private function seance(array $attributs = []): Seance
    {
        $seance = Seance::factory()->create([
            'film_id' => $this->film->id,
            'salle_id' => $this->salle->id,
            'debut' => Carbon::parse('2026-11-20 20:30'),
            ...$attributs,
        ]);
        $seance->tarifs()->create(['libelle' => 'Plein', 'prix_centimes' => 950]);

        return $seance;
    }

    /**
     * Une place vendue, ou bloquée par une réservation en cours, a un billet.
     */
    private function vendreUnePlace(Seance $seance): void
    {
        $reservation = Reservation::factory()->create(['seance_id' => $seance->id]);
        Billet::factory()->create(['reservation_id' => $reservation->id]);
    }

    public function test_les_10_salles_sont_configurees_avec_1700_places(): void
    {
        $this->salle->delete();
        $this->seed(SalleSeeder::class);

        $this->getJson(route('admin.salles.index'))
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total_places', 1700)
            ->assertJsonPath('data.0.nom', 'Salle 1')
            ->assertJsonPath('data.0.type', 'premium_imax')
            ->assertJsonPath('data.0.capacite', 300)
            ->assertJsonPath('data.0.places', 300)
            ->assertJsonPath('data.0.places_pmr', 4);
    }

    public function test_une_seance_creee_est_enregistree_en_brouillon_avec_ses_tarifs(): void
    {
        $this->postJson(route('admin.seances.store'), $this->donneesSeance([
            'tarifs' => [
                ['libelle' => 'Plein', 'prix_centimes' => 950],
                ['libelle' => 'Réduit', 'prix_centimes' => 700],
                ['libelle' => 'Enfant', 'prix_centimes' => 550],
            ],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.statut', 'brouillon')
            ->assertJsonPath('data.film.titre', 'Le Souffle du Festival')
            ->assertJsonPath('data.salle.nom', 'Salle 3')
            ->assertJsonPath('data.debut', '2026-11-20T20:30:00+01:00')
            ->assertJsonPath('data.fin', '2026-11-20T22:22:00+01:00')
            ->assertJsonPath('data.langue', 'vo')
            // Les tarifs gardent l'ordre de saisie.
            ->assertJsonPath('data.tarifs.*.libelle', ['Plein', 'Réduit', 'Enfant'])
            ->assertJsonPath('data.places_vendues', 0);

        $seance = Seance::sole();
        $this->assertSame(StatutSeance::Brouillon, $seance->statut);
        $this->assertCount(3, $seance->tarifs);
        $this->assertFalse(Seance::publiee()->exists());
    }

    public function test_une_seance_incomplete_n_est_pas_enregistree_et_chaque_champ_est_signale(): void
    {
        $this->postJson(route('admin.seances.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'film_id' => 'Le champ film est obligatoire.',
                'salle_id' => 'Le champ salle est obligatoire.',
                'debut' => 'Le champ début est obligatoire.',
                'langue' => 'Le champ langue est obligatoire.',
                'tarifs' => 'Ajoutez au moins un tarif.',
            ]);

        $this->assertDatabaseCount('seances', 0);
    }

    public function test_les_valeurs_invalides_sont_signalees(): void
    {
        $archive = Film::factory()->archive()->create();

        $this->postJson(route('admin.seances.store'), $this->donneesSeance([
            'film_id' => $archive->id,
            'debut' => '2026-11-09T20:30',
            'langue' => 'vostfr',
            'tarifs' => [
                ['libelle' => 'Plein', 'prix_centimes' => 950],
                ['libelle' => 'plein', 'prix_centimes' => -1],
            ],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'film_id' => 'Ce film n\'existe pas ou n\'est plus à l\'affiche.',
                'debut' => 'La séance doit commencer dans le futur.',
                'langue' => 'La valeur du champ langue n\'est pas valide.',
                'tarifs.1.libelle' => 'Deux tarifs ne peuvent pas avoir le même libellé.',
                'tarifs.1.prix_centimes' => 'Le champ prix du tarif doit être compris entre 0 et 10000.',
            ]);

        $this->assertDatabaseCount('seances', 0);
    }

    public function test_une_seance_publiee_apparait_sur_le_site_et_peut_etre_depubliee(): void
    {
        $seance = $this->seance(['statut' => StatutSeance::Brouillon]);

        $this->postJson(route('admin.seances.publier', $seance))
            ->assertOk()
            ->assertJsonPath('data.statut', 'publiee');
        $this->assertTrue(Seance::publiee()->whereKey($seance->id)->exists());

        $this->postJson(route('admin.seances.depublier', $seance))
            ->assertOk()
            ->assertJsonPath('data.statut', 'brouillon');
        $this->assertFalse(Seance::publiee()->whereKey($seance->id)->exists());
    }

    public function test_une_seance_dont_des_places_sont_vendues_ne_peut_pas_etre_depubliee(): void
    {
        $seance = $this->seance();
        $this->vendreUnePlace($seance);

        $this->postJson(route('admin.seances.depublier', $seance))
            ->assertConflict()
            ->assertExactJson(['message' => 'Des places sont déjà vendues pour cette séance : elle ne peut pas être dépubliée.']);

        $this->assertSame(StatutSeance::Publiee, $seance->refresh()->statut);
    }

    public function test_une_seance_dont_des_places_sont_vendues_ne_peut_plus_etre_modifiee(): void
    {
        $seance = $this->seance();
        $this->vendreUnePlace($seance);

        $this->putJson(route('admin.seances.update', $seance), $this->donneesSeance(['debut' => '2026-11-21T14:00']))
            ->assertConflict()
            ->assertJsonPath('message', 'Des places sont déjà vendues pour cette séance : elle ne peut plus être modifiée.');

        $this->assertTrue($seance->refresh()->debut->equalTo(Carbon::parse('2026-11-20 20:30')));
    }

    public function test_la_modification_remplace_les_tarifs(): void
    {
        $seance = $this->seance(['statut' => StatutSeance::Brouillon]);

        $this->putJson(route('admin.seances.update', $seance), $this->donneesSeance([
            'debut' => '2026-11-21T14:00',
            'tarifs' => [['libelle' => 'Unique', 'prix_centimes' => 800]],
        ]))
            ->assertOk()
            ->assertJsonPath('data.debut', '2026-11-21T14:00:00+01:00')
            ->assertJsonPath('data.tarifs.*.libelle', ['Unique']);

        $this->assertSame(['Unique'], $seance->tarifs()->pluck('libelle')->all());
    }

    public function test_une_seance_d_un_film_archive_ne_peut_pas_etre_publiee(): void
    {
        $seance = $this->seance(['statut' => StatutSeance::Brouillon]);
        $this->film->archiver();

        $this->postJson(route('admin.seances.publier', $seance))
            ->assertConflict()
            ->assertJsonPath('message', 'Le film de cette séance est archivé : remettez-le à l\'affiche avant de publier la séance.');

        $this->assertSame(StatutSeance::Brouillon, $seance->refresh()->statut);
    }

    public function test_une_seance_deja_commencee_ne_peut_pas_etre_publiee(): void
    {
        $seance = $this->seance(['statut' => StatutSeance::Brouillon, 'debut' => Carbon::parse('2026-11-10 09:00')]);

        $this->postJson(route('admin.seances.publier', $seance))
            ->assertConflict()
            ->assertJsonPath('message', 'Cette séance a déjà commencé : elle ne peut plus être publiée.');
    }

    public function test_seul_un_brouillon_peut_etre_supprime(): void
    {
        $brouillon = $this->seance(['statut' => StatutSeance::Brouillon]);
        $publiee = $this->seance();

        $this->deleteJson(route('admin.seances.destroy', $brouillon))->assertNoContent();
        $this->assertModelMissing($brouillon);
        $this->assertDatabaseMissing('tarifs', ['seance_id' => $brouillon->id]);

        $this->deleteJson(route('admin.seances.destroy', $publiee))
            ->assertConflict()
            ->assertJsonPath('message', 'Seule une séance en brouillon peut être supprimée : dépubliez-la d\'abord.');
        $this->assertModelExists($publiee);
    }

    public function test_la_liste_montre_les_seances_d_un_jour_par_heure_avec_les_places_vendues(): void
    {
        $soir = $this->seance();
        $apresMidi = $this->seance(['debut' => Carbon::parse('2026-11-20 14:00')]);
        $autreSalle = $this->seance(['salle_id' => Salle::factory()->create()->id, 'debut' => Carbon::parse('2026-11-20 17:30')]);
        $this->seance(['debut' => Carbon::parse('2026-11-21 14:00')]);
        $this->vendreUnePlace($soir);

        $this->getJson(route('admin.seances.index', ['date' => '2026-11-20']))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$apresMidi->id, $autreSalle->id, $soir->id])
            ->assertJsonPath('data.2.places_vendues', 1);

        $this->getJson(route('admin.seances.index', ['date' => '2026-11-20', 'salle_id' => $this->salle->id]))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$apresMidi->id, $soir->id]);
    }

    public function test_la_liste_montre_aujourd_hui_par_defaut(): void
    {
        $aujourdhui = $this->seance(['debut' => Carbon::parse('2026-11-10 20:30')]);
        $this->seance();

        $this->getJson(route('admin.seances.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$aujourdhui->id]);
    }

    public function test_une_seance_inconnue_renvoie_une_erreur_404(): void
    {
        $this->getJson(route('admin.seances.show', 999))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Ressource introuvable.']);
    }

    public function test_tous_les_films_a_l_affiche_tiennent_dans_une_liste_de_choix(): void
    {
        Film::factory()->count(30)->create();

        $this->getJson(route('admin.films.index', ['par_page' => 100]))
            ->assertOk()
            ->assertJsonCount(31, 'data');

        $this->getJson(route('admin.films.index', ['par_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['par_page' => 'Le champ nombre par page doit être compris entre 1 et 100.']);
    }

    public function test_un_spectateur_ou_un_agent_n_a_pas_acces_aux_seances_ni_aux_salles(): void
    {
        $seance = $this->seance(['statut' => StatutSeance::Brouillon]);

        foreach ([User::factory()->create(), User::factory()->agent()->create()] as $utilisateur) {
            $this->actingAs($utilisateur, 'api');

            $this->getJson(route('admin.salles.index'))->assertForbidden();
            $this->getJson(route('admin.seances.index'))->assertForbidden();
            $this->postJson(route('admin.seances.store'), $this->donneesSeance())->assertForbidden();
            $this->postJson(route('admin.seances.publier', $seance))->assertForbidden();
            $this->deleteJson(route('admin.seances.destroy', $seance))->assertForbidden();
        }

        $this->assertSame(StatutSeance::Brouillon, $seance->refresh()->statut);
        $this->assertDatabaseCount('seances', 1);
    }

    public function test_un_visiteur_sans_jeton_est_refuse(): void
    {
        $this->app->make('auth')->forgetGuards();

        $this->getJson(route('admin.seances.index'))->assertUnauthorized();
        $this->getJson(route('admin.salles.index'))->assertUnauthorized();
    }
}
