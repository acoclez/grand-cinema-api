<?php

namespace App\Http\Requests\Administration;

use App\Enums\ClassificationFilm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Données d'un film, identiques à la création et à la modification.
 */
class FilmRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Le rôle administrateur est déjà vérifié par le middleware de la route.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'synopsis' => ['required', 'string', 'max:5000'],
            'duree_minutes' => ['required', 'integer', 'between:1,600'],
            'genre' => ['required', 'string', 'max:50'],
            'classification' => ['required', Rule::enum(ClassificationFilm::class)],
            'langue_originale' => ['required', 'string', 'max:50'],
            // Adresses d'images et de vidéos hébergées ailleurs, en https uniquement.
            'affiche_url' => ['nullable', 'url:https', 'max:255'],
            'bande_annonce_url' => ['nullable', 'url:https', 'max:255'],
        ];
    }
}
