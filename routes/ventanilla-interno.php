<?php

use App\Http\Controllers\VentanillaUnica\Internos\VentanillaRadicaInternoAdjuntosController;
use App\Http\Controllers\VentanillaUnica\Internos\VentanillaRadicaInternoCompartirHistorialController;
use App\Http\Controllers\VentanillaUnica\Internos\VentanillaRadicaInternoController;
use App\Http\Controllers\VentanillaUnica\Internos\VentanillaRadicaInternoDestinatariosController;
use App\Http\Controllers\VentanillaUnica\Internos\VentanillaRadicaInternoDigitalController;
use App\Http\Controllers\VentanillaUnica\Internos\VentanillaRadicaInternoFirmantesController;
use App\Http\Controllers\VentanillaUnica\Internos\VentanillaRadicaInternoPaseHistorialController;
use App\Http\Controllers\VentanillaUnica\Internos\VentanillaRadicaInternoRespuestasController;
use App\Http\Controllers\VentanillaUnica\Internos\VentanillaRadicaInternoProyectoresController;
use App\Http\Controllers\VentanillaUnica\Internos\VentanillaRadicaInternoResponsableController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    $permInterno = 'Radicar -> Cores. Interna -> ';
    Route::get('/radica-interno/estadisticas', [VentanillaRadicaInternoController::class, 'estadisticas'])->name('radica-interno.estadisticas')->middleware('can:'.$permInterno.'Listar');
    Route::get('/radica-interno/pendientes-anulacion', [VentanillaRadicaInternoController::class, 'listarPendientesAnulacion'])->name('radica-interno.pendientes-anulacion')->middleware('can:Jefe de Archivo');
    Route::put('/radica-interno/{id}/update-asunto', [VentanillaRadicaInternoController::class, 'updateAsunto'])->name('radica-interno.update-asunto')->middleware('can:'.$permInterno.'Actualizar asunto');
    Route::put('/radica-interno/{id}/update-fechas', [VentanillaRadicaInternoController::class, 'updateFechas'])->name('radica-interno.update-fechas')->middleware('can:'.$permInterno.'Atualizar fechas de radicados');
    Route::put('/radica-interno/{id}/update-clasificacion-documental', [VentanillaRadicaInternoController::class, 'updateClasificacionDocumental'])->name('radica-interno.update-clasificacion-documental')->middleware('can:'.$permInterno.'Actualizar clasificacion de radicados');
    Route::post('/radica-interno/{id}/notificacion', [VentanillaRadicaInternoController::class, 'enviarNotificacion'])->name('radica-interno.notificacion')->middleware('can:'.$permInterno.'Notificar Email');
    Route::get('/radica-interno/{id}/linea-tiempo', [VentanillaRadicaInternoController::class, 'lineaTiempo'])->name('radica-interno.linea-tiempo')->middleware('can:'.$permInterno.'Mostrar');
    Route::get('/radica-interno/mis-radicados', [VentanillaRadicaInternoController::class, 'misRadicados'])->name('radica-interno.mis-radicados')->middleware('can:'.$permInterno.'Listar');
    Route::put('/radica-interno/{id}/estado', [VentanillaRadicaInternoController::class, 'updateEstado'])->name('radica-interno.update-estado')->middleware('can:'.$permInterno.'Editar');
    Route::post('/radica-interno/{id}/solicitar-anulacion', [VentanillaRadicaInternoController::class, 'solicitarAnulacion'])->name('radica-interno.solicitar-anulacion')->middleware('can:'.$permInterno.'Listar');
    Route::post('/radica-interno/{id}/procesar-anulacion', [VentanillaRadicaInternoController::class, 'procesarAnulacion'])->name('radica-interno.procesar-anulacion')->middleware('can:Jefe de Archivo');
    Route::get('/radica-interno', [VentanillaRadicaInternoController::class, 'index'])->name('radica-interno.index')->middleware('can:'.$permInterno.'Listar');
    Route::post('/radica-interno', [VentanillaRadicaInternoController::class, 'store'])->name('radica-interno.store')->middleware('can:'.$permInterno.'Crear');
    Route::get('/radica-interno/{id}', [VentanillaRadicaInternoController::class, 'show'])->name('radica-interno.show')->middleware('can:'.$permInterno.'Mostrar');
    Route::put('/radica-interno/{id}', [VentanillaRadicaInternoController::class, 'update'])->name('radica-interno.update')->middleware('can:'.$permInterno.'Editar');
    Route::delete('/radica-interno/{id}', [VentanillaRadicaInternoController::class, 'destroy'])->name('radica-interno.destroy')->middleware('can:'.$permInterno.'Eliminar');
    Route::get('/radica-interno/search/ocr', [VentanillaRadicaInternoController::class, 'searchByOcr'])->name('radica-interno.search-ocr')->middleware('can:'.$permInterno.'Listar');

    Route::prefix('radica-interno/{id}/archivos')->name('radica-interno.archivos.')->group(function () use ($permInterno) {
        Route::prefix('digital')->name('digital.')->group(function () use ($permInterno) {
            Route::get('/', [VentanillaRadicaInternoDigitalController::class, 'getFileInfo'])->name('info');
            Route::post('/upload', [VentanillaRadicaInternoDigitalController::class, 'upload'])->name('upload')->middleware('can:'.$permInterno.'Subir digital');
            Route::get('/download', [VentanillaRadicaInternoDigitalController::class, 'download'])->name('download')->middleware('can:'.$permInterno.'Mostrar');
            Route::delete('/delete', [VentanillaRadicaInternoDigitalController::class, 'deleteFile'])->name('delete')->middleware('can:'.$permInterno.'Eliminar digital');
            Route::get('/ocr', [VentanillaRadicaInternoDigitalController::class, 'getOcr'])->name('ocr')->middleware('can:'.$permInterno.'Mostrar');
            Route::post('/ocr/recargar', [VentanillaRadicaInternoDigitalController::class, 'recargarOcr'])->name('ocr.recargar')->middleware('can:'.$permInterno.'Subir digital');
        });

        Route::prefix('adjuntos')->name('adjuntos.')->group(function () use ($permInterno) {
            Route::get('/listar', [VentanillaRadicaInternoAdjuntosController::class, 'listarArchivosAdjuntos'])->name('listar');
            Route::get('/{archivoId}/descargar', [VentanillaRadicaInternoAdjuntosController::class, 'descargarArchivoAdjunto'])->name('descargar')->middleware('can:'.$permInterno.'Mostrar');
            Route::delete('/{archivoId}/eliminar', [VentanillaRadicaInternoAdjuntosController::class, 'eliminarArchivoAdjunto'])->name('eliminar')->middleware('can:'.$permInterno.'Eliminar adjuntos');
            Route::post('/upload-adjuntos', [VentanillaRadicaInternoAdjuntosController::class, 'subirArchivosAdjuntos'])->name('upload')->middleware('can:'.$permInterno.'Subir adjuntos');
        });

        Route::get('/historial/archivos-eliminados', [VentanillaRadicaInternoDigitalController::class, 'historialEliminaciones'])->name('historial.eliminaciones');
    });

    Route::post('/responsables-internos/{id}/marcar-visto', [VentanillaRadicaInternoResponsableController::class, 'marcarVisto'])->name('radica-interno.responsables.marcar-visto')->middleware('can:'.$permInterno.'Editar');
    Route::post('/destinatarios-internos/{id}/marcar-visto', [VentanillaRadicaInternoDestinatariosController::class, 'marcarVisto'])->name('radica-interno.destinatarios.marcar-visto')->middleware('can:'.$permInterno.'Editar');
    Route::apiResource('responsables-internos', VentanillaRadicaInternoResponsableController::class)->except('create', 'edit')->middleware('can:'.$permInterno.'Editar');
    Route::get('/radica-interno/{radica_interno_id}/responsables', [VentanillaRadicaInternoResponsableController::class, 'getByRadicado'])->name('radica-interno.responsables.listar')->middleware('can:'.$permInterno.'Editar');
    Route::post('/radica-interno/{radica_interno_id}/responsables', [VentanillaRadicaInternoResponsableController::class, 'assignToRadicado'])->name('radica-interno.responsables.asignar')->middleware('can:'.$permInterno.'Editar');

    Route::post('/radica-interno/{radica_interno_id}/pase', [VentanillaRadicaInternoPaseHistorialController::class, 'store'])->name('radica-interno.pase.registrar')->middleware('can:'.$permInterno.'Editar');
    Route::get('/radica-interno/{radica_interno_id}/pase-historial', [VentanillaRadicaInternoPaseHistorialController::class, 'byRadicado'])->name('radica-interno.pase.historial')->middleware('can:'.$permInterno.'Mostrar');

    Route::post('/radica-interno/{radica_interno_id}/compartir', [VentanillaRadicaInternoCompartirHistorialController::class, 'store'])->name('radica-interno.compartir.registrar')->middleware('can:'.$permInterno.'Editar');
    Route::get('/radica-interno/{radica_interno_id}/compartir-historial', [VentanillaRadicaInternoCompartirHistorialController::class, 'byRadicado'])->name('radica-interno.compartir.historial')->middleware('can:'.$permInterno.'Mostrar');

    Route::apiResource('proyectores-internos', VentanillaRadicaInternoProyectoresController::class)->except('create', 'edit')->middleware('can:'.$permInterno.'Editar');
    Route::get('/radica-interno/{radica_interno_id}/proyectores', [VentanillaRadicaInternoProyectoresController::class, 'getByRadicado'])->name('radica-interno.proyectores.listar')->middleware('can:'.$permInterno.'Editar');
    Route::post('/radica-interno/{radica_interno_id}/proyectores', [VentanillaRadicaInternoProyectoresController::class, 'assignToRadicado'])->name('radica-interno.proyectores.asignar')->middleware('can:'.$permInterno.'Editar');

    Route::apiResource('firmantes-internos', VentanillaRadicaInternoFirmantesController::class)->except('create', 'edit')->middleware('can:'.$permInterno.'Editar');
    Route::get('/radica-interno/{radica_interno_id}/firmantes', [VentanillaRadicaInternoFirmantesController::class, 'getByRadicado'])->name('radica-interno.firmantes.listar')->middleware('can:'.$permInterno.'Editar');
    Route::post('/radica-interno/{radica_interno_id}/firmantes', [VentanillaRadicaInternoFirmantesController::class, 'assignToRadicado'])->name('radica-interno.firmantes.asignar')->middleware('can:'.$permInterno.'Editar');

    Route::get('/radica-interno/{radica_interno_id}/respuestas', [VentanillaRadicaInternoRespuestasController::class, 'index'])->name('radica-interno.respuestas.listar')->middleware('can:'.$permInterno.'Editar');
    Route::get('/radica-interno/{radica_interno_id}/respuestas/disponibles', [VentanillaRadicaInternoRespuestasController::class, 'disponibles'])->name('radica-interno.respuestas.disponibles')->middleware('can:'.$permInterno.'Editar');
    Route::post('/radica-interno/{radica_interno_id}/respuestas', [VentanillaRadicaInternoRespuestasController::class, 'store'])->name('radica-interno.respuestas.asignar')->middleware('can:'.$permInterno.'Editar');
    Route::delete('/radica-interno/{radica_interno_id}/respuestas/{respuestaId}', [VentanillaRadicaInternoRespuestasController::class, 'destroy'])->name('radica-interno.respuestas.desasociar')->middleware('can:'.$permInterno.'Editar');

    Route::apiResource('destinatarios-internos', VentanillaRadicaInternoDestinatariosController::class)->except('create', 'edit')->middleware('can:'.$permInterno.'Editar');
    Route::get('/radica-interno/{radica_interno_id}/destinatarios', [VentanillaRadicaInternoDestinatariosController::class, 'getByRadicado'])->name('radica-interno.destinatarios.listar')->middleware('can:'.$permInterno.'Editar');
    Route::post('/radica-interno/{radica_interno_id}/destinatarios', [VentanillaRadicaInternoDestinatariosController::class, 'assignToRadicado'])->name('radica-interno.destinatarios.asignar')->middleware('can:'.$permInterno.'Editar');
});
