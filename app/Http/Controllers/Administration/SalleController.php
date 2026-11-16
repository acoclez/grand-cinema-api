<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalleResource;
use App\Models\Salle;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Les 10 salles et leur plan de places sont créés par SalleSeeder: ce sont des données de
 * référence, qui reflètent les salles physiques du cinéma. Le back-office les consulte.
 */
#[Group('Salles et séances (administration)', 'Consultation des salles, programmation et publication des séances (US-13). Réservé à l’administrateur: un autre rôle reçoit une erreur 403.')]
class SalleController extends Controller
{
    /**
     * Lister les salles avec:
     * leur type, leur capacité, le nombre de places de leur plan et le total des places du cinéma.
     */
    public function index(): AnonymousResourceCollection
    {
        $salles = Salle::query()
            ->withCount(['places', 'places as places_pmr_count' => fn (Builder $places) => $places->where('pmr', true)])
            ->orderBy('id')
            ->get();

        return SalleResource::collection($salles)->additional([
            'meta' => ['total_places' => (int) $salles->sum('places_count')],
        ]);
    }
}
