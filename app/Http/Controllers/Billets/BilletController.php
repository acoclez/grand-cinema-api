<?php

namespace App\Http\Controllers\Billets;

use App\Enums\StatutReservation;
use App\Http\Controllers\Controller;
use App\Http\Resources\BilletResource;
use App\Models\Billet;
use App\Services\Billets\BilletPdf;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Billets électroniques du spectateur connecté: un billet par place d'une réservation confirmée,
 * avec un QR code à présenter à l'entrée.
 */
#[Group('Billets', 'Billets des réservations confirmées: QR code et billet PDF (US-10).')]
class BilletController extends Controller
{
    /**
     * Lister mes billets.
     *
     * Billets des réservations confirmées dont la séance n'est pas terminée, de la plus proche à
     * la plus lointaine. Chaque billet porte son QR code (image SVG), qui ne contient que le jeton
     * aléatoire du billet.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $billets = $this->billetsDe($request)
            // Aucun film ne dure 24 heures: la fin exacte est vérifiée ensuite.
            ->whereHas('seance', fn (Builder $seance) => $seance->where('debut', '>', now()->subDay()))
            ->with(BilletPdf::RELATIONS)
            ->get()
            ->filter(fn (Billet $billet) => $billet->seance->fin()->isFuture())
            ->sortBy(['seance.debut', 'place.rangee', 'place.numero'])
            ->values();

        return BilletResource::collection($billets);
    }

    /**
     * Télécharger un billet en PDF.
     *
     * Fichier PDF du billet, avec son QR code. Le billet d'une autre personne, ou d'une
     * réservation qui n'est pas confirmée, répond 404.
     */
    public function pdf(Request $request, int $billet, BilletPdf $pdf): StreamedResponse
    {
        $trouve = $this->billetsDe($request)->findOrFail($billet);
        $contenu = $pdf->contenu($trouve);

        return response()->streamDownload(function () use ($contenu): void {
            echo $contenu;
        }, $pdf->nomDeFichier($trouve), ['Content-Type' => 'application/pdf']);
    }

    /**
     * Billets des réservations confirmées du compte connecté.
     *
     * @return Builder<Billet>
     */
    private function billetsDe(Request $request): Builder
    {
        return Billet::query()->whereHas('reservation', fn (Builder $reservation) => $reservation
            ->where('user_id', $request->user()?->getAuthIdentifier())
            ->where('statut', StatutReservation::Confirmee));
    }
}
