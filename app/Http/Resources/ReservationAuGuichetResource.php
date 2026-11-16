<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Réservation vue par l'agent d'accueil: tout ce que voit le spectateur, plus le client.
 *
 * @mixin Reservation
 */
class ReservationAuGuichetResource extends ReservationResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), 'client' => self::client($this->user)];
    }

    /**
     * Client d'une réservation en ligne. Null pour une vente au guichet, sans compte, et pour un
     * compte supprimé: ses données personnelles ont été effacées.
     *
     * @return array{prenom: string, nom: string, email: string}|null
     */
    public static function client(?User $utilisateur): ?array
    {
        if ($utilisateur === null || $utilisateur->anonymise_at !== null) {
            return null;
        }

        return ['prenom' => $utilisateur->prenom, 'nom' => $utilisateur->nom, 'email' => $utilisateur->email];
    }
}
