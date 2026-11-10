<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

/**
 * Une ou plusieurs places demandées viennent d'être prises par un autre spectateur ou au
 * guichet. La réponse 409 donne leurs identifiants, pour que l'application les retire
 * de la sélection et rafraîchisse le plan.
 */
class PlacesDejaPrisesException extends RegleMetierException
{
    /**
     * @param  list<int>  $placeIds  Places prises entre-temps
     * @param  list<string>  $libelles  Leur libellé, par exemple B7
     */
    public function __construct(public readonly array $placeIds, array $libelles)
    {
        parent::__construct($libelles === []
            ? 'Une place de votre sélection vient d\'être prise: choisissez-en une autre.'
            : 'Ces places viennent d\'être prises: '.implode(', ', $libelles).'. Choisissez-en d\'autres.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'places_prises' => $this->placeIds], 409);
    }
}
