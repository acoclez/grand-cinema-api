<?php

namespace App\Http\Requests\Comptes;

use App\Http\Requests\Concerns\NormaliseEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class InscriptionRequest extends FormRequest
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
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'consentement_emails' => ['sometimes', 'boolean'],
            'conditions_acceptees' => ['accepted'],
        ];
    }
}
