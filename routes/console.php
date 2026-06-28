<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Abgelaufene Reservierungen täglich nach der Aufbewahrungsfrist löschen (DSGVO).
Schedule::command('reservations:purge')->dailyAt('03:00');

// Inaktive Nutzerkonten (> 2 Jahre) wöchentlich löschen (DSGVO).
Schedule::command('accounts:purge')->weeklyOn(1, '03:30');
