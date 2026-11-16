<?php

use Database\Seeders\SeanceSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Les tâches tournent sur une seule instance de l'API à la fois: le verrou est pris dans le
// cache en base de données, partagé par toutes les instances.

// Les places d'une réservation non payée sont libérées 10 minutes après leur blocage.
Schedule::command('reservations:expirer')->everyMinute()->withoutOverlapping()->onOneServer();

// Les e-mails des billets passent par la file d'attente: elle est vidée chaque minute, sans
// processus permanent, et un envoi en échec est retenté (EnvoyerLesBillets).
Schedule::command('queue:work', ['--stop-when-empty', '--max-time=50'])->everyMinute()->withoutOverlapping()->onOneServer();

// Environnement de démonstration: les séances d'exemple restent programmées 7 jours à l'avance.
Schedule::command('db:seed', ['--class' => SeanceSeeder::class, '--force'])
    ->dailyAt('03:00')
    ->onOneServer()
    ->when(fn (): bool => Config::boolean('app.demonstration'));
