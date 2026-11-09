<?php

namespace App\Http\Controllers\Reservations;

use App\Enums\CanalVente;
use App\Enums\StatutReservation;
use App\Exceptions\RegleMetierException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\BlocagePlacesRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\Seance;
use App\Models\User;
use App\Services\Reservations\BlocageDesPlaces;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Réservation en ligne d'un spectateur connecté: blocage des places, suivi et abandon.
 */
#[Group('Réservation')]
class ReservationController extends Controller
{
    /**
     * Bloquer des places.
     *
     * Bloque de 1 à 10 places libres de la séance pendant 10 minutes, le temps de payer:
     * elles apparaissent aussitôt occupées pour les autres spectateurs. Sans paiement dans ce
     * délai, la réservation expire et les places sont libérées. Une nouvelle sélection sur la
     * même séance remplace la précédente. Si une place vient d'être prise, rien n'est bloqué:
     * la réponse 409 donne les places prises dans places_prises.
     */
    public function store(BlocagePlacesRequest $request, Seance $seance, BlocageDesPlaces $blocage): JsonResponse
    {
        abort_unless($seance->loadMissing('film')->estVisibleDuPublic(), 404);

        $reservation = $blocage->bloquer($seance, $request->placeIds(), $this->spectateur($request), CanalVente::EnLigne);

        return (new ReservationResource($this->charger($reservation)))->response()->setStatusCode(201);
    }

    /**
     * Afficher une de ses réservations.
     *
     * Séance, places et secondes restantes avant la fin du blocage. La réservation d'un autre
     * compte répond 404, comme une réservation inexistante.
     */
    public function show(Request $request, Reservation $reservation): ReservationResource
    {
        $this->verifierProprietaire($request, $reservation);

        return new ReservationResource($this->charger($reservation));
    }

    /**
     * Abandonner une réservation en attente.
     *
     * Libère aussitôt les places bloquées. La réservation reste dans l'historique avec le
     * statut annulée. Une réservation confirmée ou expirée ne s'abandonne pas (409).
     */
    public function destroy(Request $request, Reservation $reservation): Response
    {
        $this->verifierProprietaire($request, $reservation);

        if (! $reservation->estBloquee()) {
            throw new RegleMetierException('Cette réservation n\'est plus en attente de paiement: elle ne peut pas être abandonnée.');
        }

        $reservation->liberer(StatutReservation::Annulee);

        return response()->noContent();
    }

    private function spectateur(Request $request): User
    {
        $utilisateur = $request->user();
        assert($utilisateur instanceof User);

        return $utilisateur;
    }

    private function verifierProprietaire(Request $request, Reservation $reservation): void
    {
        abort_unless($reservation->user_id === $this->spectateur($request)->id, 404);
    }

    private function charger(Reservation $reservation): Reservation
    {
        return $reservation->load(['seance.film', 'seance.salle', 'billets.place']);
    }
}
