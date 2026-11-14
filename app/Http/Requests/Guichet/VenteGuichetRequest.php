<?php

namespace App\Http\Requests\Guichet;

use App\Models\Seance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vente au guichet: une ou plusieurs places distinctes de la salle de la séance, chacune avec
 * un tarif de la séance.
 */
class VenteGuichetRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Le rôle est déjà vérifié par le middleware de la route.
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
            // Un billet par place vendue, au plus autant que de places dans la salle.
            'billets' => ['required', 'array', 'min:1', 'max:'.$seance->salle->capacite],
            // Identifiant de la place, lu sur le plan de salle.
            'billets.*.place_id' => ['required', 'integer', 'distinct', Rule::exists('places', 'id')->where('salle_id', $seance->salle_id)],
            // Identifiant du tarif choisi par le client, parmi les tarifs du plan de salle.
            'billets.*.tarif_id' => ['required', 'integer', Rule::exists('tarifs', 'id')->where('seance_id', $seance->id)],
        ];
    }

    /**
     * @return list<array{place_id: int, tarif_id: int}>
     */
    public function billets(): array
    {
        return array_values(array_map(fn (array $billet) => [
            'place_id' => (int) $billet['place_id'],
            'tarif_id' => (int) $billet['tarif_id'],
        ], (array) $this->validated('billets')));
    }
}
