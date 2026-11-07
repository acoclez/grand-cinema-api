<?php

namespace App\Http\Controllers\Programmation;

use App\Http\Controllers\Controller;
use App\Http\Resources\FilmAlAfficheResource;
use App\Models\Film;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Films à l'affiche, consultables par tous les visiteurs, sans compte.
 * Un film archivé par l'administrateur n'apparaît plus et sa fiche répond 404.
 */
#[Group('Programmation', 'Films à l’affiche et leur fiche, consultables sans compte (US-01).')]
class FilmAlAfficheController extends Controller
{
    /**
     * Lister les films à l'affiche.
     *
     * Par ordre alphabétique. Le nombre de films à l'affiche reste limité par la programmation
     * du cinéma: la liste est renvoyée en une fois, sans pagination.
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return FilmAlAfficheResource::collection(Film::query()->aLAffiche()->orderBy('titre')->get());
    }

    /**
     * Consulter la fiche d'un film.
     *
     * Erreur 404 si le film n'existe pas ou n'est plus à l'affiche.
     *
     * @unauthenticated
     *
     * @throws ModelNotFoundException<Film>
     */
    public function show(int $film): FilmAlAfficheResource
    {
        return new FilmAlAfficheResource(Film::query()->aLAffiche()->findOrFail($film));
    }
}
