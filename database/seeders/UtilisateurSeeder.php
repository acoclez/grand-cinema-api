<?php

namespace Database\Seeders;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Comptes de démonstration (un par rôle)
 */
class UtilisateurSeeder extends Seeder
{
    public function run(): void
    {
        $comptes = [
            ['prenom' => 'Admin', 'nom' => 'Grand Cinéma', 'email' => 'admin@grand-cinema.fr', 'role' => RoleUtilisateur::Administrateur, 'password' => 'Admin-GrandCinema-2026'],
            ['prenom' => 'Agent', 'nom' => 'Accueil', 'email' => 'agent@grand-cinema.fr', 'role' => RoleUtilisateur::Agent, 'password' => 'Agent-GrandCinema-2026'],
            ['prenom' => 'Camille', 'nom' => 'Spectateur', 'email' => 'spectateur@grand-cinema.fr', 'role' => RoleUtilisateur::Spectateur, 'password' => 'Spectateur-GrandCinema-2026'],
        ];

        foreach ($comptes as $compte) {
            User::firstOrCreate(
                ['email' => $compte['email']],
                [
                    'prenom' => $compte['prenom'],
                    'nom' => $compte['nom'],
                    'role' => $compte['role'],
                    'password' => $compte['password'],
                    'pays_residence' => 'FR',
                    'consentement_emails' => false,
                ],
            );
        }
    }
}
