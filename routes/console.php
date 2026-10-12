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

// Las horas se interpretan en app.schedule_timezone (America/Bogota por defecto, M13-01)
// M18-03: onOneServer() evita que cada nodo ejecute la tarea (alertas y correos duplicados); con
// varios servidores el cache por defecto debe ser compartido (redis/database), no 'file'.
Schedule::command('pqrs:marcar-vencidas')->dailyAt('00:05')->withoutOverlapping()->onOneServer();
// M18-06: no hay sincronización programada del buzón; la Bandeja de Correo lee IMAP en vivo y la radicación
// es manual. Se retiró 'email:sync-radicados' (comando inexistente que fallaba cada 5 minutos).

// M16: Alertas de vencimiento (Ley 1437/2011)
// Notifica a responsables 1 y 3 días antes del vencimiento de radicados/PQRS
Schedule::command('alertas:vencimiento --dias=1,3')->dailyAt('07:00')->withoutOverlapping()->onOneServer();

// M17: Depuración de archivos digitales huérfanos (AGN Acuerdo 003/2015, ISO 27001)
// Corre cada domingo a la 1am. M08-01: solo reporta (--dry-run) hasta conciliar los adjuntos
// de correo con archivo:conciliar-adjuntos-correo y definir acta de eliminación (Acuerdo 004/2019).
Schedule::command('archivo:depurar-digital --disco=radicados_recibidos --dias=30 --dry-run')
    ->weekly()->sundays()->at('01:00')->withoutOverlapping()->onOneServer();
Schedule::command('archivo:depurar-digital --disco=radicados_enviados --dias=30 --dry-run')
    ->weekly()->sundays()->at('01:30')->withoutOverlapping()->onOneServer();
// M08-02: radicados internos y expedientes también se reportan; la purga de cuarentena es manual (--purgar-dias)
Schedule::command('archivo:depurar-digital --disco=radicados_internos --dias=30 --dry-run')
    ->weekly()->sundays()->at('02:00')->withoutOverlapping()->onOneServer();
Schedule::command('archivo:depurar-digital --disco=archivo_expedientes --dias=30 --dry-run')
    ->weekly()->sundays()->at('02:30')->withoutOverlapping()->onOneServer();

// Grupos Colaborativos: liberar bloqueos expirados cada hora
Schedule::command('grupos-colaborativos:liberar-bloqueos')->hourly()->withoutOverlapping()->onOneServer();

// M14: Reportes programados — revisa cada 5 minutos si hay pendientes
Schedule::command('reportes:generar-programados')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// M16b: Alertas de retención TRD (Decreto 620/2015, ISO 15489)
// Notifica a responsables cuando fecha_retencion_fin está próxima a vencer (30, 7 y 1 día)
Schedule::command('alertas:retencion-trd --dias=30,7,1')->dailyAt('06:30')->withoutOverlapping()->onOneServer();
