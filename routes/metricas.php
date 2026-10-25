<?php

use App\Http\Controllers\Reportes\MetricasArchivosController;
use Illuminate\Support\Facades\Route;

/**
 * Métricas transversales.
 * Prefijo base: api/metricas
 */
Route::middleware(['auth:sanctum', 'can:Reporte -> Metricas de archivos'])->group(function () {
    Route::get('archivos', [MetricasArchivosController::class, 'index'])->name('metricas.archivos');
});
