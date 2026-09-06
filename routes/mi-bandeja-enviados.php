<?php

use App\Http\Controllers\MiBandeja\Enviados\MiBandejaEnviadosController;
use Illuminate\Support\Facades\Route;

$permMiBandeja = 'Mi Bandeja - Enviados -> ';

Route::middleware('auth:sanctum')->group(function () use ($permMiBandeja) {
    Route::get('/mis-radicados', [MiBandejaEnviadosController::class, 'misRadicados'])
        ->name('mi-bandeja.enviados.mis-radicados')
        ->middleware('can:'.$permMiBandeja.'Ver');

    Route::get('/mis-radicados/estadisticas', [MiBandejaEnviadosController::class, 'estadisticas'])
        ->name('mi-bandeja.enviados.estadisticas')
        ->middleware('can:'.$permMiBandeja.'Ver');
});
