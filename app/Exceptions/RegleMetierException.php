<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Action refusée par une règle métier, par exemple dépublier une séance dont des places
 * sont vendues. L'API répond 409 (conflit avec l'état actuel) et le message s'affiche tel quel.
 *
 * Hériter de l'exception 409 de Symfony fait apparaître cette réponse dans la documentation OpenAPI.
 * render() écrit la réponse elle-même: aucune trace technique, même en mode debug.
 */
class RegleMetierException extends ConflictHttpException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
