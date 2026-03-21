<?php

use App\Http\Controllers\VentanillaUnica\VentanillaRadicaInternoProyectoresController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaInternoArchivosController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaInternoController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaInternoDestinatariosController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaInternoResponsaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    $permInterno = 'Radicar -> Cores. Interno -> ';
    Route::get('/radica-interno/estadisticas', [VentanillaRadicaInternoController::class, 'estadisticas'])->name('radica-interno.estadisticas')->middleware('can:' . $permInterno . 'Listar');
    Route::put('/radica-interno/{id}/update-clasificacion-documental', [VentanillaRadicaInternoController::class, 'updateClasificacionDocumental'])->name('radica-interno.update-clasificacion-documental')->middleware('can:' . $permInterno . 'Actualizar clasificacion de radicados');
    Route::post('/radica-interno/{id}/notificacion', [VentanillaRadicaInternoController::class, 'enviarNotificacion'])->name('radica-interno.notificacion')->middleware('can:' . $permInterno . 'Notificar Email');
    Route::get('/radica-interno/{id}/linea-tiempo', [VentanillaRadicaInternoController::class, 'lineaTiempo'])->name('radica-interno.linea-tiempo')->middleware('can:' . $permInterno . 'Mostrar');
    Route::get('/radica-interno', [VentanillaRadicaInternoController::class, 'index'])->name('radica-interno.index')->middleware('can:' . $permInterno . 'Listar');
    Route::post('/radica-interno', [VentanillaRadicaInternoController::class, 'store'])->name('radica-interno.store')->middleware('can:' . $permInterno . 'Crear');
    Route::get('/radica-interno/{id}', [VentanillaRadicaInternoController::class, 'show'])->name('radica-interno.show')->middleware('can:' . $permInterno . 'Mostrar');
    Route::put('/radica-interno/{id}', [VentanillaRadicaInternoController::class, 'update'])->name('radica-interno.update')->middleware('can:' . $permInterno . 'Editar');
    Route::delete('/radica-interno/{id}', [VentanillaRadicaInternoController::class, 'destroy'])->name('radica-interno.destroy')->middleware('can:' . $permInterno . 'Eliminar');

    Route::prefix('radica-interno/{id}')->name('radica-interno.')->group(function () use ($permInterno) {
        Route::get('/archivos/adjuntos/listar', [VentanillaRadicaInternoArchivosController::class, 'listarArchivosAdjuntos'])->name('archivos.adjuntos.listar')->middleware('can:' . $permInterno . 'Mostrar');
        Route::get('/archivos/historial/archivos-eliminados', [VentanillaRadicaInternoArchivosController::class, 'historialEliminaciones'])->name('archivos.historial.eliminaciones')->middleware('can:' . $permInterno . 'Mostrar');
        Route::get('/archivos/adjuntos/{archivoId}/descargar', [VentanillaRadicaInternoArchivosController::class, 'descargarArchivoAdjunto'])->name('archivos.adjuntos.descargar')->middleware('can:' . $permInterno . 'Mostrar');
        Route::delete('/archivos/adjuntos/{archivoId}/eliminar', [VentanillaRadicaInternoArchivosController::class, 'eliminarArchivoAdjunto'])->name('archivos.adjuntos.eliminar')->middleware('can:' . $permInterno . 'Eliminar adjuntos');
        Route::get('/archivos/info/', [VentanillaRadicaInternoArchivosController::class, 'getFileInfo'])->name('archivos.info')->middleware('can:' . $permInterno . 'Mostrar');
        Route::post('/archivos/upload-adjuntos', [VentanillaRadicaInternoArchivosController::class, 'subirArchivosAdjuntos'])->name('archivos.upload-adjuntos')->middleware('can:' . $permInterno . 'Subir adjuntos');
        Route::get('/archivos/download', [VentanillaRadicaInternoArchivosController::class, 'download'])->name('archivos.download')->middleware('can:' . $permInterno . 'Mostrar');
        Route::delete('/archivos/delete', [VentanillaRadicaInternoArchivosController::class, 'deleteFile'])->name('archivos.delete')->middleware('can:' . $permInterno . 'Eliminar digital');
    });

    Route::apiResource('responsables-internos', VentanillaRadicaInternoResponsaController::class)->except('create', 'edit')->middleware('can:' . $permInterno . 'Editar');
    Route::get('/radica-interno/{radica_interno_id}/responsables', [VentanillaRadicaInternoResponsaController::class, 'getByRadicado'])->name('radica-interno.responsables.listar')->middleware('can:' . $permInterno . 'Editar');
    Route::post('/radica-interno/{radica_interno_id}/responsables', [VentanillaRadicaInternoResponsaController::class, 'assignToRadicado'])->name('radica-interno.responsables.asignar')->middleware('can:' . $permInterno . 'Editar');

    Route::apiResource('proyectores-internos', VentanillaRadicaInternoProyectoresController::class)->except('create', 'edit')->middleware('can:' . $permInterno . 'Editar');
    Route::get('/radica-interno/{radica_interno_id}/proyectores', [VentanillaRadicaInternoProyectoresController::class, 'getByRadicado'])->name('radica-interno.proyectores.listar')->middleware('can:' . $permInterno . 'Editar');
    Route::post('/radica-interno/{radica_interno_id}/proyectores', [VentanillaRadicaInternoProyectoresController::class, 'assignToRadicado'])->name('radica-interno.proyectores.asignar')->middleware('can:' . $permInterno . 'Editar');

    Route::apiResource('destinatarios-internos', VentanillaRadicaInternoDestinatariosController::class)->except('create', 'edit')->middleware('can:' . $permInterno . 'Editar');
    Route::get('/radica-interno/{radica_interno_id}/destinatarios', [VentanillaRadicaInternoDestinatariosController::class, 'getByRadicado'])->name('radica-interno.destinatarios.listar')->middleware('can:' . $permInterno . 'Editar');
    Route::post('/radica-interno/{radica_interno_id}/destinatarios', [VentanillaRadicaInternoDestinatariosController::class, 'assignToRadicado'])->name('radica-interno.destinatarios.asignar')->middleware('can:' . $permInterno . 'Editar');
});
