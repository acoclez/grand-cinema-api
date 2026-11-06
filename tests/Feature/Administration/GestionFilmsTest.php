<?php

namespace Tests\Feature\Administration;

use App\Enums\ClassificationFilm;
use App\Models\Film;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'administrateur crée, modifie et archive les films.
 */
class GestionFilmsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function donneesFilm(array $changements = []): array
    {
        return [
            'titre' => 'Le Souffle du Festival',
            'synopsis' => 'Trois musiciens traversent la ville pendant la semaine du festival.',
            'duree_minutes' => 112,
            'genre' => 'Comédie dramatique',
            'classification' => 'Tous publics',
            'langue_originale' => 'Français',
            'affiche_url' => 'https://images.exemple.fr/souffle.jpg',
            'bande_annonce_url' => null,
            ...$changements,
        ];
    }

    private function connecterAdministrateur(): void
    {
        $this->actingAs(User::factory()->administrateur()->create(), 'api');
    }

    public function test_l_administrateur_cree_un_film_qui_peut_etre_programme(): void
    {
        $this->connecterAdministrateur();

        $this->postJson(route('admin.films.store'), $this->donneesFilm())
            ->assertCreated()
            ->assertJsonPath('data.titre', 'Le Souffle du Festival')
            ->assertJsonPath('data.classification', 'Tous publics')
            ->assertJsonPath('data.langue_originale', 'Français')
            ->assertJsonPath('data.archive', false);

        $film = Film::sole();
        $this->assertSame(ClassificationFilm::TousPublics, $film->classification);
        $this->assertTrue(Film::aLAffiche()->whereKey($film->id)->exists());
    }

    public function test_un_film_incomplet_n_est_pas_enregistre_et_chaque_champ_manquant_est_signale(): void
    {
        $this->connecterAdministrateur();

        $this->postJson(route('admin.films.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'titre' => 'Le champ titre est obligatoire.',
                'synopsis' => 'Le champ synopsis est obligatoire.',
                'duree_minutes' => 'Le champ durée est obligatoire.',
                'genre' => 'Le champ genre est obligatoire.',
                'classification' => 'Le champ classification est obligatoire.',
                'langue_originale' => 'Le champ langue originale est obligatoire.',
            ])
            ->assertJsonMissingValidationErrors(['affiche_url', 'bande_annonce_url']);

        $this->assertDatabaseCount('films', 0);
    }

    public function test_les_valeurs_invalides_sont_signalees(): void
    {
        $this->connecterAdministrateur();

        $this->postJson(route('admin.films.store'), $this->donneesFilm([
            'duree_minutes' => 0,
            'classification' => 'PG-13',
            'affiche_url' => 'http://images.exemple.fr/souffle.jpg',
            'bande_annonce_url' => 'pas une adresse',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'duree_minutes' => 'Le champ durée doit être compris entre 1 et 600.',
                'classification' => 'La valeur du champ classification n\'est pas valide.',
                'affiche_url' => 'Le champ affiche doit être une adresse web valide, commençant par https://.',
                'bande_annonce_url' => 'Le champ bande-annonce doit être une adresse web valide, commençant par https://.',
            ]);

        $this->assertDatabaseCount('films', 0);
    }

    public function test_l_administrateur_consulte_et_modifie_un_film(): void
    {
        $this->connecterAdministrateur();
        $film = Film::factory()->create(['titre' => 'Titre provisoire']);

        $this->getJson(route('admin.films.show', $film))
            ->assertOk()
            ->assertJsonPath('data.titre', 'Titre provisoire');

        $this->putJson(route('admin.films.update', $film), $this->donneesFilm(['classification' => '-12']))
            ->assertOk()
            ->assertJsonPath('data.titre', 'Le Souffle du Festival')
            ->assertJsonPath('data.classification', '-12');

        $film->refresh();
        $this->assertSame('Le Souffle du Festival', $film->titre);
        $this->assertSame(ClassificationFilm::Moins12, $film->classification);
    }

    public function test_un_film_archive_disparait_du_site_public_mais_reste_en_base(): void
    {
        $this->connecterAdministrateur();
        $film = Film::factory()->create();

        $this->postJson(route('admin.films.archiver', $film))
            ->assertOk()
            ->assertJsonPath('data.archive', true);

        $film->refresh();
        $this->assertNotNull($film->archive_at);
        $this->assertFalse(Film::aLAffiche()->whereKey($film->id)->exists());
        $this->assertDatabaseHas('films', ['id' => $film->id]);

        // Archiver une seconde fois ne change pas la date d'archivage.
        $date = $film->archive_at;
        $this->travel(1)->day();
        $this->postJson(route('admin.films.archiver', $film))->assertOk();
        $this->assertTrue($film->refresh()->archive_at?->equalTo($date));
    }

    public function test_un_film_archive_peut_revenir_a_l_affiche(): void
    {
        $this->connecterAdministrateur();
        $film = Film::factory()->archive()->create();

        $this->postJson(route('admin.films.restaurer', $film))
            ->assertOk()
            ->assertJsonPath('data.archive', false)
            ->assertJsonPath('data.archive_le', null);

        $this->assertTrue(Film::aLAffiche()->whereKey($film->id)->exists());
    }

    public function test_la_liste_montre_les_films_a_l_affiche_et_les_archives_sur_demande(): void
    {
        $this->connecterAdministrateur();
        Film::factory()->create(['titre' => 'Brume']);
        Film::factory()->create(['titre' => 'Aurore']);
        Film::factory()->archive()->create(['titre' => 'Crépuscule']);

        $this->getJson(route('admin.films.index'))
            ->assertOk()
            ->assertJsonPath('data.*.titre', ['Aurore', 'Brume'])
            ->assertJsonPath('meta.total', 2);

        $this->getJson(route('admin.films.index', ['archives' => 1]))
            ->assertOk()
            ->assertJsonPath('data.*.titre', ['Aurore', 'Brume', 'Crépuscule'])
            ->assertJsonPath('data.2.archive', true);
    }

    public function test_la_liste_est_paginee_par_20_films(): void
    {
        $this->connecterAdministrateur();
        Film::factory()->count(25)->create();

        $this->getJson(route('admin.films.index'))
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson(route('admin.films.index', ['page' => 2]))
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_un_film_inconnu_renvoie_une_erreur_404_sans_detail_technique(): void
    {
        $this->connecterAdministrateur();

        $this->getJson(route('admin.films.show', 999))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Ressource introuvable.']);
    }

    public function test_un_visiteur_sans_jeton_est_refuse(): void
    {
        $this->getJson(route('admin.films.index'))->assertUnauthorized();
        $this->postJson(route('admin.films.store'), $this->donneesFilm())->assertUnauthorized();

        $this->assertDatabaseCount('films', 0);
    }

    public function test_un_spectateur_ou_un_agent_n_a_pas_acces_a_la_gestion_des_films(): void
    {
        $film = Film::factory()->create();

        foreach ([User::factory()->create(), User::factory()->agent()->create()] as $utilisateur) {
            $this->actingAs($utilisateur, 'api');

            $this->getJson(route('admin.films.index'))
                ->assertForbidden()
                ->assertExactJson(['message' => 'Accès refusé: votre compte n\'a pas le rôle requis pour cette action.']);
            $this->postJson(route('admin.films.store'), $this->donneesFilm())->assertForbidden();
            $this->putJson(route('admin.films.update', $film), $this->donneesFilm())->assertForbidden();
            $this->postJson(route('admin.films.archiver', $film))->assertForbidden();
        }

        $this->assertNull($film->refresh()->archive_at);
        $this->assertDatabaseCount('films', 1);
    }
}
