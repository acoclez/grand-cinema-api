<?php

namespace Tests\Feature\Programmation;

use App\Models\Film;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un visiteur consulte les films à l'affiche et leur fiche, sans compte.
 */
class FilmsAlAfficheTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_visiteur_voit_les_films_a_l_affiche_avec_leur_affiche_et_leur_titre(): void
    {
        Film::factory()->create(['titre' => 'Brume', 'affiche_url' => 'https://images.exemple.fr/brume.jpg']);
        Film::factory()->create(['titre' => 'Aurore']);

        $this->getJson(route('films.index'))
            ->assertOk()
            ->assertJsonPath('data.*.titre', ['Aurore', 'Brume'])
            ->assertJsonPath('data.1.affiche_url', 'https://images.exemple.fr/brume.jpg');
    }

    public function test_la_fiche_affiche_synopsis_duree_genre_classification_et_langue(): void
    {
        $film = Film::factory()->create([
            'titre' => 'Le Souffle du Festival',
            'synopsis' => 'Trois musiciens traversent la ville pendant la semaine du festival.',
            'duree_minutes' => 112,
            'genre' => 'Comédie dramatique',
            'classification' => '-12',
            'langue_originale' => 'Français',
            'bande_annonce_url' => 'https://videos.exemple.fr/souffle',
        ]);

        $this->getJson(route('films.show', $film))
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $film->id,
                'titre' => 'Le Souffle du Festival',
                'synopsis' => 'Trois musiciens traversent la ville pendant la semaine du festival.',
                'duree_minutes' => 112,
                'genre' => 'Comédie dramatique',
                'classification' => '-12',
                'langue_originale' => 'Français',
                'affiche_url' => null,
                'bande_annonce_url' => 'https://videos.exemple.fr/souffle',
            ]]);
    }

    public function test_un_film_archive_n_apparait_plus_et_sa_fiche_n_est_plus_accessible(): void
    {
        Film::factory()->create(['titre' => 'Aurore']);
        $archive = Film::factory()->archive()->create(['titre' => 'Crépuscule']);

        $this->getJson(route('films.index'))
            ->assertOk()
            ->assertJsonPath('data.*.titre', ['Aurore']);

        $this->getJson(route('films.show', $archive))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Ressource introuvable.']);
    }

    public function test_un_film_inconnu_renvoie_une_erreur_404(): void
    {
        $this->getJson(route('films.show', 999))->assertNotFound();
        $this->getJson('/api/films/abc')->assertNotFound();
    }
}
