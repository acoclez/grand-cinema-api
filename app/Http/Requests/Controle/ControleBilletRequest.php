<?php

namespace App\Http\Requests\Controle;

use Illuminate\Foundation\Http\FormRequest;

class ControleBilletRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Contenu du QR code scanné: le jeton du billet.
            'code' => ['required', 'string', 'max:255'],
        ];
    }

    public function code(): string
    {
        return $this->string('code')->trim()->toString();
    }
}
