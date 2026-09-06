<?php

use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDController;
use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDVersionController;
use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTVDController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo Clasificación Documental
 *
 * Prefix aplicado desde RouteServiceProvider: /api/clasifica-documental
 *
 * @author Jhon Javer Lozano Arce
 *
 * @date 2026-08-24
 */
Route::middleware('throttle:config-operations')->group(function () {

    Route::middleware('auth:sanctum')->group(function () {

        // ===================== TRD =====================
        $p = 'TRD -> ';
        Route::prefix('trd')->name('clasifica-documental.trd.')->group(function () use ($p) {
            Route::get('/plantilla/descargar', [ClasificacionDocumentalTRDController::class, 'descargarPlantilla'])->name('plantilla.descargar')->middleware('can:'.$p.'Exportar');
            Route::post('/import-trd', [ClasificacionDocumentalTRDController::class, 'importarTRD'])->name('importar')->middleware('can:'.$p.'Importar');

            Route::get('/estadisticas/totales', [ClasificacionDocumentalTRDController::class, 'estadisticasTotales'])->name('estadisticas.totales')->middleware('can:'.$p.'Listar');
            Route::get('/estadisticas/por-dependencias', [ClasificacionDocumentalTRDController::class, 'estadisticasPorDependencias'])->name('estadisticas.por-dependencias')->middleware('can:'.$p.'Listar');
            Route::get('/estadisticas/{dependenciaId}', [ClasificacionDocumentalTRDController::class, 'estadistica'])->name('estadisticas')->middleware('can:'.$p.'Listar');

            Route::get('/dependencia/{dependenciaId}', [ClasificacionDocumentalTRDController::class, 'listarPorDependencia'])->name('por-dependencia')->middleware('can:'.$p.'Listar');
            Route::get('/{id}/dias-vencimiento', [ClasificacionDocumentalTRDController::class, 'getDiasVencimiento'])->name('dias-vencimiento')->middleware('can:'.$p.'Mostrar');

            Route::get('/', [ClasificacionDocumentalTRDController::class, 'index'])->name('index')->middleware('can:'.$p.'Listar');
            Route::post('/', [ClasificacionDocumentalTRDController::class, 'store'])->name('store')->middleware('can:'.$p.'Crear');
            Route::get('/{trd}', [ClasificacionDocumentalTRDController::class, 'show'])->name('show')->middleware('can:'.$p.'Mostrar');
            Route::put('/{trd}', [ClasificacionDocumentalTRDController::class, 'update'])->name('update')->middleware('can:'.$p.'Editar');
            Route::delete('/{trd}', [ClasificacionDocumentalTRDController::class, 'destroy'])->name('destroy')->middleware('can:'.$p.'Eliminar');
        });

        // ===================== TVD =====================
        $pt = 'TVD -> ';
        Route::prefix('tvd')->name('clasifica-documental.tvd.')->group(function () use ($pt) {
            Route::get('/estadisticas', [ClasificacionDocumentalTVDController::class, 'estadisticas'])->name('estadisticas')->middleware('can:'.$pt.'Listar');
            Route::get('/dependencia/{dependenciaId}', [ClasificacionDocumentalTVDController::class, 'listarPorDependencia'])->name('por-dependencia')->middleware('can:'.$pt.'Listar');

            Route::post('/import', [ClasificacionDocumentalTVDController::class, 'import'])->name('import')->middleware('can:'.$pt.'Importar');
            Route::post('/validate', [ClasificacionDocumentalTVDController::class, 'validar'])->name('validate')->middleware('can:'.$pt.'Listar');
            Route::get('/export', [ClasificacionDocumentalTVDController::class, 'export'])->name('export')->middleware('can:'.$pt.'Exportar');

            Route::get('/', [ClasificacionDocumentalTVDController::class, 'index'])->name('index')->middleware('can:'.$pt.'Listar');
            Route::post('/', [ClasificacionDocumentalTVDController::class, 'store'])->name('store')->middleware('can:'.$pt.'Crear');
            Route::get('/{tvd}', [ClasificacionDocumentalTVDController::class, 'show'])->name('show')->middleware('can:'.$pt.'Mostrar');
            Route::put('/{tvd}', [ClasificacionDocumentalTVDController::class, 'update'])->name('update')->middleware('can:'.$pt.'Editar');
            Route::delete('/{tvd}', [ClasificacionDocumentalTVDController::class, 'destroy'])->name('destroy')->middleware('can:'.$pt.'Eliminar');
        });

        // ===================== TRD VERSIONES =====================
        $pv = 'TRD -> Versiones';
        Route::prefix('trd-versiones')->name('clasifica-documental.trd-versiones.')->group(function () use ($p, $pv) {
            Route::post('/aprobar/{dependenciaId}', [ClasificacionDocumentalTRDVersionController::class, 'aprobarVersion'])->name('aprobar')->middleware("can:{$pv}");
            Route::get('/pendientes/aprobar', [ClasificacionDocumentalTRDVersionController::class, 'listarPendientesPorAprobar'])->name('pendientes')->middleware('can:'.$p.'Listar');
            Route::get('/estadisticas/{dependenciaId}', [ClasificacionDocumentalTRDVersionController::class, 'estadisticas'])->name('estadisticas')->middleware('can:'.$p.'Listar');

            Route::get('/', [ClasificacionDocumentalTRDVersionController::class, 'index'])->name('index')->middleware('can:'.$p.'Listar');
            Route::post('/', [ClasificacionDocumentalTRDVersionController::class, 'store'])->name('store')->middleware('can:'.$p.'Crear');
            Route::get('/{trdVersion}', [ClasificacionDocumentalTRDVersionController::class, 'show'])->name('show')->middleware('can:'.$p.'Mostrar');
            Route::put('/{trdVersion}', [ClasificacionDocumentalTRDVersionController::class, 'update'])->name('update')->middleware('can:'.$p.'Editar');
            Route::delete('/{trdVersion}', [ClasificacionDocumentalTRDVersionController::class, 'destroy'])->name('destroy')->middleware('can:'.$p.'Eliminar');
        });

    }); // Fin auth:sanctum

}); // Fin throttle
