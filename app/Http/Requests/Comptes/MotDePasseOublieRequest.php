<?php

namespace App\Http\Requests\Comptes;

use App\Http\Requests\Concerns\NormaliseEmail;
use Illuminate\Foundation\Http\FormRequest;

class MotDePasseOublieRequest extends FormRequest
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
        ];
    }
}
