<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve une route à certains rôles, par exemple role:administrateur
 * ou role:agent,administrateur. À placer après auth:api, qui répond 401
 * quand le jeton manque: le middleware répond 403 quand le rôle ne convient pas.
 */
class VerifierRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $utilisateur = $request->user();

        if (! $utilisateur instanceof User || ! in_array($utilisateur->role->value, $roles, true)) {
            return response()->json(['message' => 'Accès refusé: votre compte n\'a pas le rôle requis pour cette action.'], 403);
        }

        return $next($request);
    }
}
