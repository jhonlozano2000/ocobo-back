<?php

use App\Http\Controllers\OfiArchivo\OfiArchivoEliminacionLoteController;
use Illuminate\Support\Facades\Route;

$perm = 'Gestion de Archivo -> Eliminaciones -> ';

Route::middleware(['auth:sanctum', 'throttle:archivo'])->group(function () use ($perm) {
    // B18: eliminación documental por lote (serie completa, publicación, comité, acta)
    Route::get('eliminaciones/lotes/pendientes', [OfiArchivoEliminacionLoteController::class, 'pendientes'])
        ->middleware('can:'.$perm.'Ver');
    Route::get('eliminaciones/lotes', [OfiArchivoEliminacionLoteController::class, 'index'])
        ->middleware('can:'.$perm.'Ver');
    Route::get('eliminaciones/lotes/firmantes', [OfiArchivoEliminacionLoteController::class, 'firmantes'])
        ->middleware('can:'.$perm.'Registrar comite');
    Route::get('eliminaciones/lotes/{id}', [OfiArchivoEliminacionLoteController::class, 'show'])->whereNumber('id')
        ->middleware('can:'.$perm.'Ver');
    Route::get('eliminaciones/lotes/{id}/inventario', [OfiArchivoEliminacionLoteController::class, 'inventario'])->whereNumber('id')
        ->middleware('can:'.$perm.'Ver');
    Route::get('eliminaciones/lotes/{id}/acta', [OfiArchivoEliminacionLoteController::class, 'acta'])->whereNumber('id')
        ->middleware('can:'.$perm.'Ver');

    Route::post('eliminaciones/lotes', [OfiArchivoEliminacionLoteController::class, 'proponer'])
        ->middleware('can:'.$perm.'Proponer');
    Route::post('eliminaciones/lotes/{id}/anular', [OfiArchivoEliminacionLoteController::class, 'anular'])->whereNumber('id')
        ->middleware('can:'.$perm.'Proponer');
    Route::post('eliminaciones/lotes/{id}/concepto', [OfiArchivoEliminacionLoteController::class, 'concepto'])->whereNumber('id')
        ->middleware('can:'.$perm.'Registrar comite');
    Route::post('eliminaciones/lotes/{id}/comite', [OfiArchivoEliminacionLoteController::class, 'comite'])->whereNumber('id')
        ->middleware('can:'.$perm.'Registrar comite');
    // Firman el presidente y el secretario designados: el servicio verifica que sea uno de ellos
    Route::get('eliminaciones/lotes/por-firmar', [OfiArchivoEliminacionLoteController::class, 'porFirmar']);
    Route::post('eliminaciones/lotes/{id}/firmar', [OfiArchivoEliminacionLoteController::class, 'firmar'])->whereNumber('id');
    Route::post('eliminaciones/lotes/{id}/ejecutar', [OfiArchivoEliminacionLoteController::class, 'ejecutar'])->whereNumber('id')
        ->middleware('can:'.$perm.'Ejecutar');
});
