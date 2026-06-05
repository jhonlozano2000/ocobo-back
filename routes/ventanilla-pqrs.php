<?php

use App\Http\Controllers\VentanillaUnica\Pqrs\VentanillaPqrsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:radicacion'])->group(function () {
    Route::post('/pqrs', [VentanillaPqrsController::class, 'store'])->name('pqrs.store');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('throttle:api')->group(function () {
        Route::get('/pqrs', [VentanillaPqrsController::class, 'index'])->name('pqrs.index');
        Route::get('/pqrs/estadisticas', [VentanillaPqrsController::class, 'estadisticas'])->name('pqrs.estadisticas');
        Route::get('/pqrs/{id}/linea-tiempo', [VentanillaPqrsController::class, 'lineaTiempo'])->name('pqrs.linea-tiempo');
        Route::get('/pqrs/{id}', [VentanillaPqrsController::class, 'show'])->name('pqrs.show');
    });

    Route::middleware('throttle:radicacion')->group(function () {
        Route::put('/pqrs/{id}', [VentanillaPqrsController::class, 'update'])->name('pqrs.update');
        Route::delete('/pqrs/{id}', [VentanillaPqrsController::class, 'destroy'])->name('pqrs.destroy');
        Route::put('/pqrs/{id}/estado', [VentanillaPqrsController::class, 'cambiarEstado'])->name('pqrs.cambiar-estado');
        Route::post('/pqrs/{id}/prorroga', [VentanillaPqrsController::class, 'aplicarProrroga'])->name('pqrs.prorroga');
        Route::put('/pqrs/{id}/asunto', [VentanillaPqrsController::class, 'updateAsunto'])->name('pqrs.update-asunto');
        Route::put('/pqrs/{id}/fechas', [VentanillaPqrsController::class, 'updateFechas'])->name('pqrs.update-fechas');
        Route::put('/pqrs/{id}/clasificacion', [VentanillaPqrsController::class, 'updateClasificacion'])->name('pqrs.update-clasificacion');
        Route::get('/pqrs/{id}/rotulo', [VentanillaPqrsController::class, 'imprimirRotulo'])->name('pqrs.imprimir-rotulo');
        Route::post('/pqrs/{id}/notificar-email', [VentanillaPqrsController::class, 'notificarEmail'])->name('pqrs.notificar-email');
    });
});