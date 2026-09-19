<?php

use App\Http\Controllers\OfiArchivo\OfiArchivoPlantillaDocumentoController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas de Plantillas de Documentos Oficiales.
 *
 * Prefijo base: api/archivo/plantillas
 * ISO 27001 A.10.1.2 — Integridad de documentos mediante hash SHA-256.
 */
$perm = 'Gestion de Archivo -> Plantillas -> ';

Route::middleware('auth:sanctum')->group(function () use ($perm) {
    Route::get('plantillas', [OfiArchivoPlantillaDocumentoController::class, 'index']);
    Route::post('plantillas', [OfiArchivoPlantillaDocumentoController::class, 'store'])
        ->middleware('can:'.$perm.'Crear');
    Route::get('plantillas/{plantilla}', [OfiArchivoPlantillaDocumentoController::class, 'show'])
        ->middleware('can:'.$perm.'Ver');
    Route::post('plantillas/{plantilla}', [OfiArchivoPlantillaDocumentoController::class, 'update'])
        ->middleware('can:'.$perm.'Editar');
    Route::delete('plantillas/{plantilla}', [OfiArchivoPlantillaDocumentoController::class, 'destroy'])
        ->middleware('can:'.$perm.'Eliminar');
    Route::get('plantillas/{plantilla}/descargar', [OfiArchivoPlantillaDocumentoController::class, 'descargar'])
        ->middleware('can:'.$perm.'Descargar');
    Route::get('plantillas/{plantilla}/verificar-integridad', [OfiArchivoPlantillaDocumentoController::class, 'verificarIntegridad'])
        ->middleware('can:'.$perm.'Ver');
    Route::get('plantillas-estadisticas', [OfiArchivoPlantillaDocumentoController::class, 'estadisticas'])
        ->middleware('can:'.$perm.'Ver');
});
