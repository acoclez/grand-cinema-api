<?php

namespace App\Http\Controllers\Reservations;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanDeSalleResource;
use App\Models\Billet;
use App\Models\Seance;
use Dedoc\Scramble\Attributes\Group;

/**
 * Plan de salle d'une séance, consultable sans compte.
 */
#[Group('Réservation', 'Plan de salle, blocage des places pendant 10 minutes et suivi de la réservation en cours (US-08).')]
class PlanDeSalleController extends Controller
{
    /**
     * Afficher le plan de salle d'une séance.
     *
     * Toutes les places de la salle, libres ou occupées, et celles réservées aux personnes à
     * mobilité réduite. Une place est occupée quand elle est vendue ou bloquée par une
     * réservation en cours de paiement. L'application web relit le plan toutes les 5 secondes.
     * Une séance en brouillon ou dont le film est archivé n'a pas de plan public (404).
     *
     * @unauthenticated
     */
    public function show(Seance $seance): PlanDeSalleResource
    {
        $seance->load(['film', 'salle.places' => fn ($places) => $places->orderBy('rangee')->orderBy('numero')]);
        abort_unless($seance->estVisibleDuPublic(), 404);

        $occupees = Billet::query()
            ->where('seance_id', $seance->id)
            ->occupantSaPlace()
            ->pluck('place_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return new PlanDeSalleResource($seance, $occupees);
    }
}
