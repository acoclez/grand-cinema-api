<?php

namespace App\Enums;

enum StatutReservation: string
{
    case EnAttente = 'en_attente';
    case Confirmee = 'confirmee';
    case Annulee = 'annulee';
    case Expiree = 'expiree';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
