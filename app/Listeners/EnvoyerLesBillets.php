<?php

namespace App\Listeners;

use App\Events\ReservationConfirmee;
use App\Mail\BilletsDeLaReservation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Envoie ses billets au spectateur. L'envoi passe par la file d'attente, vidée chaque minute
 * (routes/console.php): la confirmation du paiement n'attend pas le serveur d'e-mails, et un
 * envoi qui échoue est retenté.
 */
class EnvoyerLesBillets implements ShouldQueue
{
    /** Trois essais en tout, à une puis deux minutes d'intervalle: l'e-mail part en moins de 5 minutes. */
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 120];

    public function handle(ReservationConfirmee $evenement): void
    {
        $spectateur = $evenement->reservation->user;

        // Compte supprimé entre le paiement et l'envoi: plus d'adresse à qui écrire.
        if ($spectateur === null || $spectateur->anonymise_at !== null) {
            return;
        }

        Mail::to($spectateur)->send(new BilletsDeLaReservation($evenement->reservation));
    }
}
