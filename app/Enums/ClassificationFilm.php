<?php

namespace App\Enums;

/**
 * Classification du film (visa d'exploitation) affichée sur sa fiche
 */
enum ClassificationFilm: string
{
    case TousPublics = 'Tous publics';
    case Moins12 = '-12';
    case Moins16 = '-16';
    case Moins18 = '-18';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
