<?php

namespace App\Enums;

enum TypeSalle: string
{
    case PremiumImax = 'premium_imax';
    case Standard = 'standard';
    case Vip = 'vip';
    case Evenementielle = 'evenementielle';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
