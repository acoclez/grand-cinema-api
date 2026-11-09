<?php

namespace Tests\Feature\Administration;

use App\Models\Billet;
use App\Models\Reservation;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * L'administrateur suit le remplissage de chaque séance, en ligne et au guichet.
 */
class RemplissageSeancesTest extends TestCase
{
    use RefreshDatabase;

    private Seance $seance;

    protected function setUp(): void
    {
        parent::setUp();

        // Les tests se déroulent le 10 novembre 2026 à 10 h, heure de Paris.
        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'Europe/Paris'));
        $this->seance = Seance::factory()->create([
            'salle_id' => Salle::factory()->create(['capacite' => 10])->id,
            'debut' => Carbon::parse('2026-11-12 20:30'),
        ]);
        $this->actingAs(User::factory()->administrateur()->create(), 'api');
    }

    /**
     * Une réservation de la séance avec un billet par place.
     *
     * @param  Factory<Reservation>  $reservation
     */
    private function vendre(Factory $reservation, int $places): void
    {
        $vente = $reservation->create(['seance_id' => $this->seance->id]);
        Billet::factory()->count($places)->create(['reservation_id' => $vente->id]);
    }

    public function test_chaque_seance_affiche_ses_places_vendues_par_canal_et_son_taux_de_remplissage(): void
    {
        $this->vendre(Reservation::factory()->confirmee(), 3);
        $this->vendre(Reservation::factory()->guichet(), 2);
        $this->vendre(Reservation::factory(), 1);

        $this->getJson(route('admin.seances.index', ['date' => '2026-11-12']))
            ->assertOk()
            ->assertJsonPath('data.0.salle.capacite', 10)
            ->assertJsonPath('data.0.places_vendues', 6)
            ->assertJsonPath('data.0.remplissage', [
                'en_ligne' => 3,
                'guichet' => 2,
                'en_attente' => 1,
                'taux' => 50,
            ]);
    }

    public function test_le_taux_est_arrondi_au_pourcentage_le_plus_proche(): void
    {
        $this->seance->salle->update(['capacite' => 3]);
        $this->vendre(Reservation::factory()->confirmee(), 2);

        $this->getJson(route('admin.seances.show', $this->seance))
            ->assertOk()
            ->assertJsonPath('data.remplissage.taux', 67);
    }

    public function test_les_chiffres_sont_a_jour_apres_chaque_vente(): void
    {
        $this->getJson(route('admin.seances.index', ['date' => '2026-11-12']))
            ->assertJsonPath('data.0.remplissage.taux', 0);

        $this->vendre(Reservation::factory()->guichet(), 4);

        $this->getJson(route('admin.seances.index', ['date' => '2026-11-12']))
            ->assertJsonPath('data.0.remplissage.guichet', 4)
            ->assertJsonPath('data.0.remplissage.taux', 40);
    }

    public function test_une_reservation_annulee_ou_expiree_ne_compte_pas(): void
    {
        $this->vendre(Reservation::factory()->state(['statut' => 'annulee']), 2);
        $this->vendre(Reservation::factory()->state(['statut' => 'expiree']), 1);

        $this->getJson(route('admin.seances.show', $this->seance))
            ->assertOk()
            ->assertJsonPath('data.remplissage', [
                'en_ligne' => 0,
                'guichet' => 0,
                'en_attente' => 0,
                'taux' => 0,
            ]);
    }
}
