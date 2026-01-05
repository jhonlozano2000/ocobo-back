<?php

use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDController;
use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDVersionController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo Clasificación Documental
 *
 * Prefix aplicado desde RouteServiceProvider: /api/clasifica-documental
 * Rutas finales: /api/clasifica-documental/trd/* y /api/clasifica-documental/trd-versiones/*
 */
Route::middleware('auth:sanctum')->group(function () {

    /**
     * TRD (Tabla de Retención Documental)
     * Rutas: /api/clasifica-documental/trd/*
     */
    Route::prefix('trd')->name('clasifica-documental.trd.')->group(function () {
        // Rutas específicas (deben ir ANTES del resource)
        // Importar TRD desde Excel
        Route::post('/import-trd', [ClasificacionDocumentalTRDController::class, 'importarTRD'])->name('importar');

        // Rutas de estadísticas específicas (deben ir antes de las rutas con parámetro)
        Route::get('/estadisticas/totales', [ClasificacionDocumentalTRDController::class, 'estadisticasTotales'])->name('estadisticas.totales');
        Route::get('/estadisticas/por-dependencias', [ClasificacionDocumentalTRDController::class, 'estadisticasPorDependencias'])->name('estadisticas.por-dependencias');

        // Ruta para clasificaciones por dependencia en estructura jerárquica
        Route::get('/por-dependencia/{dependenciaId}', [ClasificacionDocumentalTRDController::class, 'clasificacionesPorDependencia'])->name('clasificaciones.por-dependencia');

        // Rutas con parámetros (deben ir después de las específicas)
        Route::get('/estadisticas/{dependenciaId}', [ClasificacionDocumentalTRDController::class, 'estadistica'])->name('estadisticas');
        Route::get('/dependencia/{dependenciaId}', [ClasificacionDocumentalTRDController::class, 'listarPorDependencia'])->name('por-dependencia');

        // Resource route (debe ir DESPUÉS de las rutas específicas)
        Route::apiResource('', ClasificacionDocumentalTRDController::class)
            ->parameters(['' => 'trd'])
            ->names([
                'index' => 'index',
                'store' => 'store',
                'show' => 'show',
                'update' => 'update',
                'destroy' => 'destroy'
            ])->except('create', 'edit');
    });

    /**
     * Versiones TRD
     * Rutas: /api/clasifica-documental/trd-versiones/*
     */
    Route::prefix('trd-versiones')->name('clasifica-documental.trd-versiones.')->group(function () {
        // Rutas específicas (deben ir ANTES del resource)
        Route::post('/aprobar/{dependenciaId}', [ClasificacionDocumentalTRDVersionController::class, 'aprobarVersion'])->name('aprobar');
        Route::get('/pendientes/aprobar', [ClasificacionDocumentalTRDVersionController::class, 'listarPendientesPorAprobar'])->name('pendientes');
        Route::get('/estadisticas/{dependenciaId}', [ClasificacionDocumentalTRDVersionController::class, 'estadisticas'])->name('estadisticas');

        // Resource route (debe ir DESPUÉS de las rutas específicas)
        Route::apiResource('', ClasificacionDocumentalTRDVersionController::class)
            ->parameters(['' => 'trdVersion'])
            ->names([
                'index' => 'index',
                'store' => 'store',
                'show' => 'show',
                'update' => 'update',
                'destroy' => 'destroy'
            ])->except('create', 'edit');
    });
});
