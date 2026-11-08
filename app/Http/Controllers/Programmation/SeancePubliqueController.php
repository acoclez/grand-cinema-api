<?php

namespace App\Http\Controllers\Programmation;

use App\Http\Controllers\Controller;
use App\Http\Resources\SeancePubliqueResource;
use App\Models\Seance;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Séances publiées d'une date, consultables par tous les visiteurs, sans compte.
 * Une séance en brouillon, déjà commencée ou dont le film est archivé n'apparaît pas.
 */
#[Group('Programmation')]
class SeancePubliqueController extends Controller
{
    /**
     * Lister les séances d'une date.
     *
     * Séances publiées de la date choisie, aujourd'hui par défaut, triées par heure de début.
     * Chaque séance donne ses places restantes et signale si elle est bientôt complète
     * (au moins 90 % des places vendues) ou complète.
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            // Date choisie, aujourd'hui par défaut. Les dates passées ne sont plus proposées.
            'date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        $jour = Carbon::parse($request->string('date', today()->toDateString())->toString());

        $seances = Seance::query()
            ->publiee()
            ->whereHas('film', fn ($film) => $film->aLAffiche())
            ->with(['film', 'salle'])
            ->withCount('billets')
            // Une séance déjà commencée ne se réserve plus.
            ->whereBetween('debut', [max(now(), $jour->copy()->startOfDay()), $jour->copy()->endOfDay()])
            ->orderBy('debut')
            ->orderBy('salle_id')
            ->get();

        return SeancePubliqueResource::collection($seances);
    }
}
