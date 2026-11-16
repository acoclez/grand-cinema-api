<?php

namespace Tests\Unit;

use App\Enums\StatutReservation;
use App\Models\Reservation;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Règle du blocage de 10 minutes, testée sur une réservation en mémoire, sans base de données.
 */
class ExpirationDuBlocageTest extends TestCase
{
    /**
     * Places choisies le 10 novembre 2026 à 10 h, réservation regardée à l'heure indiquée.
     */
    private function reservationBloqueeA(string $heure): Reservation
    {
        $reservation = new Reservation([
            'statut' => StatutReservation::EnAttente,
            'expire_at' => Carbon::parse('2026-11-10 10:00', 'Europe/Paris')->addMinutes(Reservation::BLOCAGE_MINUTES),
        ]);
        $this->travelTo(Carbon::parse('2026-11-10 '.$heure, 'Europe/Paris'));

        return $reservation;
    }

    public function test_les_places_restent_bloquees_jusqu_a_10_minutes_apres_le_choix(): void
    {
        $reservation = $this->reservationBloqueeA('10:07:30');

        $this->assertTrue($reservation->estBloquee());
        $this->assertSame(150, $reservation->secondesRestantes());
    }

    public function test_le_blocage_prend_fin_a_10_minutes(): void
    {
        $reservation = $this->reservationBloqueeA('10:10');

        $this->assertFalse($reservation->estBloquee());
        $this->assertSame(0, $reservation->secondesRestantes());
    }

    public function test_une_reservation_confirmee_ne_bloque_plus_rien(): void
    {
        $reservation = new Reservation(['statut' => StatutReservation::Confirmee, 'expire_at' => null]);

        $this->assertFalse($reservation->estBloquee());
        $this->assertSame(0, $reservation->secondesRestantes());
    }
}
