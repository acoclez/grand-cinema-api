<?php

namespace App\Enums;

enum StatutSeance: string
{
    case Brouillon = 'brouillon';
    case Publiee = 'publiee';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
