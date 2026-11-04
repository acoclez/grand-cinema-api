<?php

namespace App\Enums;

enum RoleUtilisateur: string
{
    case Spectateur = 'spectateur';
    case Agent = 'agent';
    case Administrateur = 'administrateur';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
