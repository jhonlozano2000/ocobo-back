<?php

use App\Http\Controllers\Calidad\CalidadOrganigramaController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo Calidad
 *
 * Prefix aplicado desde RouteServiceProvider: /api/calidad
 * Rutas finales: /api/calidad/organigrama/*
 */
Route::middleware('auth:sanctum')->group(function () {

    /**
     * Organigrama - Gestión completa
     * Rutas: /api/calidad/organigrama/*
     */
    Route::prefix('organigrama')->name('calidad.organigrama.')->group(function () {
        // Rutas específicas del organigrama (deben ir ANTES del resource)
        // Listar solo dependencias
        Route::get('/dependencias', [CalidadOrganigramaController::class, 'listDependencias'])->name('dependencias');

        // Listar oficinas con cargos
        Route::get('/oficinas', [CalidadOrganigramaController::class, 'listOficinas'])->name('oficinas');

        // Ruta para estadísticas
        Route::get('/estadisticas', [CalidadOrganigramaController::class, 'estadisticas'])->name('estadisticas');

        // Rutas principales del organigrama (debe ir DESPUÉS de las rutas específicas)
        Route::apiResource('', CalidadOrganigramaController::class)
            ->parameters(['' => 'organigrama'])
            ->names([
                'index' => 'index',
                'store' => 'store',
                'show' => 'show',
                'update' => 'update',
                'destroy' => 'destroy'
            ])->except('create', 'edit');
    });
});
