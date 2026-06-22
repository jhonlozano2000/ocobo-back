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
    Route::get('transferencias', [OfiArchivoTransferenciaController::class, 'indexTransferencias']);
    Route::post('transferencias', [OfiArchivoTransferenciaController::class, 'storeTransferencia']);
    Route::post('transferencias/{id}/aprobar', [OfiArchivoTransferenciaController::class, 'aprobarTransferencia']);

    // Eliminaciones
    Route::get('eliminaciones', [OfiArchivoTransferenciaController::class, 'indexEliminaciones']);
    Route::post('eliminaciones', [OfiArchivoTransferenciaController::class, 'storeEliminacion']);

    // Estadísticas
    Route::get('transferencias-estadisticas', [OfiArchivoTransferenciaController::class, 'estadisticas']);
});
