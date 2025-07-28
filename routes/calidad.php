<?php

use App\Http\Controllers\Calidad\CalidadOrganigramaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    /**
     * Organigrama - Gestión completa
     */
    // Ruta para estadísticas (debe ir antes del resource)
    Route::get('/organigrama/estadisticas', [CalidadOrganigramaController::class, 'estadisticas']);

    // Rutas principales del organigrama
    Route::apiResource('organigrama', CalidadOrganigramaController::class)->except('create', 'edit');

    /**
     * Rutas específicas del organigrama
     */
    // Listar solo dependencias
    Route::get('/organigrama/dependencias', [CalidadOrganigramaController::class, 'listDependencias']);

    // Listar oficinas con cargos
    Route::get('/organigrama/oficinas', [CalidadOrganigramaController::class, 'listOficinas']);
});
