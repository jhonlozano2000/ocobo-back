<?php

use App\Http\Controllers\OfiArchivo\OfiArchivoTransferenciaController;
use App\Http\Controllers\OfiArchivo\OfiArchivoTransferenciaLoteController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas de Transferencia y Eliminación Documental.
 *
 * Prefijo base: api/archivo/transferencias
 * Acuerdo AGN 004/2019.
 */
Route::middleware(['auth:sanctum', 'throttle:archivo'])->group(function () {
    // Transferencias
    Route::get('transferencias', [OfiArchivoTransferenciaController::class, 'indexTransferencias'])
        ->middleware('can:Gestion de Archivo -> Transferencias -> Ver');
    Route::post('transferencias', [OfiArchivoTransferenciaController::class, 'storeTransferencia'])
        ->middleware('can:Gestion de Archivo -> Transferencias -> Crear');
    Route::post('transferencias/{id}/aprobar', [OfiArchivoTransferenciaController::class, 'aprobarTransferencia'])
        ->middleware('can:Gestion de Archivo -> Transferencias -> Aprobar');

    // B17: lotes de transferencia (FUID, cotejo y acta de entrega)
    $perm = 'can:Gestion de Archivo -> Transferencias -> ';
    Route::get('transferencia-lotes', [OfiArchivoTransferenciaLoteController::class, 'index'])->middleware($perm.'Ver');
    Route::get('transferencia-lotes/candidatos', [OfiArchivoTransferenciaLoteController::class, 'candidatos'])->middleware($perm.'Crear');
    Route::post('transferencia-lotes', [OfiArchivoTransferenciaLoteController::class, 'store'])->middleware($perm.'Crear');
    Route::get('transferencia-lotes/{id}', [OfiArchivoTransferenciaLoteController::class, 'show'])->whereNumber('id')->middleware($perm.'Ver');
    Route::post('transferencia-lotes/{id}/recibir', [OfiArchivoTransferenciaLoteController::class, 'recibir'])->whereNumber('id')->middleware($perm.'Recibir');
    Route::get('transferencia-lotes/{id}/fuid', [OfiArchivoTransferenciaLoteController::class, 'fuid'])->whereNumber('id')->middleware($perm.'Ver');
    Route::get('transferencia-lotes/{id}/acta', [OfiArchivoTransferenciaLoteController::class, 'acta'])->whereNumber('id')->middleware($perm.'Ver');
    Route::get('transferencia-lotes/{id}/paquete', [OfiArchivoTransferenciaLoteController::class, 'paquete'])->whereNumber('id')->middleware($perm.'Ver');

    // Eliminaciones
    Route::get('eliminaciones', [OfiArchivoTransferenciaController::class, 'indexEliminaciones'])
        ->middleware('can:Gestion de Archivo -> Eliminaciones -> Ver');
    Route::post('eliminaciones', [OfiArchivoTransferenciaController::class, 'storeEliminacion'])
        ->middleware('can:Gestion de Archivo -> Eliminaciones -> Crear');

    // Estadísticas
    Route::get('transferencias-estadisticas', [OfiArchivoTransferenciaController::class, 'estadisticas'])
        ->middleware('can:Gestion de Archivo -> Transferencias -> Ver');
});
