<?php

namespace Tests\Feature\Billets;

use App\Enums\StatutReservation;
use App\Events\ReservationConfirmee;
use App\Listeners\EnvoyerLesBillets;
use App\Mail\BilletsDeLaReservation;
use App\Models\Billet;
use App\Models\Film;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Tarif;
use App\Models\User;
use App\Services\Billets\QrCodeDuBillet;
use App\Services\Paiements\EtatDuPaiement;
use App\Services\Paiements\PrestataireDePaiement;
use chillerlan\QRCode\QRCode;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Doubles\PrestataireDePaiementSimule;
use Tests\TestCase;

/**
 * Une fois le paiement accepté, le spectateur reçoit un billet PDF par place, avec un QR code
 * unique, par e-mail et dans « Mes billets ». Le prestataire de paiement est simulé.
 */
class BilletElectroniqueTest extends TestCase
{
    use RefreshDatabase;

    private PrestataireDePaiementSimule $prestataire;

    private Seance $seance;

    /** @var array<string, Place> Places de la salle par libellé: A1 à A4 */
    private array $places = [];

    /** @var array<string, Tarif> */
    private array $tarifs = [];

    private User $camille;

    protected function setUp(): void
    {
        parent::setUp();

        // Les tests se déroulent le 10 novembre 2026 à 10 h, heure de Paris.
        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'Europe/Paris'));
        $this->prestataire = new PrestataireDePaiementSimule;
        $this->app->instance(PrestataireDePaiement::class, $this->prestataire);

        $salle = Salle::factory()->create(['nom' => 'Salle 3', 'capacite' => 4]);
        foreach (range(1, 4) as $numero) {
            $this->places['A'.$numero] = Place::factory()->create(['salle_id' => $salle->id, 'rangee' => 'A', 'numero' => $numero, 'pmr' => false]);
        }
        $film = Film::factory()->create(['titre' => 'Signal perdu', 'duree_minutes' => 120]);
        $this->seance = Seance::factory()->create(['film_id' => $film->id, 'salle_id' => $salle->id, 'debut' => Carbon::parse('2026-11-12 20:30'), 'langue' => 'vo']);
        foreach (['Plein' => 950, 'Réduit' => 700] as $libelle => $prix) {
            $this->tarifs[$libelle] = Tarif::factory()->create(['seance_id' => $this->seance->id, 'libelle' => $libelle, 'prix_centimes' => $prix]);
        }
        $this->camille = User::factory()->create(['prenom' => 'Camille', 'email' => 'camille@exemple.fr']);
    }

    /**
     * Bloque A1 et A2, choisit Plein et Réduit, puis le prestataire accepte le paiement.
     */
    private function reserverEtPayer(): Reservation
    {
        $id = $this->actingAs($this->camille, 'api')
            ->postJson(route('reservations.store', $this->seance), ['places' => [$this->places['A1']->id, $this->places['A2']->id]])
            ->assertCreated()
            ->json('data.id');
        $reservation = Reservation::findOrFail($id);

        $this->postJson(route('reservations.paiement', $reservation), ['billets' => [
            ['place_id' => $this->places['A1']->id, 'tarif_id' => $this->tarifs['Plein']->id],
            ['place_id' => $this->places['A2']->id, 'tarif_id' => $this->tarifs['Réduit']->id],
        ]])->assertCreated();
        $this->prestataire->etats['cs_test_1'] = EtatDuPaiement::Accepte;

        $this->postJson(route('reservations.paiement.verification', $reservation))
            ->assertOk()
            ->assertJsonPath('data.statut', 'confirmee');

        return $reservation;
    }

    /**
     * Réservation confirmée de Camille avec un billet, pour une séance qui commence à $debut.
     */
    private function billetConfirme(string $debut, ?User $spectateur = null, bool $confirmee = true): Billet
    {
        $seance = Seance::factory()->create(['film_id' => $this->seance->film_id, 'salle_id' => $this->seance->salle_id, 'debut' => Carbon::parse($debut)]);
        $reservation = Reservation::factory()
            ->when($confirmee, fn ($fabrique) => $fabrique->confirmee())
            ->create(['user_id' => ($spectateur ?? $this->camille)->id, 'seance_id' => $seance->id]);

        return Billet::factory()->create(['reservation_id' => $reservation->id, 'place_id' => $this->places['A3']->id]);
    }

    public function test_le_paiement_accepte_envoie_un_e_mail_avec_un_billet_pdf_par_place(): void
    {
        Mail::fake();

        $reservation = $this->reserverEtPayer();

        Mail::assertSent(BilletsDeLaReservation::class, 1);
        Mail::assertSent(BilletsDeLaReservation::class, function (BilletsDeLaReservation $mail) use ($reservation): bool {
            $this->assertTrue($mail->hasTo('camille@exemple.fr'));
            $this->assertSame('Vos billets pour Signal perdu, réservation '.$reservation->numero, $mail->envelope()->subject);
            $mail->assertSeeInOrderInHtml(['Bonjour Camille', 'Signal perdu', 'Jeudi 12 novembre 2026 à 20:30', 'Salle 3, VO', 'A1 Plein, A2 Réduit', $reservation->numero]);
            $mail->assertSeeInHtml('http://localhost:5173/mes-billets');

            $pieces = collect($mail->attachments());
            $this->assertSame(
                ['billet-'.$reservation->numero.'-A1.pdf', 'billet-'.$reservation->numero.'-A2.pdf'],
                $pieces->map(fn (Attachment $piece) => $piece->as)->all(),
            );
            $pieces->each(function (Attachment $piece): void {
                $this->assertSame('application/pdf', $piece->mime);
                $this->assertStringStartsWith('%PDF-', $piece->attachWith(fn () => '', fn ($contenu) => $contenu()));
            });

            return true;
        });
    }

    public function test_les_billets_partent_par_la_file_d_attente_videe_chaque_minute(): void
    {
        Queue::fake();

        $this->reserverEtPayer();

        // L'e-mail ne retarde pas la confirmation: il est mis en file d'attente.
        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $tache) => $tache->class === EnvoyerLesBillets::class);

        // La file est vidée chaque minute, et un envoi en échec retenté deux fois en 3 minutes.
        $vidage = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains($event->command ?? '', 'queue:work'));
        $this->assertNotNull($vidage);
        $this->assertSame('* * * * *', $vidage->expression);
        $this->assertStringContainsString('--stop-when-empty', (string) $vidage->command);
        $this->assertSame(3, (new EnvoyerLesBillets)->tries);
        $this->assertSame([60, 120], (new EnvoyerLesBillets)->backoff);
    }

    public function test_un_paiement_deja_traite_n_envoie_pas_les_billets_une_seconde_fois(): void
    {
        Mail::fake();
        $reservation = $this->reserverEtPayer();

        // La notification de Stripe arrive après la vérification au retour du spectateur.
        $this->call('POST', route('paiements.notifications'), content: 'cs_test_1:Accepte')->assertOk();
        $this->actingAs($this->camille, 'api')->postJson(route('reservations.paiement.verification', $reservation))->assertOk();

        Mail::assertSent(BilletsDeLaReservation::class, 1);
    }

    public function test_aucun_billet_n_est_envoye_sans_paiement_accepte_ni_apres_suppression_du_compte(): void
    {
        Mail::fake();

        // Paiement remboursé: les places avaient été libérées avant son arrivée.
        $id = $this->actingAs($this->camille, 'api')
            ->postJson(route('reservations.store', $this->seance), ['places' => [$this->places['A1']->id]])
            ->json('data.id');
        $this->postJson(route('reservations.paiement', $id), ['billets' => [['place_id' => $this->places['A1']->id, 'tarif_id' => $this->tarifs['Plein']->id]]]);
        Reservation::findOrFail($id)->liberer(StatutReservation::Expiree);
        $this->call('POST', route('paiements.notifications'), content: 'cs_test_1:Accepte')->assertOk();

        // Compte supprimé entre la confirmation et l'envoi.
        $billet = $this->billetConfirme('2026-11-12 20:30');
        $this->camille->anonymiser();
        (new EnvoyerLesBillets)->handle(new ReservationConfirmee($billet->reservation));

        Mail::assertNothingSent();
    }

    public function test_mes_billets_donne_les_billets_des_seances_a_venir(): void
    {
        $reservation = $this->reserverEtPayer();
        $enCours = $this->billetConfirme('2026-11-10 09:00');      // finit à 11 h: encore valable
        $terminee = $this->billetConfirme('2026-11-10 07:00');     // finie à 9 h
        $enAttente = $this->billetConfirme('2026-11-11 20:00', confirmee: false);
        $autreSpectateur = $this->billetConfirme('2026-11-11 20:00', User::factory()->create());

        $reponse = $this->actingAs($this->camille, 'api')->getJson(route('billets.index'))->assertOk();

        $this->assertSame(
            [[$enCours->id, 'A3'], [$reservation->billets()->orderBy('place_id')->value('id'), 'A1'], [$reservation->billets()->orderByDesc('place_id')->value('id'), 'A2']],
            collect($reponse->json('data'))->map(fn (array $billet) => [$billet['id'], $billet['place']['rangee'].$billet['place']['numero']])->all(),
        );
        $reponse->assertJsonPath('data.1.reservation.numero', $reservation->numero)
            ->assertJsonPath('data.1.seance.film.titre', 'Signal perdu')
            ->assertJsonPath('data.1.seance.salle.nom', 'Salle 3')
            ->assertJsonPath('data.1.seance.debut', '2026-11-12T20:30:00+01:00')
            ->assertJsonPath('data.1.seance.langue', 'vo')
            ->assertJsonPath('data.1.tarif', 'Plein')
            ->assertJsonPath('data.1.prix_centimes', 950)
            ->assertJsonPath('data.1.utilise_at', null);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $reponse->json('data.1.qr_code'));
        $this->assertArrayNotHasKey('qr_token', $reponse->json('data.1'));
        $this->assertNotContains($terminee->id, collect($reponse->json('data'))->pluck('id'));
        $this->assertNotContains($enAttente->id, collect($reponse->json('data'))->pluck('id'));
        $this->assertNotContains($autreSpectateur->id, collect($reponse->json('data'))->pluck('id'));
    }

    public function test_le_qr_code_ne_contient_que_le_jeton_aleatoire_du_billet(): void
    {
        $reservation = $this->reserverEtPayer();
        [$premier, $second] = $reservation->billets()->orderBy('place_id')->get()->all();

        // 48 caractères aléatoires, différents pour chaque billet.
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{48}$/', $premier->qr_token);
        $this->assertNotSame($premier->qr_token, $second->qr_token);

        // Relu comme le fera le contrôle à l'entrée: le jeton seul, aucune donnée personnelle.
        $image = base64_decode(explode(',', app(QrCodeDuBillet::class)->png($premier), 2)[1]);
        $contenu = (string) (new QRCode)->readFromBlob($image);
        $this->assertSame($premier->qr_token, $contenu);
        $this->assertStringNotContainsString('Camille', $contenu);
        $this->assertStringNotContainsString('camille@exemple.fr', $contenu);
    }

    public function test_seul_le_spectateur_telecharge_le_billet_pdf_d_une_reservation_confirmee(): void
    {
        $reservation = $this->reserverEtPayer();
        $billet = $reservation->billets()->orderBy('place_id')->firstOrFail();
        $enAttente = $this->billetConfirme('2026-11-11 20:00', confirmee: false);

        $reponse = $this->actingAs($this->camille, 'api')->get(route('billets.pdf', $billet))->assertOk();
        $reponse->assertHeader('Content-Type', 'application/pdf');
        $reponse->assertDownload('billet-'.$reservation->numero.'-A1.pdf');
        $this->assertStringStartsWith('%PDF-', $reponse->streamedContent());

        $this->actingAs($this->camille, 'api')->getJson(route('billets.pdf', $enAttente))->assertNotFound();
        $this->actingAs(User::factory()->create(), 'api')->getJson(route('billets.pdf', $billet))->assertNotFound();
        $this->actingAs(User::factory()->agent()->create(), 'api')->getJson(route('billets.index'))->assertForbidden();
    }
}
