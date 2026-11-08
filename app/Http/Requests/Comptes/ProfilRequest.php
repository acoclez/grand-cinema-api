<?php

namespace App\Http\Requests\Comptes;

use App\Http\Requests\Concerns\NormaliseEmail;
use App\Support\Pays;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Coordonnées, pays de résidence et consentement aux e-mails du compte connecté.
 */
class ProfilRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user())],
            // Facultatif: null efface le pays enregistré.
            'pays_residence' => ['present', 'nullable', 'string', Rule::in(Pays::codes())],
            'consentement_emails' => ['required', 'boolean'],
        ];
    }
}
