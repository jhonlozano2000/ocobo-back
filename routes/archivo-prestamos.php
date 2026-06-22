<?php

use App\Http\Controllers\OfiArchivo\OfiArchivoPrestamoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('prestamos', [OfiArchivoPrestamoController::class, 'index']);
    Route::post('prestamos', [OfiArchivoPrestamoController::class, 'store']);
    Route::get('prestamos/{id}', [OfiArchivoPrestamoController::class, 'show']);
    Route::post('prestamos/{id}/devolver', [OfiArchivoPrestamoController::class, 'devolver']);
    Route::get('prestamos-estadisticas', [OfiArchivoPrestamoController::class, 'estadisticas']);
});
