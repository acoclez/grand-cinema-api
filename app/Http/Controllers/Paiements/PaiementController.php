<?php

namespace App\Http\Controllers\Paiements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Paiements\PaiementRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\Paiements\PaiementEnLigne;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paiement en ligne d'une réservation par le spectateur qui l'a faite.
 */
#[Group('Paiement', 'Paiement par carte sur la page de Stripe: l\'application ne voit ni n\'enregistre aucune donnée de carte (US-09).')]
class PaiementController extends Controller
{
    /**
     * Choisir ses tarifs et payer.
     *
     * Attribue à chaque place de la réservation un tarif de la séance, calcule le montant et
     * ouvre la page de paiement Stripe: l'application web y redirige le spectateur avec url.
     * Après le paiement, Stripe le renvoie sur la page de sa réservation. Choisir de nouveaux
     * tarifs ferme la page de paiement précédente. Une réservation dont les places sont
     * libérées ne se paie plus (409).
     */
    public function store(PaiementRequest $request, Reservation $reservation, PaiementEnLigne $paiement): JsonResponse
    {
        $session = $paiement->demarrer($reservation, $request->tarifsParPlace());

        return response()->json(['data' => [
            // Page de paiement de Stripe
            'url' => $session->url,
        ]], 201);
    }

    /**
     * Vérifier le paiement.
     *
     * Appelée au retour de la page de paiement: demande à Stripe où en est le paiement, sans
     * attendre sa notification. Un paiement accepté confirme aussitôt la réservation.
     */
    public function verifier(Request $request, Reservation $reservation, PaiementEnLigne $paiement): ReservationResource
    {
        abort_unless($reservation->appartientA($request->user()), 404);

        $paiement->verifier($reservation);

        return new ReservationResource($reservation->refresh()->load(Reservation::RELATIONS_AFFICHEES));
    }
}
