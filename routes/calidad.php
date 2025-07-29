<?php

use App\Http\Controllers\Calidad\CalidadOrganigramaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    /**
     * Organigrama - Gestión completa
     */
    // Rutas específicas del organigrama (deben ir ANTES del resource)
    // Listar solo dependencias
    Route::get('/organigrama/dependencias', [CalidadOrganigramaController::class, 'listDependencias']);

    // Listar oficinas con cargos
    Route::get('/organigrama/oficinas', [CalidadOrganigramaController::class, 'listOficinas']);

    // Ruta para estadísticas
    Route::get('/organigrama/estadisticas', [CalidadOrganigramaController::class, 'estadisticas']);

    // Rutas principales del organigrama (debe ir DESPUÉS de las rutas específicas)
    Route::apiResource('organigrama', CalidadOrganigramaController::class)->except('create', 'edit');
});
