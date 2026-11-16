<?php

namespace App\Enums;

enum LangueSeance: string
{
    case Vo = 'vo';
    case Vf = 'vf';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
