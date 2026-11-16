<?php

namespace Tests\Feature\Reservations;

use App\Models\Billet;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Un spectateur connecté choisit ses places: elles sont bloquées pour lui pendant 10 minutes.
 */
class BlocageDesPlacesTest extends TestCase
{
    use RefreshDatabase;

    private Seance $seance;

    /** @var array<string, Place> Places de la salle par libellé: rangées A et B, numéros 1 à 6. */
    private array $places = [];

    private User $camille;

    protected function setUp(): void
    {
        parent::setUp();

        // Les tests se déroulent le 10 novembre 2026 à 10 h, heure de Paris.
        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'Europe/Paris'));
        $salle = Salle::factory()->create(['nom' => 'Salle 9', 'capacite' => 12]);
        foreach (['A', 'B'] as $rangee) {
            foreach (range(1, 6) as $numero) {
                $this->places[$rangee.$numero] = Place::factory()->create([
                    'salle_id' => $salle->id,
                    'rangee' => $rangee,
                    'numero' => $numero,
                ]);
            }
        }
        $this->seance = Seance::factory()->create(['salle_id' => $salle->id, 'debut' => Carbon::parse('2026-11-12 20:30')]);
        $this->camille = User::factory()->create(['prenom' => 'Camille']);
    }

    /**
     * @param  list<string>  $libelles  Places choisies, par exemple ['A1', 'A2']
     */
    private function bloquer(User $spectateur, array $libelles): TestResponse
    {
        return $this->actingAs($spectateur, 'api')->postJson(route('reservations.store', $this->seance), [
            'places' => array_map(fn (string $libelle) => $this->places[$libelle]->id, $libelles),
        ]);
    }

    private function estLibre(string $libelle): bool
    {
        $place = collect($this->getJson(route('seances.plan', $this->seance))->json('data.places'))
            ->firstWhere('id', $this->places[$libelle]->id);

        return $place['libre'];
    }

    public function test_les_places_choisies_sont_bloquees_10_minutes_et_apparaissent_occupees_pour_les_autres(): void
    {
        $reponse = $this->bloquer($this->camille, ['B2', 'A1']);

        $reponse->assertCreated()
            ->assertJsonPath('data.statut', 'en_attente')
            ->assertJsonPath('data.expire_at', '2026-11-10T10:10:00+01:00')
            ->assertJsonPath('data.secondes_restantes', 600)
            ->assertJsonPath('data.seance.salle.nom', 'Salle 9')
            ->assertJsonPath('data.places.0.rangee', 'A')
            ->assertJsonPath('data.places.1.rangee', 'B');
        $this->assertMatchesRegularExpression('/^R-[A-HJ-NP-Z2-9]{8}$/', $reponse->json('data.numero'));
        $this->assertDatabaseHas('reservations', [
            'user_id' => $this->camille->id,
            'seance_id' => $this->seance->id,
            'statut' => 'en_attente',
            'canal' => 'en_ligne',
        ]);
        $this->assertFalse($this->estLibre('A1'));
        $this->assertFalse($this->estLibre('B2'));
        $this->assertTrue($this->estLibre('A2'));
    }

    public function test_un_spectateur_choisit_de_1_a_10_places_distinctes_de_la_salle(): void
    {
        $this->bloquer($this->camille, ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'B1', 'B2', 'B3', 'B4', 'B5'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['places' => 'Vous pouvez choisir 10 places au maximum.']);

        $this->actingAs($this->camille, 'api')->postJson(route('reservations.store', $this->seance), ['places' => []])
            ->assertJsonValidationErrors(['places' => 'Choisissez au moins une place.']);

        $this->bloquer($this->camille, ['A1', 'A1'])
            ->assertJsonValidationErrors(['places.0' => 'Une même place est choisie deux fois.']);

        $autreSalle = Place::factory()->create();
        $this->actingAs($this->camille, 'api')->postJson(route('reservations.store', $this->seance), ['places' => [$autreSalle->id]])
            ->assertJsonValidationErrors(['places.0' => 'Cette place n\'existe pas dans la salle de la séance.']);

        $this->bloquer($this->camille, ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'B1', 'B2', 'B3', 'B4'])->assertCreated();
        $this->assertDatabaseCount('billets', 10);
    }

    public function test_sans_paiement_dans_les_10_minutes_les_places_sont_liberees_automatiquement(): void
    {
        $id = $this->bloquer($this->camille, ['A1', 'A2'])->json('data.id');

        $this->travel(9)->minutes();
        $this->assertFalse($this->estLibre('A1'));

        $this->travel(1)->minutes();
        $this->assertTrue($this->estLibre('A1'));

        $this->artisan('reservations:expirer')->expectsOutput('1 réservation(s) expirée(s).')->assertSuccessful();
        $this->assertDatabaseHas('reservations', ['id' => $id, 'statut' => 'expiree', 'expire_at' => null]);
        $this->assertDatabaseCount('billets', 0);
    }

    public function test_une_place_liberee_par_expiration_se_reserve_sans_attendre_la_tache_planifiee(): void
    {
        $premiere = $this->bloquer($this->camille, ['A1'])->json('data.id');
        $this->travel(11)->minutes();

        $this->bloquer(User::factory()->create(), ['A1'])->assertCreated();

        $this->assertDatabaseHas('reservations', ['id' => $premiere, 'statut' => 'expiree']);
    }

    public function test_si_deux_spectateurs_choisissent_la_meme_place_un_seul_l_obtient(): void
    {
        $dominique = User::factory()->create();
        $this->bloquer($this->camille, ['A1'])->assertCreated();

        // Le contrôle de l'API ne lit pas la place avant d'écrire: c'est la contrainte
        // d'unicité de la base qui refuse le second billet, comme pour deux demandes simultanées.
        $this->bloquer($dominique, ['B1', 'A1'])
            ->assertConflict()
            ->assertJsonPath('message', 'Ces places viennent d\'être prises: A1. Choisissez-en d\'autres.')
            ->assertJsonPath('places_prises', [$this->places['A1']->id]);

        // Rien n'est enregistré pour le second spectateur: ni réservation, ni billet orphelin.
        $this->assertDatabaseMissing('reservations', ['user_id' => $dominique->id]);
        $this->assertDatabaseCount('billets', 1);
        $this->assertTrue($this->estLibre('B1'));
    }

    public function test_une_nouvelle_selection_remplace_la_precedente_sur_la_meme_seance(): void
    {
        $premiere = $this->bloquer($this->camille, ['A1', 'A2'])->json('data.id');

        $this->bloquer($this->camille, ['A2', 'A3'])->assertCreated();

        $this->assertDatabaseHas('reservations', ['id' => $premiere, 'statut' => 'annulee']);
        $this->assertSame(1, Reservation::where('user_id', $this->camille->id)->where('statut', 'en_attente')->count());
        $this->assertTrue($this->estLibre('A1'));
        $this->assertFalse($this->estLibre('A2'));
    }

    public function test_bloquer_des_places_demande_un_compte_spectateur(): void
    {
        $this->postJson(route('reservations.store', $this->seance), ['places' => [$this->places['A1']->id]])
            ->assertUnauthorized();

        $this->bloquer(User::factory()->administrateur()->create(), ['A1'])->assertForbidden();
        $this->bloquer(User::factory()->agent()->create(), ['A1'])->assertForbidden();
        $this->assertDatabaseCount('billets', 0);
    }

    public function test_les_places_d_une_seance_commencee_ou_non_publiee_ne_se_reservent_pas(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 20:30', 'Europe/Paris'));
        $this->bloquer($this->camille, ['A1'])
            ->assertConflict()
            ->assertJsonPath('message', 'Cette séance a déjà commencé: ses places ne se réservent plus.');

        $this->travelBack();
        $this->seance->update(['statut' => 'brouillon']);
        $this->bloquer($this->camille, ['A1'])->assertNotFound();
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_le_spectateur_suit_et_abandonne_sa_reservation_en_attente(): void
    {
        $id = $this->bloquer($this->camille, ['A1'])->json('data.id');
        $this->travel(4)->minutes();

        $this->getJson(route('reservations.show', $id))
            ->assertOk()
            ->assertJsonPath('data.secondes_restantes', 360)
            ->assertJsonPath('data.places.0.id', $this->places['A1']->id);

        $this->actingAs(User::factory()->create(), 'api')->getJson(route('reservations.show', $id))->assertNotFound();
        $this->actingAs(User::factory()->create(), 'api')->deleteJson(route('reservations.destroy', $id))->assertNotFound();

        $this->actingAs($this->camille, 'api')->deleteJson(route('reservations.destroy', $id))->assertNoContent();
        $this->assertDatabaseHas('reservations', ['id' => $id, 'statut' => 'annulee']);
        $this->assertTrue($this->estLibre('A1'));

        $this->actingAs($this->camille, 'api')->deleteJson(route('reservations.destroy', $id))
            ->assertConflict()
            ->assertJsonPath('message', 'Cette réservation n\'est plus en attente de paiement: elle ne peut pas être abandonnée.');
    }

    public function test_une_reservation_confirmee_ne_s_abandonne_pas(): void
    {
        $reservation = Reservation::factory()->confirmee()->create(['user_id' => $this->camille->id, 'seance_id' => $this->seance->id]);
        Billet::factory()->create(['reservation_id' => $reservation->id, 'place_id' => $this->places['A1']->id]);

        $this->actingAs($this->camille, 'api')->deleteJson(route('reservations.destroy', $reservation))->assertConflict();
        $this->assertFalse($this->estLibre('A1'));
    }
}
