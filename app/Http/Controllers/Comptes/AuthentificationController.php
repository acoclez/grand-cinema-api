<?php

namespace App\Http\Controllers\Comptes;

use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comptes\ConnexionRequest;
use App\Http\Requests\Comptes\InscriptionRequest;
use App\Http\Resources\UtilisateurResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

/**
 * Création de compte, connexion et gestion du jeton d'accès (JWT).
 */
#[Group('Comptes', 'Création de compte, connexion, jeton d’accès et mot de passe oublié (US-05). Modification des informations et suppression du compte (US-06).')]
class AuthentificationController extends Controller
{
    /**
     * Créer un compte spectateur.
     *
     * Le compte reçoit le rôle spectateur. La réponse contient un jeton d'accès:
     * le spectateur est connecté dès son inscription.
     *
     * @unauthenticated
     */
    public function inscription(InscriptionRequest $request): JsonResponse
    {
        $utilisateur = User::create([
            'prenom' => $request->string('prenom')->toString(),
            'nom' => $request->string('nom')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'role' => RoleUtilisateur::Spectateur,
            'consentement_emails' => $request->boolean('consentement_emails'),
        ]);

        return $this->reponseAvecJeton($this->garde()->login($utilisateur), $utilisateur, 201);
    }

    /**
     * Se connecter.
     *
     * En cas d'échec, le message ne précise pas si l'adresse ou le mot de passe est faux,
     * pour ne pas révéler quelles adresses ont un compte.
     *
     * @unauthenticated
     */
    public function connexion(ConnexionRequest $request): JsonResponse
    {
        $jeton = $this->garde()->attempt($request->validated());

        if (! is_string($jeton)) {
            return response()->json(['message' => 'Adresse e-mail ou mot de passe incorrect.'], 401);
        }

        return $this->reponseAvecJeton($jeton, $this->garde()->user());
    }

    /**
     * Profil du compte connecté.
     */
    public function moi(Request $request): UtilisateurResource
    {
        return new UtilisateurResource($request->user());
    }

    /**
     * Renouveler le jeton.
     *
     * L'ancien jeton est invalidé et un nouveau jeton est renvoyé.
     */
    public function rafraichir(Request $request): JsonResponse
    {
        return $this->reponseAvecJeton($this->garde()->refresh(), $request->user());
    }

    /**
     * Se déconnecter.
     *
     * Le jeton est invalidé: il ne permet plus d'accéder aux routes protégées.
     */
    public function deconnexion(): Response
    {
        $this->garde()->logout();

        return response()->noContent();
    }

    /**
     * Réponse commune à l'inscription, à la connexion et au renouvellement du jeton.
     */
    private function reponseAvecJeton(string $jeton, mixed $utilisateur, int $statut = 200): JsonResponse
    {
        return response()->json([
            'access_token' => $jeton,
            'token_type' => 'bearer',
            'expires_in' => (int) $this->garde()->getTTL() * 60,
            'utilisateur' => new UtilisateurResource($utilisateur),
        ], $statut);
    }

    /**
     * Garde d'authentification par jeton JWT, déclarée sous le nom "api" dans config/auth.php.
     */
    private function garde(): JWTGuard
    {
        $garde = Auth::guard('api');
        assert($garde instanceof JWTGuard);

        return $garde;
    }
}
