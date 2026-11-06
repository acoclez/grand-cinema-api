<?php

namespace Database\Seeders;

use App\Models\Film;
use Illuminate\Database\Seeder;

/**
 * Programmation d'exemple
 */
class FilmSeeder extends Seeder
{
    public function run(): void
    {
        $films = [
            ['titre' => 'Les Nuits du Jazz', 'genre' => 'Documentaire', 'duree_minutes' => 95, 'classification' => 'Tous publics', 'langue_originale' => 'Français', 'synopsis' => 'Pendant une semaine, le festival transforme la ville. Un documentaire au plus près des musiciens, des bénévoles et des habitants.'],
            ['titre' => 'La Dernière Séance', 'genre' => 'Drame', 'duree_minutes' => 118, 'classification' => '-12', 'langue_originale' => 'Français', 'synopsis' => 'Un projectionniste à la retraite rouvre une salle abandonnée pour une unique projection, qui réveille les souvenirs de tout un quartier.'],
            ['titre' => 'Rue des Trompettes', 'genre' => 'Comédie', 'duree_minutes' => 102, 'classification' => 'Tous publics', 'langue_originale' => 'Français', 'synopsis' => 'Deux voisins que tout oppose doivent monter une fanfare en trois jours pour sauver la fête de leur rue.'],
            ['titre' => 'Signal perdu', 'genre' => 'Thriller', 'duree_minutes' => 131, 'classification' => '-16', 'langue_originale' => 'Anglais', 'synopsis' => 'Une ingénieure réseau découvre qu\'un signal inconnu se propage sur les antennes de la ville, et que quelqu\'un veut l\'en empêcher.'],
            ['titre' => 'Le Petit Orchestre', 'genre' => 'Animation', 'duree_minutes' => 84, 'classification' => 'Tous publics', 'langue_originale' => 'Français', 'synopsis' => 'Une souris chef d\'orchestre rassemble les animaux de la forêt pour un concert sous les étoiles.'],
        ];

        foreach ($films as $film) {
            // Relancer le seeder complète aussi les films déjà en base.
            Film::updateOrCreate(['titre' => $film['titre']], $film);
        }
    }
}
