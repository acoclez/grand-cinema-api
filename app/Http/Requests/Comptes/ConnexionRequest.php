<?php

namespace App\Http\Requests\Comptes;

use App\Http\Requests\Concerns\NormaliseEmail;
use Illuminate\Foundation\Http\FormRequest;

class ConnexionRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
