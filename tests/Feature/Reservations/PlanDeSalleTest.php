<?php

namespace Tests\Feature\Reservations;

use App\Models\Billet;
use App\Models\Film;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Salle;
use App\Models\Seance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Un visiteur consulte le plan de salle d'une séance, sans compte.
 */
class PlanDeSalleTest extends TestCase
{
    use RefreshDatabase;

    private Seance $seance;

    /** @var array<string, Place> Places de la salle par libellé: A1 (PMR), A2, B1, B2. */
    private array $places = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Les tests se déroulent le 10 novembre 2026 à 10 h, heure de Paris.
        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'Europe/Paris'));
        $salle = Salle::factory()->create(['nom' => 'Salle 9', 'capacite' => 4]);
        // Créées dans le désordre: le plan les trie par rangée puis par numéro.
        foreach ([['B', 2], ['A', 2], ['B', 1], ['A', 1]] as [$rangee, $numero]) {
            $this->places[$rangee.$numero] = Place::factory()->create([
                'salle_id' => $salle->id,
                'rangee' => $rangee,
                'numero' => $numero,
                'pmr' => $rangee.$numero === 'A1',
            ]);
        }
        $this->seance = Seance::factory()->create([
            'film_id' => Film::factory()->create(['titre' => 'Le Souffle du Festival'])->id,
            'salle_id' => $salle->id,
            'debut' => Carbon::parse('2026-11-12 20:30'),
        ]);
    }

    /**
     * @param  Reservation  $reservation  Réservation de la séance
     */
    private function occuper(Reservation $reservation, string $place): void
    {
        Billet::factory()->create(['reservation_id' => $reservation->id, 'place_id' => $this->places[$place]->id]);
    }

    public function test_le_plan_distingue_les_places_libres_occupees_et_reservees_aux_personnes_a_mobilite_reduite(): void
    {
        $this->occuper(Reservation::factory()->confirmee()->create(['seance_id' => $this->seance->id]), 'A2');
        $this->occuper(Reservation::factory()->create(['seance_id' => $this->seance->id]), 'B1');

        $this->getJson(route('seances.plan', $this->seance))
            ->assertOk()
            ->assertJsonPath('data.seance.film.titre', 'Le Souffle du Festival')
            ->assertJsonPath('data.seance.salle.nom', 'Salle 9')
            ->assertJsonPath('data.seance.debut', '2026-11-12T20:30:00+01:00')
            ->assertJsonPath('data.reservable', true)
            ->assertJsonPath('data.places_max', 10)
            ->assertJsonPath('data.blocage_minutes', 10)
            ->assertJsonPath('data.places_libres', 2)
            ->assertJsonPath('data.places', [
                ['id' => $this->places['A1']->id, 'rangee' => 'A', 'numero' => 1, 'pmr' => true, 'libre' => true],
                ['id' => $this->places['A2']->id, 'rangee' => 'A', 'numero' => 2, 'pmr' => false, 'libre' => false],
                ['id' => $this->places['B1']->id, 'rangee' => 'B', 'numero' => 1, 'pmr' => false, 'libre' => false],
                ['id' => $this->places['B2']->id, 'rangee' => 'B', 'numero' => 2, 'pmr' => false, 'libre' => true],
            ]);
    }

    public function test_une_place_dont_le_blocage_est_depasse_redevient_libre_sans_attendre_la_tache_planifiee(): void
    {
        $this->occuper(Reservation::factory()->create([
            'seance_id' => $this->seance->id,
            'expire_at' => now()->subSecond(),
        ]), 'B2');

        $this->getJson(route('seances.plan', $this->seance))
            ->assertOk()
            ->assertJsonPath('data.places_libres', 4)
            ->assertJsonPath('data.places.3.libre', true);
    }

    public function test_une_seance_en_brouillon_ou_d_un_film_archive_n_a_pas_de_plan_public(): void
    {
        $this->seance->update(['statut' => 'brouillon']);
        $this->getJson(route('seances.plan', $this->seance))->assertNotFound();

        $this->seance->update(['statut' => 'publiee']);
        $this->seance->film->archiver();
        $this->getJson(route('seances.plan', $this->seance))->assertNotFound();
    }

    public function test_le_plan_d_une_seance_commencee_reste_consultable_sans_reservation_possible(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 20:31', 'Europe/Paris'));

        $this->getJson(route('seances.plan', $this->seance))
            ->assertOk()
            ->assertJsonPath('data.reservable', false);
    }
}
