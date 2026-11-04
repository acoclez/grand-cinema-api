<?php

namespace App\Enums;

enum CanalVente: string
{
    case EnLigne = 'en_ligne';
    case Guichet = 'guichet';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
