<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Les places d'une réservation non payée sont libérées 10 minutes après leur blocage.
Schedule::command('reservations:expirer')->everyMinute()->withoutOverlapping();
