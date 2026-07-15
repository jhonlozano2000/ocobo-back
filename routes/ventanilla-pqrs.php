<?php

use App\Http\Controllers\VentanillaUnica\Pqrs\VentanillaPqrsArchivosController;
use App\Http\Controllers\VentanillaUnica\Pqrs\VentanillaPqrsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:radicacion'])->group(function () {
    Route::post('/pqrs', [VentanillaPqrsController::class, 'store'])->name('pqrs.store');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('throttle:api')->group(function () {
        Route::get('/pqrs', [VentanillaPqrsController::class, 'index'])->name('pqrs.index');
        Route::get('/pqrs/export', [VentanillaPqrsController::class, 'export'])->name('pqrs.export');
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
        Route::post('/pqrs/{id}/solicitar-otp-firma', [VentanillaPqrsController::class, 'solicitarOtpFirma'])->name('pqrs.solicitar-otp-firma');
        Route::post('/pqrs/{id}/validar-otp-firma', [VentanillaPqrsController::class, 'validarOtpFirma'])->name('pqrs.validar-otp-firma');
        Route::post('/pqrs/{id}/guardar-firma', [VentanillaPqrsController::class, 'guardarFirma'])->name('pqrs.guardar-firma');
        Route::post('/pqrs/{id}/anular', [VentanillaPqrsController::class, 'anular'])->name('pqrs.anular');
        Route::get('/pqrs/pendientes-firma', [VentanillaPqrsController::class, 'pendientesFirma'])->name('pqrs.pendientes-firma');
    });

    // ── Archivos PQRS ────────────────────────────────────────
    Route::prefix('pqrs/{id}')->group(function () {
        Route::get('archivos', [VentanillaPqrsArchivosController::class, 'listar'])
            ->name('pqrs.archivos.listar');
        Route::post('archivos/digital/upload', [VentanillaPqrsArchivosController::class, 'subirDigital'])
            ->name('pqrs.archivos.digital.upload');
        Route::get('archivos/digital/descargar', [VentanillaPqrsArchivosController::class, 'descargarDigital'])
            ->name('pqrs.archivos.digital.descargar');
        Route::delete('archivos/digital/eliminar', [VentanillaPqrsArchivosController::class, 'eliminarDigital'])
            ->name('pqrs.archivos.digital.eliminar');
        Route::post('archivos/adjuntos/upload', [VentanillaPqrsArchivosController::class, 'subirAdjuntos'])
            ->name('pqrs.archivos.adjuntos.upload');
        Route::get('archivos/{archivoId}/descargar', [VentanillaPqrsArchivosController::class, 'descargarAdjunto'])
            ->name('pqrs.archivos.adjuntos.descargar');
        Route::delete('archivos/{archivoId}/eliminar', [VentanillaPqrsArchivosController::class, 'eliminarAdjunto'])
            ->name('pqrs.archivos.adjuntos.eliminar');
    });
});
