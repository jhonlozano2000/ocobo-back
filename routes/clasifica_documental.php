<?php

use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDController;
use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDVersionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    /**
     * ==================== TRD (Tabla de Retención Documental) ====================
     */

    // Rutas principales de TRD
    Route::prefix('trd')->group(function () {
        // CRUD básico
        Route::get('/', [ClasificacionDocumentalTRDController::class, 'index'])->name('trd.index');
        Route::post('/', [ClasificacionDocumentalTRDController::class, 'store'])->name('trd.store');
        Route::get('/{id}', [ClasificacionDocumentalTRDController::class, 'show'])->name('trd.show');
        Route::put('/{id}', [ClasificacionDocumentalTRDController::class, 'update'])->name('trd.update');
        Route::delete('/{id}', [ClasificacionDocumentalTRDController::class, 'destroy'])->name('trd.destroy');

        // Rutas específicas
        Route::post('/importar', [ClasificacionDocumentalTRDController::class, 'importarTRD'])->name('trd.importar');
        Route::get('/estadisticas/{dependenciaId}', [ClasificacionDocumentalTRDController::class, 'estadistica'])->name('trd.estadisticas');
        Route::get('/dependencia/{dependenciaId}', [ClasificacionDocumentalTRDController::class, 'listarPorDependencia'])->name('trd.por-dependencia');

        // Nuevas rutas de estadísticas
        Route::get('/estadisticas/totales', [ClasificacionDocumentalTRDController::class, 'estadisticasTotales'])->name('trd.estadisticas.totales');
        Route::get('/estadisticas/por-dependencias', [ClasificacionDocumentalTRDController::class, 'estadisticasPorDependencias'])->name('trd.estadisticas.por-dependencias');
        Route::get('/estadisticas/comparativas', [ClasificacionDocumentalTRDController::class, 'estadisticasComparativas'])->name('trd.estadisticas.comparativas');
    });

    /**
     * ==================== VERSIONES TRD ====================
     */

    // Rutas de versiones
    Route::prefix('trd-versiones')->group(function () {
        // CRUD básico
        Route::get('/', [ClasificacionDocumentalTRDVersionController::class, 'index'])->name('trd-versiones.index');
        Route::post('/', [ClasificacionDocumentalTRDVersionController::class, 'store'])->name('trd-versiones.store');
        Route::get('/{id}', [ClasificacionDocumentalTRDVersionController::class, 'show'])->name('trd-versiones.show');

        // Rutas específicas
        Route::post('/aprobar/{dependenciaId}', [ClasificacionDocumentalTRDVersionController::class, 'aprobarVersion'])->name('trd-versiones.aprobar');
        Route::get('/pendientes/aprobar', [ClasificacionDocumentalTRDVersionController::class, 'listarPendientesPorAprobar'])->name('trd-versiones.pendientes');
        Route::get('/estadisticas/{dependenciaId}', [ClasificacionDocumentalTRDVersionController::class, 'estadisticas'])->name('trd-versiones.estadisticas');
    });
});
