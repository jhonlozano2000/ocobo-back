<?php

use App\Http\Controllers\Calidad\CalidadOrganigramaController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo Calidad
 *
 * Prefix aplicado desde RouteServiceProvider: /api/calidad
 * Rutas finales: /api/calidad/organigrama/*
 *
 * @author Jhon Javer Lozano Arce
 *
 * @date 2026-08-24
 */
Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('organigrama')->name('calidad.organigrama.')->group(function () {
        $p = 'Calidad - Organigrama -> ';

        Route::get('/dependencias', [CalidadOrganigramaController::class, 'listDependencias'])->name('dependencias')->middleware('can:'.$p.'Listar');
        Route::get('/oficinas', [CalidadOrganigramaController::class, 'listOficinas'])->name('oficinas')->middleware('can:'.$p.'Listar');
        Route::get('/estadisticas', [CalidadOrganigramaController::class, 'estadisticas'])->name('estadisticas')->middleware('can:'.$p.'Listar');

        Route::get('/', [CalidadOrganigramaController::class, 'index'])->name('index')->middleware('can:'.$p.'Listar');
        Route::post('/', [CalidadOrganigramaController::class, 'store'])->name('store')->middleware('can:'.$p.'Crear');
        Route::get('/{organigrama}', [CalidadOrganigramaController::class, 'show'])->name('show')->middleware('can:'.$p.'Mostrar');
        Route::put('/{organigrama}', [CalidadOrganigramaController::class, 'update'])->name('update')->middleware('can:'.$p.'Editar');
        Route::delete('/{organigrama}', [CalidadOrganigramaController::class, 'destroy'])->name('destroy')->middleware('can:'.$p.'Eliminar');
    });

});
