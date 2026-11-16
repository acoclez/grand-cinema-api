<?php

namespace App\Http\Controllers\Comptes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comptes\ProfilRequest;
use App\Http\Requests\Comptes\SuppressionCompteRequest;
use App\Http\Resources\UtilisateurResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Données personnelles du compte connecté: rectification et droit à l'effacement (RGPD).
 */
#[Group('Comptes')]
class ProfilController extends Controller
{
    /**
     * Modifier ses informations.
     *
     * Coordonnées, pays de résidence (facultatif, null pour l'effacer) et consentement
     * aux e-mails promotionnels, modifiable à tout moment.
     */
    public function update(ProfilRequest $request): UtilisateurResource
    {
        $utilisateur = $this->utilisateur($request);
        $utilisateur->update($request->validated());

        return new UtilisateurResource($utilisateur);
    }

    /**
     * Supprimer son compte.
     *
     * Réservé aux spectateurs (un compte du personnel reçoit une erreur 403), confirmé par
     * le mot de passe. Les données personnelles sont effacées et le compte ne peut plus se
     * connecter. Les réservations passées restent pour les statistiques, sans lien avec une
     * personne identifiable.
     */
    public function destroy(SuppressionCompteRequest $request): Response
    {
        $this->utilisateur($request)->anonymiser();
        Auth::guard('api')->logout();

        return response()->noContent();
    }

    private function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        assert($utilisateur instanceof User);

        return $utilisateur;
    }
}
