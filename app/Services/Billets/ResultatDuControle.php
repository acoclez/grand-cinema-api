<?php

namespace App\Services\Billets;

/** Résultat du contrôle d'un billet à l'entrée de la salle. */
enum ResultatDuControle: string
{
    /** Billet payé pour cette séance, pas encore utilisé: le spectateur entre. */
    case Valide = 'valide';

    /** Billet déjà présenté: il a servi une première fois, à l'heure indiquée. */
    case DejaUtilise = 'deja_utilise';

    /** QR code inconnu, billet non payé ou billet d'une autre séance. */
    case Invalide = 'invalide';
}
