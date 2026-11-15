<?php

namespace App\Http\Requests\Paiements;

use App\Exceptions\RegleMetierException;
use App\Models\Reservation;
use App\Services\Paiements\PaiementEnLigne;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tarifs choisis avant le paiement: un tarif de la séance pour chaque place de la réservation.
 */
class PaiementRequest extends FormRequest
{
    /**
     * Vérifié avant les données: la réservation d'un autre compte répond 404, comme une
     * réservation inexistante, et une réservation dont les places sont libérées répond 409.
     */
    public function authorize(): bool
    {
        $reservation = $this->reservation();
        abort_unless($reservation->appartientA($this->user()), 404);

        if (! $reservation->estBloquee()) {
            throw new RegleMetierException(PaiementEnLigne::DELAI_DEPASSE);
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $reservation = $this->reservation();
        $placeIds = $reservation->billets()->pluck('place_id')->all();

        return [
            // Un billet par place de la réservation, ni plus ni moins.
            'billets' => ['required', 'array', 'size:'.count($placeIds)],
            // Identifiant d'une place de la réservation.
            'billets.*.place_id' => ['required', 'integer', 'distinct', Rule::in($placeIds)],
            // Identifiant du tarif choisi pour cette place, parmi les tarifs de la séance.
            'billets.*.tarif_id' => ['required', 'integer', Rule::exists('tarifs', 'id')->where('seance_id', $reservation->seance_id)],
        ];
    }

    /**
     * @return array<int, int> Tarif choisi pour chaque place
     */
    public function tarifsParPlace(): array
    {
        $billets = (array) $this->validated('billets');

        return array_combine(
            array_map(fn (array $billet) => (int) $billet['place_id'], $billets),
            array_map(fn (array $billet) => (int) $billet['tarif_id'], $billets),
        );
    }

    private function reservation(): Reservation
    {
        $reservation = $this->route('reservation');
        assert($reservation instanceof Reservation);

        return $reservation;
    }
}
