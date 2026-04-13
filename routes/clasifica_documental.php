<?php

use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDController;
use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDVersionController;
use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTVDController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo Clasificación Documental
 *
 * Prefix aplicado desde RouteServiceProvider: /api/clasifica-documental
 * Rutas finales: /api/clasifica-documental/trd/* y /api/clasifica-documental/tvd/*
 */

/**
 * Rate limiting específico para Clasificación Documental
 */
Route::middleware('throttle:config-operations')->group(function () {

    /**
     * TRD (Tabla de Retención Documental)
     * Rutas: /api/clasifica-documental/trd/*
     */
    Route::prefix('trd')->name('clasifica-documental.trd.')->group(function () {
        // Rutas específicas (deben ir ANTES del resource)
        Route::get('/plantilla/descargar', [ClasificacionDocumentalTRDController::class, 'descargarPlantilla'])->name('plantilla.descargar');
        Route::post('/import-trd', [ClasificacionDocumentalTRDController::class, 'importarTRD'])->name('importar');

        // Rutas de estadísticas
        Route::get('/estadisticas/totales', [ClasificacionDocumentalTRDController::class, 'estadisticasTotales'])->name('estadisticas.totales');
        Route::get('/estadisticas/por-dependencias', [ClasificacionDocumentalTRDController::class, 'estadisticasPorDependencias'])->name('estadisticas.por-dependencias');

        // Ruta para clasificaciones por dependencia
        Route::get('/por-dependencia/{dependenciaId}', [ClasificacionDocumentalTRDController::class, 'clasificacionesPorDependencia'])->name('clasificaciones.por-dependencia');

        // Ruta para días de vencimiento (debe ir ANTES del resource para no chocar con /{trd})
        Route::get('/{id}/dias-vencimiento', [ClasificacionDocumentalTRDController::class, 'getDiasVencimiento'])->name('dias-vencimiento');

        // Rutas con parámetros
        Route::get('/estadisticas/{dependenciaId}', [ClasificacionDocumentalTRDController::class, 'estadistica'])->name('estadisticas');
        Route::get('/dependencia/{dependenciaId}', [ClasificacionDocumentalTRDController::class, 'listarPorDependencia'])->name('por-dependencia');

        // Resource route
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
     * TVD (Tabla de Valoración Documental)
     * Rutas: /api/clasifica-documental/tvd/*
     */
    Route::prefix('tvd')->name('clasifica-documental.tvd.')->group(function () {
        // Rutas de estadísticas
        Route::get('/estadisticas', [ClasificacionDocumentalTVDController::class, 'estadisticas'])->name('estadisticas');

        // Rutas con parámetros
        Route::get('/dependencia/{dependenciaId}', [ClasificacionDocumentalTVDController::class, 'listarPorDependencia'])->name('por-dependencia');

        // Resource route
        Route::apiResource('', ClasificacionDocumentalTVDController::class)
            ->parameters(['' => 'tvd'])
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
        // Rutas específicas
        Route::post('/aprobar/{dependenciaId}', [ClasificacionDocumentalTRDVersionController::class, 'aprobarVersion'])->name('aprobar');
        Route::get('/pendientes/aprobar', [ClasificacionDocumentalTRDVersionController::class, 'listarPendientesPorAprobar'])->name('pendientes');
        Route::get('/estadisticas/{dependenciaId}', [ClasificacionDocumentalTRDVersionController::class, 'estadisticas'])->name('estadisticas');

        // Resource route
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