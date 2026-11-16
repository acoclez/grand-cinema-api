<?php

namespace Tests\Feature;

use App\Enums\TypeSalle;
use App\Models\Billet;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Salle;
use App\Models\Seance;
use Database\Seeders\SalleSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeleDeDonneesTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_10_salles_totalisent_1700_places(): void
    {
        $this->seed(SalleSeeder::class);

        $this->assertSame(10, Salle::count());
        $this->assertSame(1700, Place::count());
        $this->assertSame(1700, (int) Salle::sum('capacite'));

        $this->assertSame(600, $this->placesDuType(TypeSalle::PremiumImax));
        $this->assertSame(720, $this->placesDuType(TypeSalle::Standard));
        $this->assertSame(240, $this->placesDuType(TypeSalle::Vip));
        $this->assertSame(140, $this->placesDuType(TypeSalle::Evenementielle));
        $this->assertSame(40, Place::where('pmr', true)->count());
    }

    public function test_une_seance_relie_un_film_une_salle_et_ses_tarifs(): void
    {
        $seance = Seance::factory()->create();
        $seance->tarifs()->create(['libelle' => 'Plein', 'prix_centimes' => 950]);

        $seance->refresh();

        $this->assertNotNull($seance->film);
        $this->assertNotNull($seance->salle);
        $this->assertCount(1, $seance->tarifs);
        $this->assertSame(950, $seance->tarifs->first()?->prix_centimes);
    }

    public function test_une_reservation_recoit_un_numero_et_ses_billets_un_qr_code(): void
    {
        $reservation = Reservation::factory()->create();
        $billet = Billet::factory()->create(['reservation_id' => $reservation->id]);

        $this->assertMatchesRegularExpression('/^R-[A-Z2-9]{8}$/', $reservation->numero);
        $this->assertSame(48, strlen($billet->qr_token));
        $this->assertSame($reservation->seance_id, $billet->seance_id);
        $this->assertSame($reservation->seance->salle_id, $billet->place->salle_id);
    }

    public function test_une_place_ne_peut_etre_vendue_qu_une_fois_par_seance(): void
    {
        $seance = Seance::factory()->create();
        $place = Place::factory()->create(['salle_id' => $seance->salle_id]);

        $premiere = Reservation::factory()->create(['seance_id' => $seance->id]);
        $seconde = Reservation::factory()->create(['seance_id' => $seance->id]);

        Billet::factory()->create(['reservation_id' => $premiere->id, 'seance_id' => $seance->id, 'place_id' => $place->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        Billet::factory()->create(['reservation_id' => $seconde->id, 'seance_id' => $seance->id, 'place_id' => $place->id]);
    }

    public function test_une_vente_au_guichet_n_a_pas_de_compte_spectateur(): void
    {
        $reservation = Reservation::factory()->guichet()->create();

        $this->assertNull($reservation->user_id);
        $this->assertSame('guichet', $reservation->canal->value);
    }

    private function placesDuType(TypeSalle $type): int
    {
        return Place::whereHas('salle', fn ($query) => $query->where('type', $type))->count();
    }
}
