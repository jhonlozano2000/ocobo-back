<?php

use App\Http\Controllers\OfiArchivo\OfiArchivoDashboardController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del Dashboard de Archivo.
 * Prefijo base: api/archivo/dashboard
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('stats', [OfiArchivoDashboardController::class, 'stats']);
});
