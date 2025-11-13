<?php

use App\Http\Controllers\VentanillaUnica\VentanillaRadicaReciController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaReciArchivosController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaReciResponsaController;
use App\Http\Controllers\VentanillaUnica\VentanillaUnicaController;
use App\Http\Controllers\VentanillaUnica\PermisosVentanillaUnicaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    /**
     * Ventanillas Únicas
     */
    Route::prefix('sedes/{sedeId}/ventanillas')->group(function () {
        Route::get('/', [VentanillaUnicaController::class, 'index']);
        Route::post('/', [VentanillaUnicaController::class, 'store']);
        Route::get('/{id}', [VentanillaUnicaController::class, 'show']);
        Route::put('/{id}', [VentanillaUnicaController::class, 'update']);
        Route::delete('/{id}', [VentanillaUnicaController::class, 'destroy']);
    });

    // Configuración de tipos documentales
    Route::prefix('ventanillas/{id}')->group(function () {
        Route::post('/tipos-documentales', [VentanillaUnicaController::class, 'configurarTiposDocumentales']);
        Route::get('/tipos-documentales', [VentanillaUnicaController::class, 'listarTiposDocumentales']);
    });

    /**
     * Permisos de Ventanillas
     */
    Route::prefix('ventanillas/{ventanillaId}')->group(function () {
        Route::post('/permisos', [PermisosVentanillaUnicaController::class, 'asignarPermisos']);
        Route::get('/usuarios-permitidos', [PermisosVentanillaUnicaController::class, 'listarUsuariosPermitidos']);
        Route::delete('/permisos/{usuarioId}', [PermisosVentanillaUnicaController::class, 'revocarPermisos']);
    });

    Route::prefix('usuarios/{usuarioId}')->group(function () {
        Route::get('/ventanillas-permitidas', [PermisosVentanillaUnicaController::class, 'listarVentanillasPermitidas']);
    });

    /**
     * Correspondencia Recibida (Radicaciones)
     */
    // Rutas específicas ANTES de la ruta apiResource para evitar conflictos
    Route::get('/radica-recibida/estadisticas', [VentanillaRadicaReciController::class, 'estadisticas']);
    Route::get('/radica-recibida-admin/listar', [VentanillaRadicaReciController::class, 'listarRadicados']);

    // Ruta específica para actualizar asunto
    Route::put('/radica-recibida/{id}/update-asunto', [VentanillaRadicaReciController::class, 'updateAsunto']);

    // Ruta específica para actualizar fechas (fecha de vencimiento y fecha del documento)
    Route::put('/radica-recibida/{id}/update-fechas', [VentanillaRadicaReciController::class, 'updateFechas']);

    // Ruta para actualizar clasificación documental
    Route::put('/radica-recibida/{id}/update-clasificacion-documental', [VentanillaRadicaReciController::class, 'updateClasificacionDocumental']);

    // Ruta para enviar notificaciones por correo electrónico
    Route::post('/radica-recibida/{id}/notificar', [VentanillaRadicaReciController::class, 'enviarNotificacion']);

    // Ruta apiResource (debe ir después de las rutas específicas)
    Route::apiResource('radica-recibida', VentanillaRadicaReciController::class)->except('create', 'edit');

    /**
     * Archivos de Radicaciones
     */
    Route::prefix('radica-recibida/{id}')->group(function () {
        // Rutas específicas para archivos adjuntos adicionales (DEBEN IR PRIMERO)
        Route::get('/archivos/adjuntos/listar', [VentanillaRadicaReciArchivosController::class, 'listarArchivosAdjuntos']);
        Route::get('/archivos/adjuntos/descargar', [VentanillaRadicaReciArchivosController::class, 'descargarArchivoAdjunto']);
        Route::delete('/archivos/adjuntos/eliminar', [VentanillaRadicaReciArchivosController::class, 'eliminarArchivoAdjunto']);

        // Rutas específicas para historial (también específicas)
        Route::get('/archivos/historial/archivos-eliminados', [VentanillaRadicaReciArchivosController::class, 'historialEliminaciones']);
        Route::get('/archivos/info/', [VentanillaRadicaReciArchivosController::class, 'getFileInfo']);

        // Rutas generales para archivo principal (van al final)
        Route::post('/archivos/upload', [VentanillaRadicaReciArchivosController::class, 'upload']);
        Route::post('/archivos/upload-adjuntos', [VentanillaRadicaReciArchivosController::class, 'subirArchivosAdjuntos']);
        Route::get('/archivos/download', [VentanillaRadicaReciArchivosController::class, 'download']);
        Route::delete('/archivos/delete', [VentanillaRadicaReciArchivosController::class, 'deleteFile']);
    });

    /**
     * Responsables de Radicaciones
     */
    Route::apiResource('responsables', VentanillaRadicaReciResponsaController::class)->except('create', 'edit');

    // Rutas específicas para responsables de radicaciones
    Route::get('/radica-recibida/{radica_reci_id}/responsables', [VentanillaRadicaReciResponsaController::class, 'getByRadicado']);
    Route::post('/radica-recibida/{radica_reci_id}/responsables', [VentanillaRadicaReciResponsaController::class, 'assignToRadicado']);
});
