<?php

use App\Http\Controllers\OfiArchivo\OfiArchivoPlantillaDocumentoController;
use App\Http\Controllers\OfiArchivo\OfiArchivoPlantillaGeneradaController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas de Plantillas de Documentos Oficiales.
 *
 * Prefijo base: api/archivo/plantillas (cargue de plantillas: archivos) y api/archivo/plantillas-generadas
 * (generador de plantillas: hechas con el editor de Mis Documentos).
 * ISO 27001 A.10.1.2 — Integridad de documentos mediante hash SHA-256.
 */
$perm = 'Gestion de Archivo -> Plantillas -> ';

Route::middleware(['auth:sanctum', 'throttle:archivo'])->group(function () use ($perm) {
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

    Route::get('plantillas-generadas', [OfiArchivoPlantillaGeneradaController::class, 'index'])
        ->middleware('can:'.$perm.'Ver');
    Route::post('plantillas-generadas', [OfiArchivoPlantillaGeneradaController::class, 'store'])
        ->middleware('can:'.$perm.'Crear');
    Route::patch('plantillas-generadas/{plantilla}', [OfiArchivoPlantillaGeneradaController::class, 'update'])
        ->middleware('can:'.$perm.'Editar');
    Route::delete('plantillas-generadas/{plantilla}', [OfiArchivoPlantillaGeneradaController::class, 'destroy'])
        ->middleware('can:'.$perm.'Eliminar');
    Route::post('plantillas-generadas/{plantilla}/editar', [OfiArchivoPlantillaGeneradaController::class, 'editar'])
        ->middleware('can:'.$perm.'Editar');
    Route::post('plantillas-generadas/{plantilla}/duplicar', [OfiArchivoPlantillaGeneradaController::class, 'duplicar'])
        ->middleware('can:'.$perm.'Crear');
});
