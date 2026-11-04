<?php

namespace Database\Seeders;

use App\Enums\TypeSalle;
use App\Models\Place;
use App\Models\Salle;
use Illuminate\Database\Seeder;

class SalleSeeder extends Seeder
{
    /**
     * Nombre de places réservées aux personnes à mobilité réduite au début de la première rangée.
     */
    private const int PLACES_PMR_PAR_SALLE = 4;

    /**
     * @var list<array{nom: string, type: TypeSalle, rangees: int, places_par_rangee: int}>
     */
    private const array SALLES = [
        ['nom' => 'Salle 1', 'type' => TypeSalle::PremiumImax, 'rangees' => 15, 'places_par_rangee' => 20],
        ['nom' => 'Salle 2', 'type' => TypeSalle::PremiumImax, 'rangees' => 15, 'places_par_rangee' => 20],
        ['nom' => 'Salle 3', 'type' => TypeSalle::Standard, 'rangees' => 10, 'places_par_rangee' => 18],
        ['nom' => 'Salle 4', 'type' => TypeSalle::Standard, 'rangees' => 10, 'places_par_rangee' => 18],
        ['nom' => 'Salle 5', 'type' => TypeSalle::Standard, 'rangees' => 10, 'places_par_rangee' => 18],
        ['nom' => 'Salle 6', 'type' => TypeSalle::Standard, 'rangees' => 10, 'places_par_rangee' => 18],
        ['nom' => 'Salle 7', 'type' => TypeSalle::Vip, 'rangees' => 8, 'places_par_rangee' => 15],
        ['nom' => 'Salle 8', 'type' => TypeSalle::Vip, 'rangees' => 8, 'places_par_rangee' => 15],
        ['nom' => 'Salle 9', 'type' => TypeSalle::Evenementielle, 'rangees' => 5, 'places_par_rangee' => 14],
        ['nom' => 'Salle 10', 'type' => TypeSalle::Evenementielle, 'rangees' => 5, 'places_par_rangee' => 14],
    ];

    public function run(): void
    {
        foreach (self::SALLES as $definition) {
            $salle = Salle::firstOrCreate(
                ['nom' => $definition['nom']],
                ['type' => $definition['type'], 'capacite' => $definition['rangees'] * $definition['places_par_rangee']],
            );

            if ($salle->places()->exists()) {
                continue;
            }

            $places = [];
            $maintenant = now();

            for ($r = 0; $r < $definition['rangees']; $r++) {
                $rangee = chr(ord('A') + $r);

                for ($numero = 1; $numero <= $definition['places_par_rangee']; $numero++) {
                    $places[] = [
                        'salle_id' => $salle->id,
                        'rangee' => $rangee,
                        'numero' => $numero,
                        'pmr' => $r === 0 && $numero <= self::PLACES_PMR_PAR_SALLE,
                        'created_at' => $maintenant,
                        'updated_at' => $maintenant,
                    ];
                }
            }

            Place::insert($places);
        }
    }
}
