<?php

use App\Http\Controllers\VentanillaUnica\VentanillaRadicaEnviadosArchivosController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaEnviadosController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaEnviadosFirmantesController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaEnviadosProyectoresController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaEnviadosResponsaController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaReciController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaReciArchivosController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaReciResponsaController;
use App\Http\Controllers\VentanillaUnica\VentanillaUnicaController;
use App\Http\Controllers\VentanillaUnica\PermisosVentanillaUnicaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    /**
     * Ventanillas Únicas (Config - Ventanillas)
     */
    $permConfig = 'Config - Ventanillas -> ';
    Route::prefix('sedes/{sedeId}/ventanillas')->group(function () use ($permConfig) {
        Route::get('/', [VentanillaUnicaController::class, 'index'])->middleware('can:' . $permConfig . 'Listar');
        Route::post('/', [VentanillaUnicaController::class, 'store'])->middleware('can:' . $permConfig . 'Crear');
        Route::get('/{id}', [VentanillaUnicaController::class, 'show'])->middleware('can:' . $permConfig . 'Mostrar');
        Route::put('/{id}', [VentanillaUnicaController::class, 'update'])->middleware('can:' . $permConfig . 'Editar');
        Route::delete('/{id}', [VentanillaUnicaController::class, 'destroy'])->middleware('can:' . $permConfig . 'Eliminar');
    });

    Route::prefix('ventanillas/{id}')->group(function () use ($permConfig) {
        Route::post('/tipos-documentales', [VentanillaUnicaController::class, 'configurarTiposDocumentales'])->middleware('can:' . $permConfig . 'Editar');
        Route::get('/tipos-documentales', [VentanillaUnicaController::class, 'listarTiposDocumentales'])->middleware('can:' . $permConfig . 'Mostrar');
    });

    Route::prefix('ventanillas/{ventanillaId}')->group(function () use ($permConfig) {
        Route::post('/permisos', [PermisosVentanillaUnicaController::class, 'asignarPermisos'])->middleware('can:' . $permConfig . 'Editar');
        Route::get('/usuarios-permitidos', [PermisosVentanillaUnicaController::class, 'listarUsuariosPermitidos'])->middleware('can:' . $permConfig . 'Listar');
        Route::delete('/permisos/{usuarioId}', [PermisosVentanillaUnicaController::class, 'revocarPermisos'])->middleware('can:' . $permConfig . 'Editar');
    });

    Route::prefix('usuarios/{usuarioId}')->group(function () use ($permConfig) {
        Route::get('/ventanillas-permitidas', [PermisosVentanillaUnicaController::class, 'listarVentanillasPermitidas'])->middleware('can:' . $permConfig . 'Listar');
    });

    /**
     * Correspondencia Recibida (Radicaciones)
     */
    $permReci = 'Radicar -> Cores. Recibida -> ';
    Route::get('/radica-recibida/estadisticas', [VentanillaRadicaReciController::class, 'estadisticas'])->name('radica-recibida.estadisticas')->middleware('can:' . $permReci . 'Listar');
    Route::put('/radica-recibida/{id}/update-asunto', [VentanillaRadicaReciController::class, 'updateAsunto'])->name('radica-recibida.update-asunto')->middleware('can:' . $permReci . 'Actualizar asunto');
    Route::put('/radica-recibida/{id}/update-fechas', [VentanillaRadicaReciController::class, 'updateFechas'])->name('radica-recibida.update-fechas')->middleware('can:' . $permReci . 'Atualizar fechas de radicados');
    Route::put('/radica-recibida/{id}/update-clasificacion-documental', [VentanillaRadicaReciController::class, 'updateClasificacionDocumental'])->name('radica-recibida.update-clasificacion-documental')->middleware('can:' . $permReci . 'Actualizar clasificacion de radicados');
    Route::post('/radica-recibida/{id}/notificacion', [VentanillaRadicaReciController::class, 'enviarNotificacion'])->name('radica-recibida.notificacion')->middleware('can:' . $permReci . 'Notificar Email');
    Route::get('/radica-recibida/{id}/linea-tiempo', [VentanillaRadicaReciController::class, 'lineaTiempo'])->name('radica-recibida.linea-tiempo')->middleware('can:' . $permReci . 'Mostrar');
    Route::get('/radica-recibida', [VentanillaRadicaReciController::class, 'index'])->name('radica-recibida.index')->middleware('can:' . $permReci . 'Listar');
    Route::post('/radica-recibida', [VentanillaRadicaReciController::class, 'store'])->name('radica-recibida.store')->middleware('can:' . $permReci . 'Crear');
    Route::get('/radica-recibida/{id}', [VentanillaRadicaReciController::class, 'show'])->name('radica-recibida.show')->middleware('can:' . $permReci . 'Mostrar');
    Route::put('/radica-recibida/{id}', [VentanillaRadicaReciController::class, 'update'])->name('radica-recibida.update')->middleware('can:' . $permReci . 'Editar');
    Route::delete('/radica-recibida/{id}', [VentanillaRadicaReciController::class, 'destroy'])->name('radica-recibida.destroy')->middleware('can:' . $permReci . 'Eliminar');

    Route::prefix('radica-recibida/{id}')->name('radica-recibida.')->group(function () use ($permReci) {
        Route::get('/archivos/adjuntos/listar', [VentanillaRadicaReciArchivosController::class, 'listarArchivosAdjuntos'])->name('archivos.adjuntos.listar')->middleware('can:' . $permReci . 'Mostrar');
        Route::get('/archivos/adjuntos/{archivoId}/descargar', [VentanillaRadicaReciArchivosController::class, 'descargarArchivoAdjunto'])->name('archivos.adjuntos.descargar')->middleware('can:' . $permReci . 'Mostrar');
        Route::delete('/archivos/adjuntos/{archivoId}/eliminar', [VentanillaRadicaReciArchivosController::class, 'eliminarArchivoAdjunto'])->name('archivos.adjuntos.eliminar')->middleware('can:' . $permReci . 'Eliminar adjuntos');
        Route::get('/archivos/historial/archivos-eliminados', [VentanillaRadicaReciArchivosController::class, 'historialEliminaciones'])->name('archivos.historial.eliminaciones')->middleware('can:' . $permReci . 'Mostrar');
        Route::get('/archivos/info/', [VentanillaRadicaReciArchivosController::class, 'getFileInfo'])->name('archivos.info')->middleware('can:' . $permReci . 'Mostrar');
        Route::post('/archivos/upload-digital', [VentanillaRadicaReciArchivosController::class, 'upload'])->name('archivos.upload.digital')->middleware('can:' . $permReci . 'Subir digital');
        Route::post('/archivos/upload-adjuntos', [VentanillaRadicaReciArchivosController::class, 'subirArchivosAdjuntos'])->name('archivos.upload-adjuntos')->middleware('can:' . $permReci . 'Subir adjuntos');
        Route::get('/archivos/download', [VentanillaRadicaReciArchivosController::class, 'download'])->name('archivos.download')->middleware('can:' . $permReci . 'Mostrar');
        Route::delete('/archivos/delete', [VentanillaRadicaReciArchivosController::class, 'deleteFile'])->name('archivos.delete')->middleware('can:' . $permReci . 'Eliminar digital');
    });

    Route::apiResource('responsables', VentanillaRadicaReciResponsaController::class)->except('create', 'edit')->middleware('can:' . $permReci . 'Editar');
    Route::get('/radica-recibida/{radica_reci_id}/responsables', [VentanillaRadicaReciResponsaController::class, 'getByRadicado'])->name('radica-recibida.responsables.listar')->middleware('can:' . $permReci . 'Editar');
    Route::post('/radica-recibida/{radica_reci_id}/responsables', [VentanillaRadicaReciResponsaController::class, 'assignToRadicado'])->name('radica-recibida.responsables.asignar')->middleware('can:' . $permReci . 'Editar');

    /**
     * Correspondencia Enviada (Radicaciones)
     */
    $permEnvi = 'Radicar -> Cores. Enviada -> ';
    Route::get('/radica-enviada/estadisticas', [VentanillaRadicaEnviadosController::class, 'estadisticas'])->name('radica-enviada.estadisticas')->middleware('can:' . $permEnvi . 'Listar');
    Route::put('/radica-enviada/{id}/update-asunto', [VentanillaRadicaEnviadosController::class, 'updateAsunto'])->name('radica-enviada.update-asunto')->middleware('can:' . $permEnvi . 'Actualizar asunto');
    Route::put('/radica-enviada/{id}/update-fechas', [VentanillaRadicaEnviadosController::class, 'updateFechas'])->name('radica-enviada.update-fechas')->middleware('can:' . $permEnvi . 'Atualizar fechas de radicados');
    Route::put('/radica-enviada/{id}/update-clasificacion-documental', [VentanillaRadicaEnviadosController::class, 'updateClasificacionDocumental'])->name('radica-enviada.update-clasificacion-documental')->middleware('can:' . $permEnvi . 'Actualizar clasificacion de radicados');
    Route::post('/radica-enviada/{id}/notificacion', [VentanillaRadicaEnviadosController::class, 'enviarNotificacion'])->name('radica-enviada.notificacion')->middleware('can:' . $permEnvi . 'Notificar Email');
    Route::get('/radica-enviada/{id}/linea-tiempo', [VentanillaRadicaEnviadosController::class, 'lineaTiempo'])->name('radica-enviada.linea-tiempo')->middleware('can:' . $permEnvi . 'Mostrar');
    Route::get('/radica-enviada', [VentanillaRadicaEnviadosController::class, 'index'])->name('radica-enviada.index')->middleware('can:' . $permEnvi . 'Listar');
    Route::post('/radica-enviada', [VentanillaRadicaEnviadosController::class, 'store'])->name('radica-enviada.store')->middleware('can:' . $permEnvi . 'Crear');
    Route::get('/radica-enviada/{id}', [VentanillaRadicaEnviadosController::class, 'show'])->name('radica-enviada.show')->middleware('can:' . $permEnvi . 'Mostrar');
    Route::put('/radica-enviada/{id}', [VentanillaRadicaEnviadosController::class, 'update'])->name('radica-enviada.update')->middleware('can:' . $permEnvi . 'Editar');
    Route::delete('/radica-enviada/{id}', [VentanillaRadicaEnviadosController::class, 'destroy'])->name('radica-enviada.destroy')->middleware('can:' . $permEnvi . 'Eliminar');

    Route::prefix('radica-enviada/{id}')->name('radica-enviada.')->group(function () use ($permEnvi) {
        Route::get('/archivos/adjuntos/listar', [VentanillaRadicaEnviadosArchivosController::class, 'listarArchivosAdjuntos'])->name('archivos.adjuntos.listar')->middleware('can:' . $permEnvi . 'Mostrar');
        Route::get('/archivos/historial/archivos-eliminados', [VentanillaRadicaEnviadosArchivosController::class, 'historialEliminaciones'])->name('archivos.historial.eliminaciones')->middleware('can:' . $permEnvi . 'Mostrar');
        Route::get('/archivos/adjuntos/{archivoId}/descargar', [VentanillaRadicaEnviadosArchivosController::class, 'descargarArchivoAdjunto'])->name('archivos.adjuntos.descargar')->middleware('can:' . $permEnvi . 'Mostrar');
        Route::delete('/archivos/adjuntos/{archivoId}/eliminar', [VentanillaRadicaEnviadosArchivosController::class, 'eliminarArchivoAdjunto'])->name('archivos.adjuntos.eliminar')->middleware('can:' . $permEnvi . 'Eliminar adjuntos');
        Route::get('/archivos/info/', [VentanillaRadicaEnviadosArchivosController::class, 'getFileInfo'])->name('archivos.info')->middleware('can:' . $permEnvi . 'Mostrar');
        Route::post('/archivos/upload-digital', [VentanillaRadicaEnviadosArchivosController::class, 'upload'])->name('archivos.upload.digital')->middleware('can:' . $permEnvi . 'Subir digital');
        Route::post('/archivos/upload-adjuntos', [VentanillaRadicaEnviadosArchivosController::class, 'subirArchivosAdjuntos'])->name('archivos.upload-adjuntos')->middleware('can:' . $permEnvi . 'Subir adjuntos');
        Route::get('/archivos/download', [VentanillaRadicaEnviadosArchivosController::class, 'download'])->name('archivos.download')->middleware('can:' . $permEnvi . 'Mostrar');
        Route::delete('/archivos/delete', [VentanillaRadicaEnviadosArchivosController::class, 'deleteFile'])->name('archivos.delete')->middleware('can:' . $permEnvi . 'Eliminar digital');
    });

    Route::apiResource('responsables-enviados', VentanillaRadicaEnviadosResponsaController::class)->except('create', 'edit')->middleware('can:' . $permEnvi . 'Editar');
    Route::get('/radica-enviada/{radica_enviado_id}/responsables', [VentanillaRadicaEnviadosResponsaController::class, 'getByRadicado'])->name('radica-enviada.responsables.listar')->middleware('can:' . $permEnvi . 'Editar');
    Route::post('/radica-enviada/{radica_enviado_id}/responsables', [VentanillaRadicaEnviadosResponsaController::class, 'assignToRadicado'])->name('radica-enviada.responsables.asignar')->middleware('can:' . $permEnvi . 'Editar');

    Route::apiResource('firmantes-enviados', VentanillaRadicaEnviadosFirmantesController::class)->except('create', 'edit')->middleware('can:' . $permEnvi . 'Editar');
    Route::get('/radica-enviada/{radica_enviado_id}/firmantes', [VentanillaRadicaEnviadosFirmantesController::class, 'getByRadicado'])->name('radica-enviada.firmantes.listar')->middleware('can:' . $permEnvi . 'Editar');
    Route::post('/radica-enviada/{radica_enviado_id}/firmantes', [VentanillaRadicaEnviadosFirmantesController::class, 'assignToRadicado'])->name('radica-enviada.firmantes.asignar')->middleware('can:' . $permEnvi . 'Editar');

    Route::apiResource('proyectores-enviados', VentanillaRadicaEnviadosProyectoresController::class)->except('create', 'edit')->middleware('can:' . $permEnvi . 'Editar');
    Route::get('/radica-enviada/{radica_enviado_id}/proyectores', [VentanillaRadicaEnviadosProyectoresController::class, 'getByRadicado'])->name('radica-enviada.proyectores.listar')->middleware('can:' . $permEnvi . 'Editar');
    Route::post('/radica-enviada/{radica_enviado_id}/proyectores', [VentanillaRadicaEnviadosProyectoresController::class, 'assignToRadicado'])->name('radica-enviada.proyectores.asignar')->middleware('can:' . $permEnvi . 'Editar');
});
