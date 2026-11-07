<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\FilmRequest;
use App\Http\Resources\FilmResource;
use App\Models\Film;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Gestion des films par l'administrateur, aucun film n'est supprimé:
 * l'archivage le retire du site public et le garde pour l'historique et les statistiques.
 */
#[Group('Films (administration)', 'Création, modification et archivage des films (US-12). Réservé à l’administrateur: un autre rôle reçoit une erreur 403.')]
class FilmController extends Controller
{
    /**
     * Lister les films.
     *
     * Par ordre alphabétique, 20 par page par défaut (par_page, jusqu'à 100). Les films archivés ne sont inclus qu'avec archives=1.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            // Inclure les films archivés.
            'archives' => ['sometimes', 'boolean'],
            // La liste de choix du formulaire de séance demande tous les films à l'affiche d'un coup.
            'par_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $films = Film::query()
            ->unless($request->boolean('archives'), fn ($requete) => $requete->aLAffiche())
            ->orderBy('titre')
            ->paginate($request->integer('par_page', 20));

        return FilmResource::collection($films);
    }

    /**
     * Créer un film.
     *
     * Le film est à l'affiche dès sa création et peut être programmé dans une séance.
     */
    public function store(FilmRequest $request): JsonResponse
    {
        $film = Film::create($request->validated());

        return (new FilmResource($film))->response()->setStatusCode(201);
    }

    /**
     * Consulter un film.
     */
    public function show(Film $film): FilmResource
    {
        return new FilmResource($film);
    }

    /**
     * Modifier un film.
     *
     * Tous les champs sont renvoyés, comme à la création.
     */
    public function update(FilmRequest $request, Film $film): FilmResource
    {
        $film->update($request->validated());

        return new FilmResource($film);
    }

    /**
     * Archiver un film.
     *
     * Le film disparaît du site public, mais reste en base avec ses séances passées.
     */
    public function archiver(Film $film): FilmResource
    {
        $film->archiver();

        return new FilmResource($film);
    }

    /**
     * Remettre un film à l'affiche.
     */
    public function restaurer(Film $film): FilmResource
    {
        $film->restaurer();

        return new FilmResource($film);
    }
}
