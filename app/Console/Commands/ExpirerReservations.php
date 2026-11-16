<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Libère les places des réservations en attente dont le blocage de 10 minutes est dépassé.
 * Planifiée chaque minute dans routes/console.php.
 */
#[Signature('reservations:expirer')]
#[Description('Expire les réservations non payées après 10 minutes et libère leurs places')]
class ExpirerReservations extends Command
{
    public function handle(): int
    {
        $nombre = Reservation::expirerLesBlocagesDepasses();
        $this->info($nombre.' réservation(s) expirée(s).');

        return self::SUCCESS;
    }
}
