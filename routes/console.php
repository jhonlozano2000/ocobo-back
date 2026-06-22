<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pqrs:marcar-vencidas')->dailyAt('00:05');
Schedule::command('email:sync-radicados')->everyFiveMinutes()->withoutOverlapping();

// M16: Alertas de vencimiento (Ley 1437/2011)
// Notifica a responsables 1 y 3 días antes del vencimiento de radicados/PQRS
Schedule::command('alertas:vencimiento --dias=1,3')->dailyAt('07:00')->withoutOverlapping();

// M17: Depuración de archivos digitales huérfanos (AGN Acuerdo 003/2015, ISO 27001)
// Corre cada domingo a la 1am — modo cuarentena (nunca borra directamente)
Schedule::command('archivo:depurar-digital --disco=radicados_recibidos --dias=30')
    ->weekly()->sundays()->at('01:00')->withoutOverlapping();
Schedule::command('archivo:depurar-digital --disco=radicados_enviados --dias=30')
    ->weekly()->sundays()->at('01:30')->withoutOverlapping();
