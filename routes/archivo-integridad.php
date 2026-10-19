<?php

use App\Http\Controllers\OfiArchivo\OfiArchivoIntegridadController;
use Illuminate\Support\Facades\Route;

$perm = 'Gestion de Archivo -> Integridad -> ';

Route::middleware(['auth:sanctum', 'throttle:archivo'])->group(function () use ($perm) {
    // B21: incidentes de la verificación de huellas y las corridas que los detectaron
    Route::get('integridad/incidentes', [OfiArchivoIntegridadController::class, 'incidentes'])
        ->middleware('can:'.$perm.'Ver');
    Route::post('integridad/incidentes/{id}/resolver', [OfiArchivoIntegridadController::class, 'resolver'])->whereNumber('id')
        ->middleware('can:'.$perm.'Resolver');
    Route::get('integridad/verificaciones', [OfiArchivoIntegridadController::class, 'verificaciones'])
        ->middleware('can:'.$perm.'Ver');
    Route::post('integridad/expedientes/{id}/verificar', [OfiArchivoIntegridadController::class, 'verificarExpediente'])->whereNumber('id')
        ->middleware('can:'.$perm.'Resolver');
});
