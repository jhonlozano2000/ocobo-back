<?php

use App\Http\Controllers\OfiArchivo\OfiArchivoReportesController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas de Reportes y Estadísticas del Archivo.
 * Prefijo base: api/archivo/reportes
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('reportes/{tipo}', [OfiArchivoReportesController::class, 'generar']);
    Route::get('reportes-estadisticas', [OfiArchivoReportesController::class, 'estadisticas']);
});
