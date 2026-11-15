<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Notification de paiement dont la signature ne correspond pas: elle ne vient pas du
 * prestataire, ou a été modifiée. Elle est refusée (400) sans rien changer.
 */
class NotificationDePaiementInvalideException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Notification de paiement invalide.'], 400);
    }
}
