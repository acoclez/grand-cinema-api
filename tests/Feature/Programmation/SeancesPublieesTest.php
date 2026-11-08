<?php

namespace Tests\Feature\Programmation;

use App\Models\Billet;
use App\Models\Film;
use App\Models\Reservation;
use App\Models\Salle;
use App\Models\Seance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Un visiteur consulte les séances d'une date et leurs places restantes, sans compte.
 */
class SeancesPublieesTest extends TestCase
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
        $this->salle = Salle::factory()->create(['nom' => 'Salle 3', 'capacite' => 10]);
    }

    private function seance(string $debut, array $attributs = []): Seance
    {
        return Seance::factory()->create([
            'film_id' => $this->film->id,
            'salle_id' => $this->salle->id,
            'debut' => Carbon::parse($debut),
            ...$attributs,
        ]);
    }

    /**
     * Chaque place vendue, ou bloquée par une réservation en cours, a un billet.
     */
    private function vendrePlaces(Seance $seance, int $nombre): void
    {
        $reservation = Reservation::factory()->create(['seance_id' => $seance->id]);
        Billet::factory()->count($nombre)->create(['reservation_id' => $reservation->id]);
    }

    public function test_un_visiteur_voit_les_seances_publiees_d_une_date_avec_l_heure_la_salle_et_la_langue(): void
    {
        $autreFilm = Film::factory()->create(['titre' => 'Aurore']);
        $soir = $this->seance('2026-11-12 20:30', ['langue' => 'vo']);
        $this->seance('2026-11-12 14:00', ['film_id' => $autreFilm->id, 'langue' => 'vf']);
        $this->seance('2026-11-12 17:30')->update(['statut' => 'brouillon']);
        $this->seance('2026-11-13 20:30');

        $this->getJson(route('seances.index', ['date' => '2026-11-12']))
            ->assertOk()
            ->assertJsonPath('data.*.film.titre', ['Aurore', 'Le Souffle du Festival'])
            ->assertJsonPath('data.1', [
                'id' => $soir->id,
                'film' => [
                    'id' => $this->film->id,
                    'titre' => 'Le Souffle du Festival',
                    'duree_minutes' => 112,
                    'genre' => $this->film->genre,
                    'classification' => $this->film->classification->value,
                    'affiche_url' => $this->film->affiche_url,
                ],
                'salle' => ['nom' => 'Salle 3', 'type' => 'standard'],
                'debut' => '2026-11-12T20:30:00+01:00',
                'fin' => '2026-11-12T22:22:00+01:00',
                'langue' => 'vo',
                'places_restantes' => 10,
                'bientot_complet' => false,
                'complet' => false,
            ]);
    }

    public function test_sans_date_la_liste_montre_les_seances_d_aujourd_hui_qui_n_ont_pas_commence(): void
    {
        $this->seance('2026-11-10 09:00');
        $apresMidi = $this->seance('2026-11-10 14:00');
        $this->seance('2026-11-11 14:00');

        $this->getJson(route('seances.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$apresMidi->id]);
    }

    public function test_chaque_seance_donne_ses_places_restantes_et_signale_bientot_complet_a_90_pourcent(): void
    {
        $this->vendrePlaces($this->seance('2026-11-12 14:00'), 8);
        $this->vendrePlaces($this->seance('2026-11-12 17:30'), 9);

        $this->getJson(route('seances.index', ['date' => '2026-11-12']))
            ->assertOk()
            ->assertJsonPath('data.*.places_restantes', [2, 1])
            ->assertJsonPath('data.*.bientot_complet', [false, true])
            ->assertJsonPath('data.*.complet', [false, false]);
    }

    public function test_une_seance_sans_place_libre_est_complete(): void
    {
        $this->vendrePlaces($this->seance('2026-11-12 20:30'), 10);

        $this->getJson(route('seances.index', ['date' => '2026-11-12']))
            ->assertOk()
            ->assertJsonPath('data.0.places_restantes', 0)
            ->assertJsonPath('data.0.bientot_complet', false)
            ->assertJsonPath('data.0.complet', true);
    }

    public function test_les_seances_d_un_film_archive_n_apparaissent_plus(): void
    {
        $this->seance('2026-11-12 20:30');
        $this->film->archiver();

        $this->getJson(route('seances.index', ['date' => '2026-11-12']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_une_date_passee_ou_mal_ecrite_est_refusee(): void
    {
        $this->getJson(route('seances.index', ['date' => '2026-11-09']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => 'Les séances passées ne sont plus proposées: choisissez aujourd’hui ou une date à venir.']);

        $this->getJson(route('seances.index', ['date' => '12/11/2026']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => 'Indiquez une date au format AAAA-MM-JJ.']);
    }
}
