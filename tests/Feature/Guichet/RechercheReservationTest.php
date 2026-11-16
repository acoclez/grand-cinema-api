<?php

namespace Tests\Feature\Guichet;

use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
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
 * L'agent d'accueil retrouve une réservation par son numéro, le nom du client ou la séance,
 * pour renseigner un spectateur au guichet.
 */
class RechercheReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private Seance $signal;

    private Seance $aurore;

    /** @var array<string, Reservation> Réservations par numéro */
    private array $reservations = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-12 10:00', 'Europe/Paris'));
        $this->agent = User::factory()->agent()->create();
        $this->signal = $this->seance('Signal perdu', '2026-11-12 20:30', 'Salle 3');
        $this->aurore = $this->seance('Aurore', '2026-11-13 18:00', 'Salle 5');

        $camille = User::factory()->create(['prenom' => 'Camille', 'nom' => 'Durand', 'email' => 'camille.durand@exemple.fr']);
        $paul = User::factory()->create(['prenom' => 'Paul', 'nom' => 'Durand', 'email' => 'paul.durand@exemple.fr']);
        $martin = User::factory()->create(['prenom' => 'Camille', 'nom' => 'Martin', 'email' => 'c.martin@exemple.fr']);
        $supprime = User::factory()->create(['prenom' => 'Lou', 'nom' => 'Durand']);

        $payee = $this->reserver('R-7KX4M2PA', $this->signal, $camille, StatutReservation::Confirmee, ['A1' => 'Plein', 'A2' => 'Réduit']);
        $payee->paiements()->create(['prestataire' => 'stripe', 'reference_externe' => 'cs_test_1', 'statut' => StatutPaiement::Accepte, 'montant_centimes' => 1650]);
        $payee->billets()->whereHas('place', fn ($place) => $place->where('numero', 1))->update(['utilise_at' => Carbon::parse('2026-11-12 09:55')]);
        $this->reserver('R-QWERTY23', $this->aurore, $paul, StatutReservation::EnAttente, ['B1' => 'Plein']);
        $this->reserver('R-GUICHET2', $this->signal, null, StatutReservation::Confirmee, ['A3' => 'Plein']);
        $this->reserver('R-ANNULE77', $this->aurore, $martin, StatutReservation::Annulee, []);
        $this->reserver('R-ANONYME2', $this->signal, $supprime, StatutReservation::Confirmee, ['A4' => 'Plein']);
        $supprime->anonymiser();
    }

    private function seance(string $titre, string $debut, string $salle): Seance
    {
        $seance = Seance::factory()->create([
            'film_id' => Film::factory()->create(['titre' => $titre])->id,
            'salle_id' => Salle::factory()->create(['nom' => $salle])->id,
            'debut' => Carbon::parse($debut),
        ]);
        foreach (['Plein' => 950, 'Réduit' => 700] as $libelle => $prix) {
            Tarif::factory()->create(['seance_id' => $seance->id, 'libelle' => $libelle, 'prix_centimes' => $prix]);
        }

        return $seance;
    }

    /**
     * @param  array<string, string>  $places  Tarif par place, par exemple ['A1' => 'Plein']
     */
    private function reserver(string $numero, Seance $seance, ?User $client, StatutReservation $statut, array $places): Reservation
    {
        $reservation = Reservation::factory()->create([
            'numero' => $numero,
            'seance_id' => $seance->id,
            'user_id' => $client?->id,
            'canal' => $client === null ? 'guichet' : 'en_ligne',
            'statut' => $statut,
            'expire_at' => $statut === StatutReservation::EnAttente ? now()->addMinutes(10) : null,
        ]);
        $montant = 0;
        foreach ($places as $libelle => $libelleTarif) {
            $tarif = $seance->tarifs()->where('libelle', $libelleTarif)->firstOrFail();
            Billet::factory()->create([
                'reservation_id' => $reservation->id,
                'place_id' => Place::factory()->create(['salle_id' => $seance->salle_id, 'rangee' => $libelle[0], 'numero' => (int) substr($libelle, 1), 'pmr' => false])->id,
                'tarif_id' => $tarif->id,
                'prix_centimes' => $tarif->prix_centimes,
            ]);
            $montant += $tarif->prix_centimes;
        }
        $reservation->update(['montant_centimes' => $montant]);

        return $this->reservations[$numero] = $reservation;
    }

    /**
     * @param  array<string, int|string>  $criteres
     */
    private function rechercher(array $criteres, ?User $compte = null): TestResponse
    {
        return $this->actingAs($compte ?? $this->agent, 'api')->getJson(route('guichet.reservations.index', $criteres));
    }

    /**
     * @return list<string>
     */
    private function numeros(TestResponse $reponse): array
    {
        return collect($reponse->assertOk()->json('data'))->pluck('numero')->all();
    }

    public function test_le_numero_de_reservation_retrouve_la_reservation_avec_son_statut(): void
    {
        $this->assertSame(['R-7KX4M2PA'], $this->numeros($this->rechercher(['recherche' => 'r-7kx'])));
        $this->assertSame(['R-7KX4M2PA'], $this->numeros($this->rechercher(['recherche' => '7KX4M2PA'])));

        $this->rechercher(['recherche' => 'R-7KX4M2PA'])
            ->assertJsonPath('data', [[
                'id' => $this->reservations['R-7KX4M2PA']->id,
                'numero' => 'R-7KX4M2PA',
                'statut' => 'confirmee',
                'canal' => 'en_ligne',
                'client' => ['prenom' => 'Camille', 'nom' => 'Durand', 'email' => 'camille.durand@exemple.fr'],
                'seance' => [
                    'id' => $this->signal->id,
                    'film' => ['id' => $this->signal->film_id, 'titre' => 'Signal perdu'],
                    'salle' => ['nom' => 'Salle 3'],
                    'debut' => '2026-11-12T20:30:00+01:00',
                ],
                'places' => 2,
                'montant_centimes' => 1650,
                'paiement' => 'accepte',
            ]]);
    }

    public function test_le_nom_le_prenom_ou_l_adresse_e_mail_retrouvent_les_reservations_du_client(): void
    {
        // Les séances les plus récentes d'abord.
        $this->assertSame(['R-QWERTY23', 'R-7KX4M2PA'], $this->numeros($this->rechercher(['recherche' => 'durand'])));
        $this->assertSame(['R-7KX4M2PA'], $this->numeros($this->rechercher(['recherche' => 'Camille Durand'])));
        $this->assertSame(['R-ANNULE77', 'R-7KX4M2PA'], $this->numeros($this->rechercher(['recherche' => 'camille'])));
        $this->assertSame(['R-QWERTY23'], $this->numeros($this->rechercher(['recherche' => 'paul.durand@'])));

        $this->rechercher(['recherche' => 'martin'])
            ->assertJsonPath('data.0.statut', 'annulee')
            ->assertJsonPath('data.0.places', 0);

        // Un compte supprimé n'est plus retrouvé par son nom, ni par le nom qui l'a remplacé.
        $this->assertSame([], $this->numeros($this->rechercher(['recherche' => 'supprimé'])));
    }

    public function test_la_seance_liste_ses_reservations_en_ligne_et_au_guichet(): void
    {
        $reponse = $this->rechercher(['seance' => $this->signal->id]);

        $this->assertSame(['R-ANONYME2', 'R-GUICHET2', 'R-7KX4M2PA'], $this->numeros($reponse));
        $reponse->assertJsonPath('data.0.client', null)
            ->assertJsonPath('data.1.canal', 'guichet')
            ->assertJsonPath('data.1.client', null)
            ->assertJsonPath('data.1.paiement', null);

        $this->assertSame(['R-7KX4M2PA'], $this->numeros($this->rechercher(['seance' => $this->signal->id, 'recherche' => 'camille'])));
    }

    public function test_une_recherche_sans_critere_ne_liste_rien(): void
    {
        $this->rechercher([])
            ->assertUnprocessable()
            ->assertJsonPath('errors.recherche.0', 'Saisissez un numéro de réservation, un nom ou une adresse e-mail, ou choisissez une séance.');
        $this->rechercher(['recherche' => 'a'])->assertUnprocessable()->assertJsonValidationErrors('recherche');
        // Le caractère % ne sert pas à tout lister.
        $this->rechercher(['recherche' => '%%'])->assertUnprocessable()->assertJsonValidationErrors('recherche');
        $this->rechercher(['seance' => 999])->assertUnprocessable()->assertJsonValidationErrors('seance');
    }

    public function test_les_resultats_sont_pagines_par_20(): void
    {
        foreach (range(1, 23) as $rang) {
            Reservation::factory()->confirmee()->create(['seance_id' => $this->aurore->id]);
        }

        $this->rechercher(['seance' => $this->aurore->id])
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 2);
        $this->rechercher(['seance' => $this->aurore->id, 'page' => 2])->assertJsonCount(5, 'data');
    }

    public function test_le_detail_montre_les_places_les_tarifs_le_paiement_et_l_etat_des_billets(): void
    {
        $this->actingAs($this->agent, 'api')
            ->getJson(route('guichet.reservations.show', $this->reservations['R-7KX4M2PA']))
            ->assertOk()
            ->assertJsonPath('data.numero', 'R-7KX4M2PA')
            ->assertJsonPath('data.statut', 'confirmee')
            ->assertJsonPath('data.client.email', 'camille.durand@exemple.fr')
            ->assertJsonPath('data.paiement', 'accepte')
            ->assertJsonPath('data.montant_centimes', 1650)
            ->assertJsonPath('data.places.0.rangee', 'A')
            ->assertJsonPath('data.places.0.numero', 1)
            ->assertJsonPath('data.places.0.tarif', 'Plein')
            ->assertJsonPath('data.places.0.prix_centimes', 950)
            ->assertJsonPath('data.places.0.utilise_at', '2026-11-12T09:55:00+01:00')
            ->assertJsonPath('data.places.1.tarif', 'Réduit')
            ->assertJsonPath('data.places.1.utilise_at', null);

        $this->actingAs($this->agent, 'api')
            ->getJson(route('guichet.reservations.show', $this->reservations['R-GUICHET2']))
            ->assertJsonPath('data.canal', 'guichet')
            ->assertJsonPath('data.client', null)
            ->assertJsonPath('data.paiement', null);
    }

    public function test_seuls_les_agents_et_l_administrateur_recherchent_les_reservations(): void
    {
        $this->getJson(route('guichet.reservations.index', ['recherche' => 'durand']))->assertUnauthorized();
        $this->rechercher(['recherche' => 'durand'], User::factory()->create())->assertForbidden();
        $this->actingAs(User::factory()->create(), 'api')
            ->getJson(route('guichet.reservations.show', $this->reservations['R-7KX4M2PA']))
            ->assertForbidden();

        $this->assertCount(2, $this->numeros($this->rechercher(['recherche' => 'durand'], User::factory()->administrateur()->create())));
        $this->actingAs($this->agent, 'api')->getJson(route('guichet.reservations.show', 9999))->assertNotFound();
    }
}
