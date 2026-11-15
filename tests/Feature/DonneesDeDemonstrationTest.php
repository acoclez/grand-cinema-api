<?php

namespace Tests\Feature;

use App\Models\Film;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SeanceSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Données de l'environnement de démonstration: relancées à chaque déploiement et chaque nuit,
 * elles ne se dupliquent pas et gardent une semaine de séances à réserver.
 */
class DonneesDeDemonstrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Les tests se déroulent le 10 novembre 2026 à 10 h, heure de Paris.
        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'Europe/Paris'));
    }

    public function test_relancer_les_donnees_ne_cree_aucun_doublon(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, User::count());
        $this->assertSame(10 * 3 * SeanceSeeder::JOURS, Seance::count());
        $this->assertSame(Seance::count() * 3, Seance::withCount('tarifs')->get()->sum('tarifs_count'));
        $this->assertSame('2026-11-10 14:00', Seance::orderBy('debut')->value('debut')?->format('Y-m-d H:i'));
        $this->assertSame('2026-11-16 20:30', Seance::orderByDesc('debut')->value('debut')?->format('Y-m-d H:i'));
    }

    public function test_les_jours_sans_seance_sont_programmes_pour_garder_une_semaine_d_avance(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->travel(2)->days();

        $this->seed(SeanceSeeder::class);

        $this->assertSame('2026-11-18 20:30', Seance::orderByDesc('debut')->value('debut')?->format('Y-m-d H:i'));
        $this->assertSame(10 * 3 * (SeanceSeeder::JOURS + 2), Seance::count());
        $this->assertTrue(Film::exists());
    }

    public function test_les_seances_sont_reprogrammees_chaque_nuit_seulement_en_demonstration(): void
    {
        $reprogrammation = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains($event->command ?? '', 'db:seed'));

        $this->assertNotNull($reprogrammation);
        $this->assertSame('0 3 * * *', $reprogrammation->expression);
        $this->assertStringEndsWith('--force', (string) $reprogrammation->command);
        $this->assertTrue($reprogrammation->onOneServer);

        config(['app.demonstration' => false]);
        $this->assertFalse($reprogrammation->filtersPass($this->app));

        config(['app.demonstration' => true]);
        $this->assertTrue($reprogrammation->filtersPass($this->app));
    }
}
