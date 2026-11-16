<?php

namespace App\Http\Requests\Administration;

use App\Enums\LangueSeance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Données d'une séance et de ses tarifs, identiques à la création et à la modification.
 */
class SeanceRequest extends FormRequest
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
            // Seul un film à l'affiche peut être programmé.
            'film_id' => ['required', 'integer', Rule::exists('films', 'id')->whereNull('archive_at')],
            'salle_id' => ['required', 'integer', Rule::exists('salles', 'id')],
            // Date et heure de Paris, par exemple 2026-11-20T20:30.
            'debut' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'langue' => ['required', Rule::enum(LangueSeance::class)],
            'tarifs' => ['required', 'array', 'min:1', 'max:6'],
            'tarifs.*.libelle' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            // Prix en centimes, de 0 (invitation) à 100 euros.
            'tarifs.*.prix_centimes' => ['required', 'integer', 'between:0,10000'],
        ];
    }
}
