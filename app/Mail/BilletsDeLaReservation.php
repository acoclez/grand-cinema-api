<?php

namespace App\Mail;

use App\Models\Billet;
use App\Models\Reservation;
use App\Services\Billets\BilletPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

/**
 * E-mail envoyé quand une réservation en ligne est confirmée: un billet PDF par place, chacun
 * avec son QR code, et le lien vers « Mes billets ».
 */
class BilletsDeLaReservation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation)
    {
        $this->reservation->loadMissing(['user', 'seance.film', 'seance.salle', 'billets.place', 'billets.tarif']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Vos billets pour '.$this->reservation->seance->film->titre.', réservation '.$this->reservation->numero,
        );
    }

    public function content(): Content
    {
        $seance = $this->reservation->seance;

        return new Content(
            view: 'mail.billets',
            text: 'mail.billets-texte',
            with: [
                'prenom' => $this->reservation->user?->prenom,
                'film' => $seance->film->titre,
                'quand' => ucfirst($seance->debut->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[:]mm')),
                'salle' => $seance->salle->nom.', '.strtoupper($seance->langue->value),
                'places' => $this->places(),
                'numero' => $this->reservation->numero,
                'lienMesBillets' => Config::string('app.frontend_url').'/mes-billets',
            ],
        );
    }

    /**
     * Un billet PDF par place.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = App::make(BilletPdf::class);

        return $this->billets()
            ->map(fn (Billet $billet) => Attachment::fromData(fn (): string => $pdf->contenu($billet), $pdf->nomDeFichier($billet))
                ->withMime('application/pdf'))
            ->all();
    }

    /** Places et tarifs, par exemple « B1 Plein, B2 Réduit ». */
    private function places(): string
    {
        return $this->billets()
            ->map(fn (Billet $billet) => trim($billet->place->libelle().' '.$billet->tarif?->libelle))
            ->join(', ');
    }

    /**
     * @return Collection<int, Billet>
     */
    private function billets(): Collection
    {
        return $this->reservation->billets->sortBy(['place.rangee', 'place.numero'])->values();
    }
}
