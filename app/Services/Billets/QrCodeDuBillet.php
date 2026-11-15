<?php

namespace App\Services\Billets;

use App\Models\Billet;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR code d'un billet. Il contient seulement le jeton du billet: 48 caractères aléatoires, sans
 * donnée personnelle et impossibles à deviner. Le contrôle à l'entrée retrouve le billet par ce jeton.
 */
readonly class QrCodeDuBillet
{
    /** Image SVG en adresse data:, affichée par l'application web. */
    public function svg(Billet $billet): string
    {
        return $this->generer($billet, QROutputInterface::MARKUP_SVG);
    }

    /** Image PNG en adresse data:, insérée dans le billet PDF. */
    public function png(Billet $billet): string
    {
        return $this->generer($billet, QROutputInterface::GDIMAGE_PNG);
    }

    private function generer(Billet $billet, string $format): string
    {
        $options = new QROptions([
            'outputType' => $format,
            'outputBase64' => true,
            // Niveau de correction M: le code reste lisible sur un écran rayé ou une feuille froissée.
            'eccLevel' => EccLevel::M,
            'scale' => 8,
            'svgAddXmlHeader' => false,
        ]);

        return (new QRCode($options))->render($billet->qr_token);
    }
}
