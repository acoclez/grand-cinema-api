<?php

namespace App\Http\Controllers\Guichet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guichet\VenteGuichetRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\Seance;
use App\Services\Reservations\VenteAuGuichet;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

/**
 * Vente de places au guichet par un agent d'accueil, sur le même plan de salle que le site.
 */
#[Group('Guichet', 'Vente de places au guichet, sur le même stock de places que la vente en ligne (US-16).')]
class VenteController extends Controller
{
    /**
     * Vendre des places au guichet.
     *
     * L'agent choisit les places sur le plan de salle (GET /api/seances/{seance}/plan) et un
     * tarif pour chacune. Le client paie au comptoir: la réservation est confirmée tout de
     * suite, avec le canal guichet, et les places deviennent aussitôt occupées sur le site.
     * Si une place vient d'être prise en ligne, rien n'est vendu: la réponse 409 donne les
     * places prises dans places_prises. Une séance commencée ne se vend plus (409).
     */
    public function store(VenteGuichetRequest $request, Seance $seance, VenteAuGuichet $vente): JsonResponse
    {
        abort_unless($seance->loadMissing('film')->estVisibleDuPublic(), 404);

        $reservation = $vente->vendre($seance, $request->billets());

        return (new ReservationResource($reservation->load(Reservation::RELATIONS_AFFICHEES)))
            ->response()
            ->setStatusCode(201);
    }
}
