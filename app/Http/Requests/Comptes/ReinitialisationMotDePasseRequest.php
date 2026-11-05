<?php

namespace App\Http\Requests\Comptes;

use App\Http\Requests\Concerns\NormaliseEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ReinitialisationMotDePasseRequest extends FormRequest
{
    use NormaliseEmail;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Jeton et adresse reçus dans le lien de l’e-mail.
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            // Au moins 12 caractères, à saisir deux fois (champ password_confirmation).
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
