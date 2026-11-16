<?php

namespace App\Http\Controllers\Comptes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comptes\MotDePasseOublieRequest;
use App\Http\Requests\Comptes\ReinitialisationMotDePasseRequest;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Mot de passe oublié, avec un lien de réinitialisation valable une heure.
 */
#[Group('Comptes', 'Création de compte, connexion, jeton d’accès et mot de passe oublié (US-05).')]
class MotDePasseController extends Controller
{
    /**
     * Demander un lien de réinitialisation.
     *
     * La réponse est la même, que l'adresse corresponde ou non à un compte,
     * pour ne pas révéler quelles adresses sont inscrites.
     *
     * @unauthenticated
     */
    public function oublie(MotDePasseOublieRequest $request): JsonResponse
    {
        Password::sendResetLink(['email' => $request->string('email')->toString()]);

        return response()->json([
            'message' => 'Si un compte existe pour cette adresse, un lien de réinitialisation valable une heure vient d\'être envoyé.',
        ]);
    }

    /**
     * Choisir un nouveau mot de passe.
     *
     * Le jeton et l'adresse viennent du lien reçu par e-mail.
     *
     * @unauthenticated
     *
     * @throws ValidationException
     */
    public function reinitialiser(ReinitialisationMotDePasseRequest $request): JsonResponse
    {
        $statut = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $utilisateur, string $motDePasse): void {
                $utilisateur->forceFill(['password' => $motDePasse])->save();
            },
        );

        if ($statut !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'Ce lien de réinitialisation est invalide ou a expiré.',
            ]);
        }

        return response()->json(['message' => 'Votre mot de passe a été modifié. Vous pouvez vous connecter.']);
    }
}
