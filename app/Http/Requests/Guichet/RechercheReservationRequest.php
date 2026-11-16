<?php

namespace App\Http\Requests\Guichet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Recherche d'une réservation au guichet: un numéro, un nom ou une adresse e-mail, une séance,
 * ou les deux. Sans critère, rien n'est listé: les réservations des clients ne s'affichent pas
 * en bloc.
 */
class RechercheReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Le rôle est déjà vérifié par le middleware de la route.
        return true;
    }

    /**
     * Le texte est nettoyé avant la validation: sans le caractère %, qui ferait correspondre
     * toutes les réservations, ni espaces superflus. Un texte vide n'est pas un critère.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('recherche')) {
            $texte = Str::of((string) $this->input('recherche'))->replace('%', ' ')->squish()->toString();
            $this->merge(['recherche' => $texte === '' ? null : $texte]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Numéro de réservation (avec ou sans « R- »), prénom, nom ou adresse e-mail du client.
            'recherche' => ['required_without:seance', 'nullable', 'string', 'min:2', 'max:100'],
            // Identifiant de la séance.
            'seance' => ['required_without:recherche', 'nullable', 'integer', 'exists:seances,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $consigne = 'Saisissez un numéro de réservation, un nom ou une adresse e-mail, ou choisissez une séance.';

        return ['recherche.required_without' => $consigne, 'seance.required_without' => $consigne];
    }

    /**
     * Mots recherchés, déjà nettoyés.
     *
     * @return list<string>
     */
    public function termes(): array
    {
        $texte = $this->string('recherche')->toString();

        return $texte === '' ? [] : explode(' ', $texte);
    }

    public function seanceId(): ?int
    {
        return $this->filled('seance') ? $this->integer('seance') : null;
    }
}
