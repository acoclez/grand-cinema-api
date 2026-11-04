<?php

namespace Tests\Feature;

use App\Enums\StatutPaiement;
use App\Models\Billet;
use App\Models\Film;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Models\Seance;
use App\Models\Tarif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Relations entre les tables et filtres du site public.
 */
class RelationsDesModelesTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_reservation_relie_son_spectateur_ses_billets_et_ses_paiements(): void
    {
        $tarif = Tarif::factory()->create();
        $reservation = Reservation::factory()->create(['seance_id' => $tarif->seance_id]);
        $billet = Billet::factory()->create(['reservation_id' => $reservation->id, 'tarif_id' => $tarif->id]);
        $paiement = Paiement::factory()->create(['reservation_id' => $reservation->id]);

        $this->assertNotNull($reservation->user);
        $this->assertTrue($reservation->user->reservations->contains($reservation));
        $this->assertTrue($reservation->billets->contains($billet));
        $this->assertTrue($reservation->paiements->contains($paiement));

        $this->assertTrue($paiement->reservation->is($reservation));
        $this->assertSame(StatutPaiement::Accepte, $paiement->statut);

        $this->assertTrue($billet->reservation->is($reservation));
        $this->assertTrue($billet->seance->is($reservation->seance));
        $this->assertTrue($tarif->seance->is($reservation->seance));
        $this->assertNotNull($billet->tarif);
        $this->assertTrue($billet->tarif->is($tarif));
    }

    public function test_une_seance_retrouve_ses_reservations_et_ses_billets(): void
    {
        $billet = Billet::factory()->create();
        $seance = $billet->seance;

        $this->assertTrue($seance->billets->contains($billet));
        $this->assertTrue($seance->reservations->contains($billet->reservation));
        $this->assertTrue($billet->place->billets->contains($billet));
        $this->assertTrue($seance->salle->seances->contains($seance));
        $this->assertTrue($seance->film->seances->contains($seance));
    }

    public function test_le_site_public_n_affiche_que_les_films_non_archives_et_les_seances_publiees(): void
    {
        $film = Film::factory()->create();
        Film::factory()->archive()->create();

        $publiee = Seance::factory()->create(['film_id' => $film->id]);
        Seance::factory()->brouillon()->create(['film_id' => $film->id]);

        $this->assertSame([$film->id], Film::aLAffiche()->pluck('id')->all());
        $this->assertSame([$publiee->id], Seance::publiee()->pluck('id')->all());
    }
}
