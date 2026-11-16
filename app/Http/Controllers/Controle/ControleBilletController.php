<?php

namespace App\Http\Controllers\Controle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Controle\ControleBilletRequest;
use App\Http\Resources\ControleBilletResource;
use App\Models\Seance;
use App\Services\Billets\ControleALEntree;
use Dedoc\Scramble\Attributes\Group;

/**
 * Contrôle des billets à l'entrée d'une séance, par un agent d'accueil ou l'administrateur.
 */
#[Group('Contrôle', 'Contrôle des billets à l\'entrée de la salle, par le QR code (US-11).')]
class ControleBilletController extends Controller
{
    /**
     * Contrôler un billet.
     *
     * Le code est le contenu du QR code scanné. Le résultat est valide (le billet est aussitôt
     * marqué utilisé), deja_utilise (avec l'heure du premier passage) ou invalide (QR code
     * inconnu, billet non payé ou billet d'une autre séance). Le contrôle n'est ouvert que le
     * jour de la séance (409).
     */
    public function store(ControleBilletRequest $request, Seance $seance, ControleALEntree $controle): ControleBilletResource
    {
        abort_unless($seance->loadMissing('film')->estVisibleDuPublic(), 404);

        return new ControleBilletResource($controle->controler($seance, $request->code()));
    }
}
