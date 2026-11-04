<?php

namespace App\Enums;

enum StatutPaiement: string
{
    case EnAttente = 'en_attente';
    case Accepte = 'accepte';
    case Refuse = 'refuse';
    case Rembourse = 'rembourse';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
