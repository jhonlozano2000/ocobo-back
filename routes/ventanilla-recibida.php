<?php

use App\Http\Controllers\VentanillaUnica\MetadataController;
use App\Http\Controllers\VentanillaUnica\Recibidos\RadicadoRespuestasController;
use App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciAdjuntosController;
use App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciCompartirHistorialController;
use App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciController;
use App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciDigitalController;
use App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciPaseHistorialController;
use App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciResponsableController;
use Illuminate\Support\Facades\Route;

$permReci = 'Radicar -> Cores. Recibida -> ';

Route::middleware('auth:sanctum')->group(function () use ($permReci) {
    // Rutas con throttle:api (rate limit general 60/min)
    Route::middleware('throttle:api')->group(function () use ($permReci) {
        Route::get('/radica-recibida/estadisticas', [VentanillaRadicaReciController::class, 'estadisticas'])->name('radica-recibida.estadisticas')->middleware('can:'.$permReci.'Listar');
        Route::get('/radica-recibida/estados', [VentanillaRadicaReciController::class, 'estadosDisponibles'])->name('radica-recibida.estados')->middleware('can:'.$permReci.'Listar');
        Route::put('/radica-recibida/{id}/update-asunto', [VentanillaRadicaReciController::class, 'updateAsunto'])->name('radica-recibida.update-asunto')->middleware('can:'.$permReci.'Actualizar asunto')->middleware('acceso_radicado:recibido,id');
        Route::put('/radica-recibida/{id}/update-fechas', [VentanillaRadicaReciController::class, 'updateFechas'])->name('radica-recibida.update-fechas')->middleware('can:'.$permReci.'Atualizar fechas de radicados')->middleware('acceso_radicado:recibido,id');
        Route::put('/radica-recibida/{id}/update-clasificacion-documental', [VentanillaRadicaReciController::class, 'updateClasificacionDocumental'])->name('radica-recibida.update-clasificacion-documental')->middleware('can:'.$permReci.'Actualizar clasificacion de radicados')->middleware('acceso_radicado:recibido,id');
        Route::post('/radica-recibida/{id}/notificacion', [VentanillaRadicaReciController::class, 'enviarNotificacion'])->name('radica-recibida.notificacion')->middleware('can:'.$permReci.'Notificar Email')->middleware('acceso_radicado:recibido,id');
        Route::post('/radica-recibida/{id}/notificacion-tercero', [VentanillaRadicaReciController::class, 'enviarNotificacionTercero'])->name('radica-recibida.notificacion-tercero')->middleware('can:'.$permReci.'Notificar Email')->middleware('acceso_radicado:recibido,id');
        Route::get('/radica-recibida/{id}/linea-tiempo', [VentanillaRadicaReciController::class, 'lineaTiempo'])->name('radica-recibida.linea-tiempo')->middleware('acceso_radicado:recibido,id');
        Route::get('/radica-recibida/{id}/historial-estados', [VentanillaRadicaReciController::class, 'historialEstados'])->name('radica-recibida.historial-estados')->middleware('acceso_radicado:recibido,id');
        Route::get('/radica-recibida/mis-radicados', [VentanillaRadicaReciController::class, 'misRadicados'])->name('radica-recibida.mis-radicados')->middleware('can:'.$permReci.'Listar');
        Route::get('/radica-recibida/pendientes-anulacion', [VentanillaRadicaReciController::class, 'listarPendientesAnulacion'])->name('radica-recibida.pendientes-anulacion')->middleware('can:Jefe de Archivo');
        Route::post('/radica-recibida/{id}/solicitar-anulacion', [VentanillaRadicaReciController::class, 'solicitarAnulacion'])->name('radica-recibida.solicitar-anulacion')->middleware('can:'.$permReci.'Listar')->middleware('acceso_radicado:recibido,id');
        Route::post('/radica-recibida/{id}/procesar-anulacion', [VentanillaRadicaReciController::class, 'procesarAnulacion'])->name('radica-recibida.procesar-anulacion')->middleware('can:Jefe de Archivo');
        Route::get('/radica-recibida', [VentanillaRadicaReciController::class, 'index'])->name('radica-recibida.index')->middleware('can:'.$permReci.'Listar');
        Route::get('/radica-recibida/export', [VentanillaRadicaReciController::class, 'export'])->name('radica-recibida.export')->middleware('can:'.$permReci.'Listar');
        Route::get('/radica-recibida/{id}', [VentanillaRadicaReciController::class, 'show'])->name('radica-recibida.show')->middleware('can:'.$permReci.'Mostrar')->middleware('acceso_radicado:recibido,id');
        Route::get('/radica-recibida/search/ocr', [VentanillaRadicaReciController::class, 'searchByOcr'])->name('radica-recibida.search-ocr')->middleware('can:'.$permReci.'Listar');
    });

    // Rutas con throttle:radicacion (30/min) - operaciones de creación/edición
    Route::middleware('throttle:radicacion')->group(function () use ($permReci) {
        Route::post('/radica-recibida', [VentanillaRadicaReciController::class, 'store'])->name('radica-recibida.store')->middleware('can:'.$permReci.'Crear');
        Route::put('/radica-recibida/{id}', [VentanillaRadicaReciController::class, 'update'])->name('radica-recibida.update')->middleware('can:'.$permReci.'Editar')->middleware('acceso_radicado:recibido,id');
        Route::delete('/radica-recibida/{id}', [VentanillaRadicaReciController::class, 'destroy'])->name('radica-recibida.destroy')->middleware('can:'.$permReci.'Eliminar')->middleware('acceso_radicado:recibido,id');
        Route::delete('/radica-recibida', [VentanillaRadicaReciController::class, 'bulkDestroy'])->name('radica-recibida.bulk-destroy')->middleware('can:'.$permReci.'Eliminar');

        Route::post('/radica-recibida/{radicadoId}/respuestas', [RadicadoRespuestasController::class, 'store'])->name('radica-recibida.respuestas.store')->middleware('can:'.$permReci.'Crear')->middleware('acceso_radicado:recibido,radicadoId');
        Route::put('/radica-recibida/respuestas/{id}', [RadicadoRespuestasController::class, 'update'])->name('radica-recibida.respuestas.update')->middleware('can:'.$permReci.'Editar');
        Route::delete('/radica-recibida/respuestas/{id}', [RadicadoRespuestasController::class, 'destruir'])->name('radica-recibida.respuestas.destroy')->middleware('can:'.$permReci.'Eliminar');

        Route::post('/radica-recibida/{radica_reci_id}/responsables', [VentanillaRadicaReciResponsableController::class, 'assignToRadicado'])->name('radica-recibida.responsables.asignar')->middleware('can:'.$permReci.'Editar')->middleware('acceso_radicado:recibido,radica_reci_id');

        Route::post('/radica-recibida/{radica_reci_id}/pase', [VentanillaRadicaReciPaseHistorialController::class, 'store'])->name('radica-recibida.pase.registrar')->middleware('can_any:Radicar -> Cores. Recibida -> Editar,Mi Bandeja - Recibidos -> Ver,Mi Bandeja - Recibidos -> Editar Estado')->middleware('acceso_radicado:recibido,radica_reci_id');

        Route::post('/radica-recibida/{radica_reci_id}/compartir', [VentanillaRadicaReciCompartirHistorialController::class, 'store'])->name('radica-recibida.compartir.registrar')->middleware('can_any:Radicar -> Cores. Recibida -> Editar,Mi Bandeja - Recibidos -> Ver,Mi Bandeja - Recibidos -> Editar Estado')->middleware('acceso_radicado:recibido,radica_reci_id');
    });

    // Rutas con throttle:uploads (10/min) - subida de archivos
    Route::middleware('throttle:uploads')->group(function () use ($permReci) {
        Route::post('/radica-recibida/{id}/archivos/digital/upload', [VentanillaRadicaReciDigitalController::class, 'upload'])->name('radica-recibida.archivos.digital.upload')->middleware('can:'.$permReci.'Subir digital')->middleware('acceso_radicado:recibido,id');
        Route::post('/radica-recibida/{id}/archivos/digital/ocr/recargar', [VentanillaRadicaReciDigitalController::class, 'recargarOcr'])->name('radica-recibida.archivos.digital.ocr.recargar')->middleware('can:'.$permReci.'Subir digital')->middleware('acceso_radicado:recibido,id');
        Route::post('/radica-recibida/{id}/archivos/adjuntos/upload-adjuntos', [VentanillaRadicaReciAdjuntosController::class, 'subirArchivosAdjuntos'])->name('radica-recibida.archivos.adjuntos.upload')->middleware('can:'.$permReci.'Subir adjuntos')->middleware('acceso_radicado:recibido,id');
    });

    // Rutas de consulta con throttle:search (30/min)
    Route::middleware('throttle:search')->group(function () use ($permReci) {
        Route::get('/radica-recibida/{radicadoId}/respuestas', [RadicadoRespuestasController::class, 'index'])->name('radica-recibida.respuestas.index')->middleware('can:'.$permReci.'Listar')->middleware('acceso_radicado:recibido,radicadoId');
        Route::get('/radica-recibida/respuestas/{id}', [RadicadoRespuestasController::class, 'show'])->name('radica-recibida.respuestas.show')->middleware('can:'.$permReci.'Mostrar');
        Route::get('/radica-recibida/{id}/archivos/digital/', [VentanillaRadicaReciDigitalController::class, 'getFileInfo'])->name('radica-recibida.archivos.digital.info')->middleware('can:'.$permReci.'Mostrar')->middleware('acceso_radicado:recibido,id');
        Route::get('/radica-recibida/{id}/archivos/digital/download', [VentanillaRadicaReciDigitalController::class, 'download'])->name('radica-recibida.archivos.digital.download')->middleware('can:'.$permReci.'Mostrar')->middleware('acceso_radicado:recibido,id');
        Route::get('/radica-recibida/{id}/archivos/digital/ocr', [VentanillaRadicaReciDigitalController::class, 'getOcr'])->name('radica-recibida.archivos.digital.ocr')->middleware('can:'.$permReci.'Mostrar')->middleware('acceso_radicado:recibido,id');
        Route::get('/radica-recibida/{id}/archivos/adjuntos/listar', [VentanillaRadicaReciAdjuntosController::class, 'listarArchivosAdjuntos'])->name('radica-recibida.archivos.adjuntos.listar')->middleware('can:'.$permReci.'Mostrar')->middleware('acceso_radicado:recibido,id');
        Route::get('/radica-recibida/{id}/archivos/adjuntos/{archivoId}/descargar', [VentanillaRadicaReciAdjuntosController::class, 'descargarArchivoAdjunto'])->name('radica-recibida.archivos.adjuntos.descargar')->middleware('can:'.$permReci.'Mostrar')->middleware('acceso_radicado:recibido,id');
        Route::get('/radica-recibida/{id}/archivos/historial/archivos-eliminados', [VentanillaRadicaReciDigitalController::class, 'historialEliminaciones'])->name('radica-recibida.archivos.historial.eliminaciones')->middleware('can:'.$permReci.'Mostrar')->middleware('acceso_radicado:recibido,id');
    });

    // Rutas con throttle:api (generales)
    Route::middleware('throttle:api')->group(function () use ($permReci) {
        Route::delete('/radica-recibida/{id}/archivos/digital/delete', [VentanillaRadicaReciDigitalController::class, 'deleteFile'])->name('radica-recibida.archivos.digital.delete')->middleware('can:'.$permReci.'Eliminar digital')->middleware('acceso_radicado:recibido,id');
        Route::delete('/radica-recibida/{id}/archivos/adjuntos/{archivoId}/eliminar', [VentanillaRadicaReciAdjuntosController::class, 'eliminarArchivoAdjunto'])->name('radica-recibida.archivos.adjuntos.eliminar')->middleware('can:'.$permReci.'Eliminar adjuntos')->middleware('acceso_radicado:recibido,id');
        Route::post('/radica-recibida/responsables/{id}/marcar-visto', [VentanillaRadicaReciResponsableController::class, 'marcarVisto'])->name('radica-recibida.responsables.marcar-visto')->middleware('can:'.$permReci.'Editar');
        Route::apiResource('responsables', VentanillaRadicaReciResponsableController::class)->only('show', 'update', 'destroy')->middleware('can:'.$permReci.'Editar')->middleware('acceso_asignacion:responsable_recibido');
        Route::get('/radica-recibida/{radica_reci_id}/responsables', [VentanillaRadicaReciResponsableController::class, 'getByRadicado'])->name('radica-recibida.responsables.listar')->middleware('can:'.$permReci.'Editar')->middleware('acceso_radicado:recibido,radica_reci_id');

        Route::get('/radica-recibida/{radica_reci_id}/enviados', [VentanillaRadicaReciController::class, 'getEnviadosAsociados'])->name('radica-recibida.enviados.listar')->middleware('can:'.$permReci.'Mostrar')->middleware('acceso_radicado:recibido,radica_reci_id');

        Route::get('/radica-recibida/{radica_reci_id}/pase-historial', [VentanillaRadicaReciPaseHistorialController::class, 'byRadicado'])->name('radica-recibida.pase.historial')->middleware('can_any:Radicar -> Cores. Recibida -> Mostrar,Mi Bandeja - Recibidos -> Ver')->middleware('acceso_radicado:recibido,radica_reci_id');

        Route::get('/radica-recibida/{radica_reci_id}/compartir-historial', [VentanillaRadicaReciCompartirHistorialController::class, 'byRadicado'])->name('radica-recibida.compartir.historial')->middleware('can_any:Radicar -> Cores. Recibida -> Mostrar,Mi Bandeja - Recibidos -> Ver')->middleware('acceso_radicado:recibido,radica_reci_id');
        // Metadata es compartida por recibidos/enviados/internos: el prefijo de permiso
        // depende del parámetro {tipo}, así que se autoriza dentro del controlador
        // (MetadataController::autorizarPorTipo) y no con un can: fijo aqui.
        Route::get('/metadata/clasificacion-niveles', [MetadataController::class, 'nivelClasificacionIndex'])->name('metadata.niveles');
        Route::get('/metadata/archivos/{archivoId}/{tipo?}', [MetadataController::class, 'show'])->name('metadata.show');
        Route::put('/metadata/archivos/{archivoId}/{tipo?}', [MetadataController::class, 'store'])->name('metadata.update');
        Route::get('/metadata/historial/{archivoId}/{tipo?}', [MetadataController::class, 'historial'])->name('metadata.historial');
        Route::get('/metadata/exportar/{tipo?}', [MetadataController::class, 'exportar'])->name('metadata.exportar');
        Route::get('/metadata/sugerir-clasificacion', [MetadataController::class, 'sugerirClasificacion'])->name('metadata.sugerir-clasificacion');
    });
});
