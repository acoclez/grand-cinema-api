<?php

namespace App\Services\Billets;

use App\Models\Billet;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Billet PDF d'une place: le film, la séance, la salle, la place, le tarif et le QR code à
 * présenter à l'entrée. Généré à la demande, jamais stocké.
 */
readonly class BilletPdf
{
    /** Relations lues par le billet, à charger avant de le générer. */
    public const array RELATIONS = ['reservation', 'seance.film', 'seance.salle', 'place', 'tarif'];

    public function __construct(private QrCodeDuBillet $qrCode) {}

    /** Contenu du fichier PDF. */
    public function contenu(Billet $billet): string
    {
        $billet->loadMissing(self::RELATIONS);

        return Pdf::loadView('billets.pdf', ['billet' => $billet, 'qrCode' => $this->qrCode->png($billet)])
            ->setPaper('a5')
            ->output();
    }

    /** Nom du fichier, par exemple billet-R-7KX4M2PA-B12.pdf. */
    public function nomDeFichier(Billet $billet): string
    {
        $billet->loadMissing(self::RELATIONS);

        return 'billet-'.$billet->reservation->numero.'-'.$billet->place->libelle().'.pdf';
    }
}
