<?php

namespace Database\Seeders;

use App\Enums\LangueSeance;
use App\Enums\StatutSeance;
use App\Models\Film;
use App\Models\Salle;
use App\Models\Seance;
use Illuminate\Database\Seeder;

/**
 * Séances d'exemple: trois séances par salle et par jour, pour les 7 jours à venir.
 * Seuls les jours sans séance sont programmés: relancé chaque jour sur l'environnement de
 * démonstration, le seeder garde donc toujours une semaine de séances à réserver.
 */
class SeanceSeeder extends Seeder
{
    /** Nombre de jours programmés à l'avance, aujourd'hui compris. */
    public const int JOURS = 7;

    private const array HORAIRES = ['14:00', '17:30', '20:30'];

    private const array TARIFS = [
        ['libelle' => 'Plein', 'prix_centimes' => 950],
        ['libelle' => 'Réduit', 'prix_centimes' => 700],
        ['libelle' => 'Enfant', 'prix_centimes' => 550],
    ];

    public function run(): void
    {
        $films = Film::all();
        $salles = Salle::orderBy('id')->get();

        if ($films->isEmpty() || $salles->isEmpty()) {
            return;
        }

        $compteur = 0;

        for ($jour = 0; $jour < self::JOURS; $jour++) {
            $date = today()->addDays($jour);

            if (Seance::whereDate('debut', $date)->exists()) {
                continue;
            }

            foreach ($salles as $salle) {
                foreach (self::HORAIRES as $horaire) {
                    $film = $films[$compteur % $films->count()];
                    $compteur++;

                    $seance = Seance::create([
                        'film_id' => $film->id,
                        'salle_id' => $salle->id,
                        'debut' => $date->copy()->setTimeFromTimeString($horaire),
                        'langue' => $compteur % 3 === 0 ? LangueSeance::Vo : LangueSeance::Vf,
                        'statut' => StatutSeance::Publiee,
                    ]);

                    $seance->tarifs()->createMany(self::TARIFS);
                }
            }
        }
    }
}
