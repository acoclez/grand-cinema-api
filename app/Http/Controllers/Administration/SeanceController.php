<?php

namespace App\Http\Controllers\Administration;

use App\Enums\StatutSeance;
use App\Exceptions\RegleMetierException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\SeanceRequest;
use App\Http\Resources\SeanceResource;
use App\Models\Seance;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Programmation des séances par l'administrateur. Une séance est créée en brouillon,
 * puis publiée sur le site. Une séance dont des places sont vendues ne change plus.
 */
#[Group('Salles et séances (administration)')]
class SeanceController extends Controller
{
    /**
     * Lister les séances d'un jour.
     * Toutes les salles par défaut, ou une seule avec salle_id. Les séances sont triées par heure de début.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            // Jour affiché, aujourd'hui par défaut.
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'salle_id' => ['sometimes', 'integer', 'exists:salles,id'],
        ]);

        $jour = Carbon::parse($request->string('date', today()->toDateString())->toString());

        $seances = Seance::query()
            ->with(['film', 'salle', 'tarifs'])
            ->withCount('billets')
            ->whereBetween('debut', [$jour->copy()->startOfDay(), $jour->copy()->endOfDay()])
            ->when($request->filled('salle_id'), fn ($requete) => $requete->where('salle_id', $request->integer('salle_id')))
            ->orderBy('debut')
            ->orderBy('salle_id')
            ->get();

        return SeanceResource::collection($seances);
    }

    /**
     * Programmer une séance.
     * La séance est enregistrée en brouillon avec ses tarifs: elle n'apparaît pas encore sur le site.
     */
    public function store(SeanceRequest $request): JsonResponse
    {
        $seance = DB::transaction(function () use ($request): Seance {
            $seance = Seance::create([
                ...$request->safe()->except('tarifs'),
                'statut' => StatutSeance::Brouillon,
            ]);
            $seance->tarifs()->createMany($request->validated('tarifs'));

            return $seance;
        });

        return (new SeanceResource($this->charger($seance)))->response()->setStatusCode(201);
    }

    /**
     * Consulter une séance.
     */
    public function show(Seance $seance): SeanceResource
    {
        return new SeanceResource($this->charger($seance));
    }

    /**
     * Modifier une séance.
     * Les tarifs envoyés remplacent les précédents.
     * Refusé (409) si des places sont déjà vendues.
     *
     * @throws RegleMetierException
     */
    public function update(SeanceRequest $request, Seance $seance): SeanceResource
    {
        $seance->verifierModifiable();

        DB::transaction(function () use ($request, $seance): void {
            $seance->update($request->safe()->except('tarifs'));
            $seance->tarifs()->delete();
            $seance->tarifs()->createMany($request->validated('tarifs'));
        });

        return new SeanceResource($this->charger($seance));
    }

    /**
     * Supprimer une séance en brouillon.
     * Refusé (409) pour une séance publiée, qui doit d'abord être dépubliée.
     *
     * @throws RegleMetierException
     */
    public function destroy(Seance $seance): Response
    {
        $seance->supprimer();

        return response()->noContent();
    }

    /**
     * Publier une séance.
     *
     * La séance apparaît sur le site public. Refusé (409) si son film est archivé ou si elle a déjà commencé.
     *
     * @throws RegleMetierException
     */
    public function publier(Seance $seance): SeanceResource
    {
        $seance->publier();

        return new SeanceResource($this->charger($seance));
    }

    /**
     * Dépublier une séance.
     * La séance repasse en brouillon.
     * Refusé (409) si des places sont déjà vendues.
     *
     * @throws RegleMetierException
     */
    public function depublier(Seance $seance): SeanceResource
    {
        $seance->depublier();

        return new SeanceResource($this->charger($seance));
    }

    private function charger(Seance $seance): Seance
    {
        return $seance->load(['film', 'salle', 'tarifs'])->loadCount('billets');
    }
}
