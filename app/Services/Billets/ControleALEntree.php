<?php

namespace App\Services\Billets;

use App\Enums\StatutReservation;
use App\Exceptions\RegleMetierException;
use App\Models\Billet;
use App\Models\Seance;

/**
 * Contrôle des billets à l'entrée d'une séance: l'agent scanne le QR code, qui contient le jeton
 * du billet. Un billet valide est aussitôt marqué utilisé: il ne peut plus servir.
 */
readonly class ControleALEntree
{
    public const string HORS_DU_JOUR = 'Le contrôle des billets n\'est ouvert que le jour de la séance.';

    /**
     * @param  string  $code  Contenu du QR code scanné
     *
     * @throws RegleMetierException La séance n'a pas lieu aujourd'hui
     */
    public function controler(Seance $seance, string $code): ControleDuBillet
    {
        if (! $seance->debut->isToday()) {
            throw new RegleMetierException(self::HORS_DU_JOUR);
        }

        $billet = Billet::query()
            ->where('qr_token', $code)
            ->whereHas('reservation', fn ($reservation) => $reservation->where('statut', StatutReservation::Confirmee))
            ->with(['seance.film', 'seance.salle', 'place', 'tarif'])
            ->first();

        if ($billet === null) {
            return new ControleDuBillet(ResultatDuControle::Invalide, 'Invalide: QR code inconnu ou billet non payé.');
        }

        if ($billet->seance_id !== $seance->id) {
            $autre = $billet->seance;

            return new ControleDuBillet(
                ResultatDuControle::Invalide,
                'Invalide: billet pour une autre séance, '.$autre->film->titre.' le '
                .$autre->debut->locale('fr')->isoFormat('dddd D MMMM [à] HH[:]mm').', '.$autre->salle->nom.'.',
                $billet,
            );
        }

        // Marqué utilisé seulement s'il ne l'est pas déjà: deux agents qui scannent le même
        // billet au même moment ne peuvent pas le faire entrer deux fois.
        $marque = Billet::query()->whereKey($billet->id)->whereNull('utilise_at')->update(['utilise_at' => now()]);
        $billet->refresh();

        if ($marque === 0) {
            return new ControleDuBillet(
                ResultatDuControle::DejaUtilise,
                'Déjà utilisé: premier passage à '.$billet->utilise_at?->format('H:i').'.',
                $billet,
            );
        }

        return new ControleDuBillet(ResultatDuControle::Valide, 'Valide', $billet);
    }
}
