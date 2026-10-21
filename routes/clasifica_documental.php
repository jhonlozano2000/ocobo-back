<?php

use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDController;
use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDVersionController;
use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTVDController;
use App\Http\Controllers\ClasificacionDocumental\FondoAcumuladoController;
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
            Route::get('/unidad/{unidadId}', [ClasificacionDocumentalTVDController::class, 'listarPorUnidad'])->name('por-unidad')->middleware('can:'.$pt.'Listar');

            Route::post('/import', [ClasificacionDocumentalTVDController::class, 'import'])->name('import')->middleware('can:'.$pt.'Importar');
            Route::get('/plantilla', [ClasificacionDocumentalTVDController::class, 'plantilla'])->name('plantilla')->middleware('can:'.$pt.'Importar');
            Route::post('/validate', [ClasificacionDocumentalTVDController::class, 'validar'])->name('validate')->middleware('can:'.$pt.'Listar');
            Route::get('/export', [ClasificacionDocumentalTVDController::class, 'export'])->name('export')->middleware('can:'.$pt.'Exportar');
            Route::post('/aprobar/{periodoId}', [ClasificacionDocumentalTVDController::class, 'aprobar'])->name('aprobar')->middleware('can:'.$pt.'Versiones');

            Route::get('/', [ClasificacionDocumentalTVDController::class, 'index'])->name('index')->middleware('can:'.$pt.'Listar');
            Route::post('/', [ClasificacionDocumentalTVDController::class, 'store'])->name('store')->middleware('can:'.$pt.'Crear');
            Route::get('/{tvd}', [ClasificacionDocumentalTVDController::class, 'show'])->name('show')->middleware('can:'.$pt.'Mostrar');
            Route::put('/{tvd}', [ClasificacionDocumentalTVDController::class, 'update'])->name('update')->middleware('can:'.$pt.'Editar');
            Route::delete('/{tvd}', [ClasificacionDocumentalTVDController::class, 'destroy'])->name('destroy')->middleware('can:'.$pt.'Eliminar');
        });

        // ===================== FONDOS ACUMULADOS (marco de la TVD, M11-02) =====================
        Route::prefix('fondos-acumulados')->name('clasifica-documental.fondos-acumulados.')->group(function () use ($pt) {
            Route::get('/', [FondoAcumuladoController::class, 'index'])->name('index')->middleware('can:'.$pt.'Listar');
            Route::post('/', [FondoAcumuladoController::class, 'store'])->name('store')->middleware('can:'.$pt.'Crear');
            Route::put('/{fondo}', [FondoAcumuladoController::class, 'update'])->name('update')->middleware('can:'.$pt.'Editar');
            Route::delete('/{fondo}', [FondoAcumuladoController::class, 'destroy'])->name('destroy')->middleware('can:'.$pt.'Eliminar');

            Route::post('/{fondo}/periodos', [FondoAcumuladoController::class, 'storePeriodo'])->name('periodos.store')->middleware('can:'.$pt.'Crear');
            Route::put('/periodos/{periodo}', [FondoAcumuladoController::class, 'updatePeriodo'])->name('periodos.update')->middleware('can:'.$pt.'Editar');
            Route::delete('/periodos/{periodo}', [FondoAcumuladoController::class, 'destroyPeriodo'])->name('periodos.destroy')->middleware('can:'.$pt.'Eliminar');

            Route::get('/periodos/{periodo}/unidades', [FondoAcumuladoController::class, 'unidades'])->name('unidades.index')->middleware('can:'.$pt.'Listar');
            Route::post('/periodos/{periodo}/unidades', [FondoAcumuladoController::class, 'storeUnidad'])->name('unidades.store')->middleware('can:'.$pt.'Crear');
            Route::put('/unidades/{unidad}', [FondoAcumuladoController::class, 'updateUnidad'])->name('unidades.update')->middleware('can:'.$pt.'Editar');
            Route::delete('/unidades/{unidad}', [FondoAcumuladoController::class, 'destroyUnidad'])->name('unidades.destroy')->middleware('can:'.$pt.'Eliminar');
        });

        // ===================== TRD VERSIONES =====================
        $pv = 'TRD -> Versiones';
        Route::prefix('trd-versiones')->name('clasifica-documental.trd-versiones.')->group(function () use ($p, $pv) {
            Route::post('/aprobar/{dependenciaId}', [ClasificacionDocumentalTRDVersionController::class, 'aprobarVersion'])->name('aprobar')->middleware("can:{$pv}");
            Route::get('/pendientes/aprobar', [ClasificacionDocumentalTRDVersionController::class, 'listarPendientesPorAprobar'])->name('pendientes')->middleware('can:'.$p.'Listar');
            Route::get('/estadisticas/{dependenciaId}', [ClasificacionDocumentalTRDVersionController::class, 'estadisticas'])->name('estadisticas')->middleware('can:'.$p.'Listar');
            // B18: inscripción de la versión en el RUSD, requisito para eliminar
            Route::patch('/{id}/rusd', [ClasificacionDocumentalTRDVersionController::class, 'registrarRusd'])->whereNumber('id')->name('rusd')->middleware("can:{$pv}");

            Route::get('/', [ClasificacionDocumentalTRDVersionController::class, 'index'])->name('index')->middleware('can:'.$p.'Listar');
            Route::post('/', [ClasificacionDocumentalTRDVersionController::class, 'store'])->name('store')->middleware('can:'.$p.'Crear');
            Route::get('/{trdVersion}', [ClasificacionDocumentalTRDVersionController::class, 'show'])->name('show')->middleware('can:'.$p.'Mostrar');
            Route::put('/{trdVersion}', [ClasificacionDocumentalTRDVersionController::class, 'update'])->name('update')->middleware('can:'.$p.'Editar');
            Route::delete('/{trdVersion}', [ClasificacionDocumentalTRDVersionController::class, 'destroy'])->name('destroy')->middleware('can:'.$p.'Eliminar');
        });

    }); // Fin auth:sanctum

}); // Fin throttle
