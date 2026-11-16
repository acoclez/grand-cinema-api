<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Profil renvoyé par l'API, mot de passe non inclus.
 *
 * @mixin User
 */
class UtilisateurResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prenom' => $this->prenom,
            'nom' => $this->nom,
            'email' => $this->email,
            'role' => $this->role,
            'pays_residence' => $this->pays_residence,
            'consentement_emails' => $this->consentement_emails,
        ];
    }
}
