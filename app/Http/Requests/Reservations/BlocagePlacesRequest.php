<?php

namespace App\Http\Requests\Reservations;

use App\Models\Reservation;
use App\Models\Seance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Places choisies sur le plan de salle: de 1 à 10 places distinctes de la salle de la séance.
 */
class BlocagePlacesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Le rôle spectateur est déjà vérifié par le middleware de la route.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $seance = $this->route('seance');
        assert($seance instanceof Seance);

        return [
            // Identifiants des places, lus sur le plan de salle.
            'places' => ['required', 'array', 'min:1', 'max:'.Reservation::PLACES_MAX],
            'places.*' => ['integer', 'distinct', Rule::exists('places', 'id')->where('salle_id', $seance->salle_id)],
        ];
    }

    /**
     * @return list<int>
     */
    public function placeIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->validated('places')));
    }
}
