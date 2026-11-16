<?php

namespace Tests\Feature\Controle;

use App\Enums\StatutSeance;
use App\Models\Billet;
use App\Models\Film;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Tarif;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * L'agent d'accueil scanne le QR code d'un billet à l'entrée de la salle et sait aussitôt si le
 * spectateur peut entrer.
 */
class ControleBilletTest extends TestCase
{
    use RefreshDatabase;

    private Seance $seance;

    private User $agent;

    private Billet $billet;

    protected function setUp(): void
    {
        parent::setUp();

        // Le contrôle se déroule le 12 novembre 2026 à 20 h 10, heure de Paris.
        $this->travelTo(Carbon::parse('2026-11-12 20:10', 'Europe/Paris'));
        $this->agent = User::factory()->agent()->create();
        $this->seance = $this->seance('Signal perdu', '2026-11-12 20:30');
        $this->billet = $this->billet($this->seance, 'B', 7, confirmee: true);
    }

    private function seance(string $titre, string $debut, string $salle = 'Salle 3'): Seance
    {
        $salle = Salle::factory()->create(['nom' => $salle]);

        return Seance::factory()->create([
            'film_id' => Film::factory()->create(['titre' => $titre])->id,
            'salle_id' => $salle->id,
            'debut' => Carbon::parse($debut),
        ]);
    }

    private function billet(Seance $seance, string $rangee, int $numero, bool $confirmee): Billet
    {
        $reservation = Reservation::factory()
            ->when($confirmee, fn ($fabrique) => $fabrique->confirmee())
            ->create(['seance_id' => $seance->id]);
        $tarif = Tarif::query()->firstOrCreate(['seance_id' => $seance->id, 'libelle' => 'Réduit'], ['prix_centimes' => 700]);

        return Billet::factory()->create([
            'reservation_id' => $reservation->id,
            'place_id' => Place::factory()->create(['salle_id' => $seance->salle_id, 'rangee' => $rangee, 'numero' => $numero, 'pmr' => false])->id,
            'tarif_id' => $tarif->id,
            'prix_centimes' => 700,
        ]);
    }

    private function scanner(string $code, ?User $compte = null, ?Seance $seance = null): TestResponse
    {
        return $this->actingAs($compte ?? $this->agent, 'api')
            ->postJson(route('controle.billets.store', $seance ?? $this->seance), ['code' => $code]);
    }

    public function test_un_billet_valide_affiche_le_film_la_salle_et_la_place_puis_est_marque_utilise(): void
    {
        $this->scanner($this->billet->qr_token)
            ->assertOk()
            ->assertExactJson(['data' => [
                'resultat' => 'valide',
                'message' => 'Valide',
                'billet' => [
                    'film' => 'Signal perdu',
                    'salle' => 'Salle 3',
                    'debut' => '2026-11-12T20:30:00+01:00',
                    'place' => ['rangee' => 'B', 'numero' => 7, 'pmr' => false],
                    'tarif' => 'Réduit',
                    'utilise_at' => '2026-11-12T20:10:00+01:00',
                ],
            ]]);

        $this->assertSame('2026-11-12 20:10', $this->billet->fresh()?->utilise_at?->format('Y-m-d H:i'));
    }

    public function test_un_billet_deja_utilise_affiche_l_heure_du_premier_passage(): void
    {
        $this->scanner($this->billet->qr_token)->assertJsonPath('data.resultat', 'valide');
        $this->travel(7)->minutes();

        $this->scanner($this->billet->qr_token)
            ->assertOk()
            ->assertJsonPath('data.resultat', 'deja_utilise')
            ->assertJsonPath('data.message', 'Déjà utilisé: premier passage à 20:10.')
            ->assertJsonPath('data.billet.place.numero', 7)
            ->assertJsonPath('data.billet.utilise_at', '2026-11-12T20:10:00+01:00');

        $this->assertSame('20:10', $this->billet->fresh()?->utilise_at?->format('H:i'));
    }

    public function test_un_qr_code_inconnu_ou_un_billet_non_paye_est_invalide(): void
    {
        $nonPaye = $this->billet($this->seance, 'B', 8, confirmee: false);

        foreach (['code-inconnu', $nonPaye->qr_token] as $code) {
            $this->scanner($code)
                ->assertOk()
                ->assertExactJson(['data' => [
                    'resultat' => 'invalide',
                    'message' => 'Invalide: QR code inconnu ou billet non payé.',
                    'billet' => null,
                ]]);
        }

        $this->assertNull($nonPaye->fresh()?->utilise_at);
    }

    public function test_un_billet_d_une_autre_seance_est_invalide_et_reste_utilisable(): void
    {
        $autre = $this->billet($this->seance('Aurore', '2026-11-12 21:45', 'Salle 5'), 'C', 2, confirmee: true);

        $this->scanner($autre->qr_token)
            ->assertOk()
            ->assertJsonPath('data.resultat', 'invalide')
            ->assertJsonPath('data.message', 'Invalide: billet pour une autre séance, Aurore le jeudi 12 novembre à 21:45, Salle 5.')
            ->assertJsonPath('data.billet.film', 'Aurore');

        // Le spectateur pourra entrer à la bonne séance.
        $this->assertNull($autre->fresh()?->utilise_at);
    }

    public function test_le_controle_n_est_ouvert_que_le_jour_de_la_seance(): void
    {
        $demain = $this->seance('Aurore', '2026-11-13 20:30', 'Salle 6');
        $billet = $this->billet($demain, 'A', 1, confirmee: true);

        $this->scanner($billet->qr_token, seance: $demain)
            ->assertConflict()
            ->assertExactJson(['message' => 'Le contrôle des billets n\'est ouvert que le jour de la séance.']);

        $this->assertNull($billet->fresh()?->utilise_at);
    }

    public function test_seuls_les_agents_et_l_administrateur_controlent_les_billets_d_une_seance_publiee(): void
    {
        $this->postJson(route('controle.billets.store', $this->seance), ['code' => $this->billet->qr_token])->assertUnauthorized();
        $this->scanner($this->billet->qr_token, User::factory()->create())->assertForbidden();
        $this->assertNull($this->billet->fresh()?->utilise_at);

        $this->scanner($this->billet->qr_token, User::factory()->administrateur()->create())->assertJsonPath('data.resultat', 'valide');

        $this->scanner('', seance: $this->seance)->assertUnprocessable()->assertJsonValidationErrors('code');
        $brouillon = $this->seance('Projet', '2026-11-12 22:00', 'Salle 7');
        $brouillon->update(['statut' => StatutSeance::Brouillon]);
        $this->scanner('code', seance: $brouillon)->assertNotFound();
    }
}
