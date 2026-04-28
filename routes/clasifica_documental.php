<?php

use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDController;
use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDVersionController;
use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTVDController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo Clasificación Documental
 *
 * Prefix aplicado desde RouteServiceProvider: /api/clasifica-documental
 * Rutas finales:
 *   - /api/clasifica-documental/trd/*
 *   - /api/clasifica-documental/tvd/*
 *   - /api/clasifica-documental/trd-versiones/*
 */

/**
 * Rate limiting específico para Clasificación Documental
 */
Route::middleware('throttle:config-operations')->group(function () {

    // Rutas autenticadas con Sanctum
    Route::middleware('auth:sanctum')->group(function () {

        // ===================== TRD =====================
        Route::prefix('trd')->name('clasifica-documental.trd.')->group(function () {
            // Importar y descargar plantilla
            Route::get('/plantilla/descargar', [ClasificacionDocumentalTRDController::class, 'descargarPlantilla'])->name('plantilla.descargar');
            Route::post('/import-trd', [ClasificacionDocumentalTRDController::class, 'importarTRD'])->name('importar');

            // Estadísticas
            Route::get('/estadisticas/totales', [ClasificacionDocumentalTRDController::class, 'estadisticasTotales'])->name('estadisticas.totales');
            Route::get('/estadisticas/por-dependencias', [ClasificacionDocumentalTRDController::class, 'estadisticasPorDependencias'])->name('estadisticas.por-dependencias');
            Route::get('/estadisticas/{dependenciaId}', [ClasificacionDocumentalTRDController::class, 'estadistica'])->name('estadisticas');

            // Por dependencia
            Route::get('/por-dependencia/{dependenciaId}', [ClasificacionDocumentalTRDController::class, 'clasificacionesPorDependencia'])->name('clasificaciones.por-dependencia');
            Route::get('/dependencia/{dependenciaId}', [ClasificacionDocumentalTRDController::class, 'listarPorDependencia'])->name('por-dependencia');

            // Días de vencimiento (debe ir ANTES del resource para no chocar con /{trd})
            Route::get('/{id}/dias-vencimiento', [ClasificacionDocumentalTRDController::class, 'getDiasVencimiento'])->name('dias-vencimiento');

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

        // ===================== TVD =====================
        Route::prefix('tvd')->name('clasifica-documental.tvd.')->group(function () {
            // Estadísticas
            Route::get('/estadisticas', [ClasificacionDocumentalTVDController::class, 'estadisticas'])->name('estadisticas');

            // Por dependencia
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

        // ===================== TRD VERSIONES =====================
        Route::prefix('trd-versiones')->name('clasifica-documental.trd-versiones.')->group(function () {
            // Aprobar y pendientes
            Route::post('/aprobar/{dependenciaId}', [ClasificacionDocumentalTRDVersionController::class, 'aprobarVersion'])->name('aprobar');
            Route::get('/pendientes/aprobar', [ClasificacionDocumentalTRDVersionController::class, 'listarPendientesPorAprobar'])->name('pendientes');

            // Estadísticas
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

    }); // Fin auth:sanctum

}); // Fin throttle