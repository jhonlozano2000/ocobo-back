<?php

use App\Http\Controllers\VentanillaUnica\Enviados\VentanillaRadicaEnviadosAdjuntosController;
use App\Http\Controllers\VentanillaUnica\Enviados\VentanillaRadicaEnviadosController;
use App\Http\Controllers\VentanillaUnica\Enviados\VentanillaRadicaEnviadosDigitalController;
use App\Http\Controllers\VentanillaUnica\Enviados\VentanillaRadicaEnviadosFirmantesController;
use App\Http\Controllers\VentanillaUnica\Enviados\VentanillaRadicaEnviadosPendientesController;
use App\Http\Controllers\VentanillaUnica\Enviados\VentanillaRadicaEnviadosProyectoresController;
use App\Http\Controllers\VentanillaUnica\Enviados\VentanillaRadicaEnviadosResponsableController;
use App\Http\Controllers\VentanillaUnica\Enviados\VentanillaRadicaEnviadosRespuestasController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    $permEnvi = 'Radicar -> Cores. Enviada -> ';

    Route::get('/radica-enviada/pendientes-radicar', [VentanillaRadicaEnviadosPendientesController::class, 'index'])->name('radica-enviada.pendientes-radicar.index')->middleware('can:'.$permEnvi.'Listar');
    Route::get('/radica-enviada/pendientes-radicar/{id}/documento/{versionId?}', [VentanillaRadicaEnviadosPendientesController::class, 'descargarDocumento'])->name('radica-enviada.pendientes-radicar.documento')->middleware('can:'.$permEnvi.'Listar');
    Route::get('/radica-enviada/pendientes-radicar/{id}/adjunto/{adjuntoId}', [VentanillaRadicaEnviadosPendientesController::class, 'descargarAdjunto'])->name('radica-enviada.pendientes-radicar.adjunto')->middleware('can:'.$permEnvi.'Listar');
    Route::get('/radica-enviada/pendientes-radicar/{id}', [VentanillaRadicaEnviadosPendientesController::class, 'show'])->name('radica-enviada.pendientes-radicar.show')->middleware('can:'.$permEnvi.'Listar');
    Route::get('/radica-enviada/estadisticas', [VentanillaRadicaEnviadosController::class, 'estadisticas'])->name('radica-enviada.estadisticas')->middleware('can:'.$permEnvi.'Listar');
    Route::put('/radica-enviada/{id}/update-asunto', [VentanillaRadicaEnviadosController::class, 'updateAsunto'])->name('radica-enviada.update-asunto')->middleware('can:'.$permEnvi.'Actualizar asunto')->middleware('acceso_radicado:enviado');
    Route::put('/radica-enviada/{id}/update-fechas', [VentanillaRadicaEnviadosController::class, 'updateFechas'])->name('radica-enviada.update-fechas')->middleware('can:'.$permEnvi.'Atualizar fechas de radicados')->middleware('acceso_radicado:enviado');
    Route::put('/radica-enviada/{id}/update-clasificacion-documental', [VentanillaRadicaEnviadosController::class, 'updateClasificacionDocumental'])->name('radica-enviada.update-clasificacion-documental')->middleware('can:'.$permEnvi.'Actualizar clasificacion de radicados')->middleware('acceso_radicado:enviado');
    Route::post('/radica-enviada/{id}/notificacion', [VentanillaRadicaEnviadosController::class, 'enviarNotificacion'])->name('radica-enviada.notificacion')->middleware('can:'.$permEnvi.'Notificar Email')->middleware('acceso_radicado:enviado')->middleware('throttle:radicacion');
    Route::get('/radica-enviada/{id}/linea-tiempo', [VentanillaRadicaEnviadosController::class, 'lineaTiempo'])->name('radica-enviada.linea-tiempo')->middleware('can:'.$permEnvi.'Mostrar')->middleware('acceso_radicado:enviado');
    Route::get('/radica-enviada/mis-radicados', [VentanillaRadicaEnviadosController::class, 'misRadicados'])->name('radica-enviada.mis-radicados')->middleware('can:'.$permEnvi.'Listar');
    Route::put('/radica-enviada/{id}/estado', [VentanillaRadicaEnviadosController::class, 'updateEstado'])->name('radica-enviada.update-estado')->middleware('can:'.$permEnvi.'Editar')->middleware('acceso_radicado:enviado');
    Route::get('/radica-enviada', [VentanillaRadicaEnviadosController::class, 'index'])->name('radica-enviada.index')->middleware('can:'.$permEnvi.'Listar');
    Route::post('/radica-enviada', [VentanillaRadicaEnviadosController::class, 'store'])->name('radica-enviada.store')->middleware('can:'.$permEnvi.'Crear')->middleware('throttle:radicacion');
    Route::get('/radica-enviada/export', [VentanillaRadicaEnviadosController::class, 'export'])->name('radica-enviada.export')->middleware('can:'.$permEnvi.'Listar');
    Route::get('/radica-enviada/pendientes-anulacion', [VentanillaRadicaEnviadosController::class, 'listarPendientesAnulacion'])->name('radica-enviada.pendientes-anulacion')->middleware('can:Jefe de Archivo');
    Route::post('/radica-enviada/{id}/solicitar-anulacion', [VentanillaRadicaEnviadosController::class, 'solicitarAnulacion'])->name('radica-enviada.solicitar-anulacion')->middleware('can:'.$permEnvi.'Listar')->middleware('acceso_radicado:enviado');
    Route::post('/radica-enviada/{id}/procesar-anulacion', [VentanillaRadicaEnviadosController::class, 'procesarAnulacion'])->name('radica-enviada.procesar-anulacion')->middleware('can:Jefe de Archivo');
    Route::get('/radica-enviada/{id}', [VentanillaRadicaEnviadosController::class, 'show'])->name('radica-enviada.show')->middleware('can:'.$permEnvi.'Mostrar')->middleware('acceso_radicado:enviado');
    Route::put('/radica-enviada/{id}', [VentanillaRadicaEnviadosController::class, 'update'])->name('radica-enviada.update')->middleware('can:'.$permEnvi.'Editar')->middleware('acceso_radicado:enviado');
    Route::delete('/radica-enviada/{id}', [VentanillaRadicaEnviadosController::class, 'destroy'])->name('radica-enviada.destroy')->middleware('can:'.$permEnvi.'Eliminar')->middleware('acceso_radicado:enviado');
    Route::get('/radica-enviada/search/ocr', [VentanillaRadicaEnviadosController::class, 'searchByOcr'])->name('radica-enviada.search-ocr')->middleware('can:'.$permEnvi.'Listar');

    Route::prefix('radica-enviada/{id}/archivos')->middleware('acceso_radicado:enviado')->name('radica-enviada.archivos.')->group(function () use ($permEnvi) {
        Route::prefix('digital')->name('digital.')->group(function () use ($permEnvi) {
            Route::get('/', [VentanillaRadicaEnviadosDigitalController::class, 'getFileInfo'])->name('info')->middleware('can:'.$permEnvi.'Mostrar');
            Route::post('/upload', [VentanillaRadicaEnviadosDigitalController::class, 'upload'])->name('upload')->middleware('can:'.$permEnvi.'Subir digital')->middleware('throttle:uploads');
            Route::get('/download', [VentanillaRadicaEnviadosDigitalController::class, 'download'])->name('download')->middleware('can:'.$permEnvi.'Mostrar');
            Route::delete('/delete', [VentanillaRadicaEnviadosDigitalController::class, 'deleteFile'])->name('delete')->middleware('can:'.$permEnvi.'Eliminar digital');
            Route::get('/ocr', [VentanillaRadicaEnviadosDigitalController::class, 'getOcr'])->name('ocr')->middleware('can:'.$permEnvi.'Mostrar');
            Route::post('/ocr/recargar', [VentanillaRadicaEnviadosDigitalController::class, 'recargarOcr'])->name('ocr.recargar')->middleware('can:'.$permEnvi.'Subir digital')->middleware('throttle:uploads');
        });

        Route::prefix('adjuntos')->name('adjuntos.')->group(function () use ($permEnvi) {
            Route::get('/listar', [VentanillaRadicaEnviadosAdjuntosController::class, 'listarArchivosAdjuntos'])->name('listar')->middleware('can:'.$permEnvi.'Mostrar');
            Route::get('/{archivoId}/descargar', [VentanillaRadicaEnviadosAdjuntosController::class, 'descargarArchivoAdjunto'])->name('descargar')->middleware('can:'.$permEnvi.'Mostrar');
            Route::delete('/{archivoId}/eliminar', [VentanillaRadicaEnviadosAdjuntosController::class, 'eliminarArchivoAdjunto'])->name('eliminar')->middleware('can:'.$permEnvi.'Eliminar adjuntos');
            Route::post('/upload-adjuntos', [VentanillaRadicaEnviadosAdjuntosController::class, 'subirArchivosAdjuntos'])->name('upload')->middleware('can:'.$permEnvi.'Subir adjuntos')->middleware('throttle:uploads');
        });

        Route::get('/historial/archivos-eliminados', [VentanillaRadicaEnviadosDigitalController::class, 'historialEliminaciones'])->name('historial.eliminaciones')->middleware('can:'.$permEnvi.'Mostrar');
    });

    Route::post('/responsables-enviados/{id}/marcar-visto', [VentanillaRadicaEnviadosResponsableController::class, 'marcarVisto'])->name('radica-enviada.responsables.marcar-visto')->middleware('can:'.$permEnvi.'Editar');
    // index/store se retiraron: listaban o creaban asignaciones de cualquier radicado; se usan las rutas
    // anidadas /radica-*/{id}/... (M01-08, M03-15..18)
    Route::apiResource('responsables-enviados', VentanillaRadicaEnviadosResponsableController::class)->only('show', 'update', 'destroy')->middleware('can:'.$permEnvi.'Editar')->middleware('acceso_asignacion:responsable_enviado');
    Route::get('/radica-enviada/{radica_enviado_id}/responsables', [VentanillaRadicaEnviadosResponsableController::class, 'getByRadicado'])->name('radica-enviada.responsables.listar')->middleware('can:'.$permEnvi.'Editar')->middleware('acceso_radicado:enviado,radica_enviado_id');
    Route::post('/radica-enviada/{radica_enviado_id}/responsables', [VentanillaRadicaEnviadosResponsableController::class, 'assignToRadicado'])->name('radica-enviada.responsables.asignar')->middleware('can:'.$permEnvi.'Editar')->middleware('acceso_radicado:enviado,radica_enviado_id');

    Route::apiResource('firmantes-enviados', VentanillaRadicaEnviadosFirmantesController::class)->only('show', 'update', 'destroy')->middleware('can:'.$permEnvi.'Editar')->middleware('acceso_asignacion:firmante_enviado');
    Route::get('/radica-enviada/{radica_enviado_id}/firmantes', [VentanillaRadicaEnviadosFirmantesController::class, 'getByRadicado'])->name('radica-enviada.firmantes.listar')->middleware('can:'.$permEnvi.'Editar')->middleware('acceso_radicado:enviado,radica_enviado_id');
    Route::post('/radica-enviada/{radica_enviado_id}/firmantes', [VentanillaRadicaEnviadosFirmantesController::class, 'assignToRadicado'])->name('radica-enviada.firmantes.asignar')->middleware('can:'.$permEnvi.'Editar')->middleware('acceso_radicado:enviado,radica_enviado_id');

    Route::apiResource('proyectores-enviados', VentanillaRadicaEnviadosProyectoresController::class)->only('show', 'update', 'destroy')->middleware('can:'.$permEnvi.'Editar')->middleware('acceso_asignacion:proyector_enviado');
    Route::get('/radica-enviada/{radica_enviado_id}/proyectores', [VentanillaRadicaEnviadosProyectoresController::class, 'getByRadicado'])->name('radica-enviada.proyectores.listar')->middleware('can:'.$permEnvi.'Editar')->middleware('acceso_radicado:enviado,radica_enviado_id');
    Route::post('/radica-enviada/{radica_enviado_id}/proyectores', [VentanillaRadicaEnviadosProyectoresController::class, 'assignToRadicado'])->name('radica-enviada.proyectores.asignar')->middleware('can:'.$permEnvi.'Editar')->middleware('acceso_radicado:enviado,radica_enviado_id');

    Route::get('/radica-enviada/{radica_enviado_id}/recibidos', [VentanillaRadicaEnviadosRespuestasController::class, 'index'])->name('radica-enviada.recibidos.listar')->middleware('can:'.$permEnvi.'Editar')->middleware('acceso_radicado:enviado,radica_enviado_id');
    Route::post('/radica-enviada/{radica_enviado_id}/recibidos', [VentanillaRadicaEnviadosRespuestasController::class, 'store'])->name('radica-enviada.recibidos.asignar')->middleware('can:'.$permEnvi.'Editar')->middleware('acceso_radicado:enviado,radica_enviado_id');
    Route::delete('/radica-enviada/{radica_enviado_id}/recibidos/{recibidoId}', [VentanillaRadicaEnviadosRespuestasController::class, 'destroy'])->name('radica-enviada.recibidos.desasociar')->middleware('can:'.$permEnvi.'Editar')->middleware('acceso_radicado:enviado,radica_enviado_id');

    Route::post('/radica-enviada/{id}/notificacion-tercero', [VentanillaRadicaEnviadosController::class, 'enviarNotificacionTercero'])->name('radica-enviada.notificacion-tercero')->middleware('can:'.$permEnvi.'Notificar Email')->middleware('acceso_radicado:enviado')->middleware('throttle:radicacion');

    // Las rutas /metadata/* son compartidas y se registran en ventanilla-recibida.php.
    // Duplicarlas aqui las dejaba inoperativas (Laravel resuelve la primera registrada)
    // y su permiso fijo de Enviada nunca se aplicaba.
});
