<?php

use App\Http\Controllers\OfiArchivo\OfiArchivoPlantillaDocumentoController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas de Plantillas de Documentos Oficiales.
 *
 * Prefijo base: api/archivo/plantillas
 * ISO 27001 A.10.1.2 — Integridad de documentos mediante hash SHA-256.
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('plantillas', [OfiArchivoPlantillaDocumentoController::class, 'index']);
    Route::post('plantillas', [OfiArchivoPlantillaDocumentoController::class, 'store']);
    Route::get('plantillas/{plantilla}', [OfiArchivoPlantillaDocumentoController::class, 'show']);
    Route::post('plantillas/{plantilla}', [OfiArchivoPlantillaDocumentoController::class, 'update']);
    Route::delete('plantillas/{plantilla}', [OfiArchivoPlantillaDocumentoController::class, 'destroy']);
    Route::get('plantillas/{plantilla}/descargar', [OfiArchivoPlantillaDocumentoController::class, 'descargar']);
    Route::get('plantillas/{plantilla}/verificar-integridad', [OfiArchivoPlantillaDocumentoController::class, 'verificarIntegridad']);
    Route::get('plantillas-estadisticas', [OfiArchivoPlantillaDocumentoController::class, 'estadisticas']);
});
