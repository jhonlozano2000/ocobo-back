<?php

use App\Http\Controllers\OfiArchivo\OfiArchivoTransferenciaController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas de Transferencia y Eliminación Documental.
 *
 * Prefijo base: api/archivo/transferencias
 * Acuerdo AGN 004/2019.
 */
Route::middleware('auth:sanctum')->group(function () {
    // Transferencias
    Route::get('transferencias', [OfiArchivoTransferenciaController::class, 'indexTransferencias'])
        ->middleware('can:Gestion de Archivo -> Transferencias -> Ver');
    Route::post('transferencias', [OfiArchivoTransferenciaController::class, 'storeTransferencia'])
        ->middleware('can:Gestion de Archivo -> Transferencias -> Crear');
    Route::post('transferencias/{id}/aprobar', [OfiArchivoTransferenciaController::class, 'aprobarTransferencia'])
        ->middleware('can:Gestion de Archivo -> Transferencias -> Aprobar');

    // Eliminaciones
    Route::get('eliminaciones', [OfiArchivoTransferenciaController::class, 'indexEliminaciones'])
        ->middleware('can:Gestion de Archivo -> Eliminaciones -> Ver');
    Route::post('eliminaciones', [OfiArchivoTransferenciaController::class, 'storeEliminacion'])
        ->middleware('can:Gestion de Archivo -> Eliminaciones -> Crear');

    // Estadísticas
    Route::get('transferencias-estadisticas', [OfiArchivoTransferenciaController::class, 'estadisticas'])
        ->middleware('can:Gestion de Archivo -> Transferencias -> Ver');
});
