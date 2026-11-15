<?php

namespace App\Http\Controllers\Paiements;

use App\Http\Controllers\Controller;
use App\Services\Paiements\PaiementEnLigne;
use App\Services\Paiements\PrestataireDePaiement;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Paiement')]
class NotificationStripeController extends Controller
{
    /**
     * Recevoir une notification de Stripe.
     *
     * Appelée par Stripe (webhook), pas par l'application web: paiement accepté
     * (checkout.session.completed) ou page de paiement fermée (checkout.session.expired).
     * La signature de l'en-tête Stripe-Signature est vérifiée: une notification qui ne vient
     * pas de Stripe est refusée (400). Une notification déjà traitée ne change rien.
     *
     * @unauthenticated
     */
    public function __invoke(Request $request, PrestataireDePaiement $prestataire, PaiementEnLigne $paiement): JsonResponse
    {
        $notification = $prestataire->lireNotification($request->getContent(), (string) $request->header('Stripe-Signature'));

        if ($notification !== null) {
            $paiement->enregistrer($notification);
        }

        return response()->json(['recue' => true]);
    }
}
