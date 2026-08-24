<?php

use App\Http\Controllers\OfiArchivo\OfiArchivoReportesController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas de Reportes y Estadísticas del Archivo.
 * Prefijo base: api/archivo/reportes
 */
$perm = 'Gestion de Archivo -> Reportes -> ';

Route::middleware('auth:sanctum')->group(function () use ($perm) {
    Route::get('reportes/{tipo}', [OfiArchivoReportesController::class, 'generar'])
        ->middleware('can:'.$perm.'Ver');
    Route::get('reportes/{tipo}/export', [OfiArchivoReportesController::class, 'export'])
        ->middleware('can:'.$perm.'Ver');
    Route::get('reportes-estadisticas', [OfiArchivoReportesController::class, 'estadisticas'])
        ->middleware('can:'.$perm.'Ver');
});
