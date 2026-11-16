<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données initiales pour le test de l'application
     */
    public function run(): void
    {
        $this->call([
            SalleSeeder::class,
            UtilisateurSeeder::class,
            FilmSeeder::class,
            SeanceSeeder::class,
        ]);
    }
}
