<?php

namespace App\Http\Requests\Comptes;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Le spectateur confirme la suppression de son compte avec son mot de passe.
 */
class SuppressionCompteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password:api'],
        ];
    }
}
