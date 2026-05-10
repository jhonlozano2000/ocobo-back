<?php

use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\ComentarioController;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\CursorController;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\DocumentoController;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\DocumentoExportController;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\DocumentoImportController;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\VersionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('comunicaciones-recibidas')->group(function () {
    Route::post('/documentos/importar', [DocumentoImportController::class, 'importar']);
    Route::post('/documentos/importar-html', [DocumentoImportController::class, 'importarHtml']);

    Route::get('/documentos', [DocumentoController::class, 'index']);
    Route::post('/documentos', [DocumentoController::class, 'store']);

    Route::middleware('can:ver,documento')->group(function () {
        Route::get('/documentos/{documento}', [DocumentoController::class, 'show']);
        Route::get('/documentos/{documento}/contenido', [DocumentoController::class, 'obtenerContenido']);
        Route::get('/documentos/{documento}/cursores', [CursorController::class, 'obtenerCursores']);
        Route::get('/documentos/{documento}/comentarios', [ComentarioController::class, 'index']);
        Route::get('/documentos/{documento}/versiones', [VersionController::class, 'index']);
        Route::get('/documentos/{documento}/exportar/{formato}', [DocumentoExportController::class, 'exportar'])
            ->whereIn('formato', ['pdf', 'docx', 'html', 'txt']);
    });

    Route::middleware('can:editar,documento')->group(function () {
        Route::put('/documentos/{documento}', [DocumentoController::class, 'update']);
        Route::patch('/documentos/{documento}/configuracion', [DocumentoController::class, 'guardarConfiguracionPagina']);
        Route::post('/documentos/{documento}/sincronizar', [DocumentoController::class, 'sincronizar']);
        Route::post('/documentos/{documento}/cursores', [CursorController::class, 'actualizarCursor']);
        Route::post('/documentos/{documento}/comentarios', [ComentarioController::class, 'store']);
        Route::post('/documentos/{documento}/versiones', [DocumentoController::class, 'crearVersion']);
        Route::post('/documentos/{documento}/versiones/{versionId}/restaurar', [VersionController::class, 'restaurar']);
    });

    Route::middleware('can:gestionarUsuarios,documento')->group(function () {
        Route::post('/documentos/{documento}/usuarios', [DocumentoController::class, 'asignarUsuarios']);
    });

    Route::middleware('can:eliminar,documento')->group(function () {
        Route::delete('/documentos/{documento}', [DocumentoController::class, 'destroy']);
    });
});

Route::middleware('auth:sanctum')->prefix('comunicaciones-recibidas/comentarios')->group(function () {
    Route::delete('/comentarios/{comentario}', [ComentarioController::class, 'destroy']);
    Route::post('/comentarios/{comentario}/resolver', [ComentarioController::class, 'resolver']);
    Route::post('/comentarios/{comentario}/desresolver', [ComentarioController::class, 'desresolver']);
});