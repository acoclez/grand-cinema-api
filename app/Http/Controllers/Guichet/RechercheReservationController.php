<?php

namespace App\Http\Controllers\Guichet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guichet\RechercheReservationRequest;
use App\Http\Resources\ReservationAuGuichetResource;
use App\Http\Resources\ReservationTrouveeResource;
use App\Models\Reservation;
use App\Models\Seance;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Recherche des réservations au guichet, pour renseigner un spectateur: en ligne comme au
 * guichet, quel que soit leur statut.
 */
#[Group('Guichet')]
class RechercheReservationController extends Controller
{
    /**
     * Rechercher des réservations.
     *
     * Par numéro (le début suffit, avec ou sans « R- »), par prénom, nom ou adresse e-mail du
     * client (chaque mot doit correspondre, par exemple « Camille Durand »), par séance, ou en
     * combinant le texte et la séance. Au moins un critère est obligatoire (422). Les plus
     * récentes séances d'abord, 20 par page.
     */
    public function index(RechercheReservationRequest $request): AnonymousResourceCollection
    {
        $reservations = Reservation::query()
            ->with(['user', 'seance.film', 'seance.salle', 'paiements'])
            ->withCount('billets')
            ->when($request->seanceId(), fn (Builder $requete, int $seance) => $requete->where('seance_id', $seance))
            ->when($request->termes(), fn (Builder $requete, array $termes) => $requete->where(fn (Builder $criteres) => $this->correspondre($criteres, $termes)))
            ->orderByDesc(Seance::query()->select('debut')->whereColumn('seances.id', 'reservations.seance_id'))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return ReservationTrouveeResource::collection($reservations);
    }

    /**
     * Afficher une réservation.
     *
     * Le client, la séance, les places avec leur tarif et leur prix, le paiement et l'état de
     * chaque billet (heure du passage en salle).
     */
    public function show(Reservation $reservation): ReservationAuGuichetResource
    {
        return new ReservationAuGuichetResource($reservation->load([...Reservation::RELATIONS_AFFICHEES, 'user']));
    }

    /**
     * Un seul mot peut être le début d'un numéro de réservation. Sinon, chaque mot doit se
     * retrouver dans le prénom, le nom ou l'adresse e-mail d'un client dont le compte existe encore.
     *
     * @param  Builder<Reservation>  $criteres
     * @param  list<string>  $termes
     */
    private function correspondre(Builder $criteres, array $termes): void
    {
        if (count($termes) === 1) {
            $numero = 'R-'.Str::upper(Str::chopStart($termes[0], ['R-', 'r-']));
            $criteres->orWhere('numero', 'like', $numero.'%');
        }

        $criteres->orWhereHas('user', function (Builder $client) use ($termes): void {
            $client->whereNull('anonymise_at');
            foreach ($termes as $terme) {
                $client->where(fn (Builder $champs) => $champs
                    ->where('prenom', 'like', '%'.$terme.'%')
                    ->orWhere('nom', 'like', '%'.$terme.'%')
                    ->orWhere('email', 'like', '%'.$terme.'%'));
            }
        });
    }
}
