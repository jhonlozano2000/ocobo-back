<?php

use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\ComentarioController;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\CursorController;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\DocumentoController;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\DocumentoExportController;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\DocumentoImportController;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\FirmaDocumentoController;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\GrupoDocumentoController;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\PlantillaController;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\SugerenciaController;
use App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\VersionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:documentos'])->prefix('comunicaciones-recibidas')->group(function () {
    Route::post('/documentos/importar', [DocumentoImportController::class, 'importar']);
    Route::post('/documentos/importar-html', [DocumentoImportController::class, 'importarHtml']);

    Route::get('/documentos', [DocumentoController::class, 'index']);
    Route::get('/documentos/estadisticas', [DocumentoController::class, 'estadisticas']);
    Route::get('/logo-entidad', [DocumentoController::class, 'logoEntidad']);
    Route::post('/documentos', [DocumentoController::class, 'store']);

    // Plantillas para crear documentos: generadas (institucionales y personales) y cargadas en Gestión de Archivo
    Route::get('/plantillas', [PlantillaController::class, 'index']);
    Route::post('/plantillas', [PlantillaController::class, 'store']);
    Route::patch('/plantillas/{plantilla}', [PlantillaController::class, 'update']);
    Route::delete('/plantillas/{plantilla}', [PlantillaController::class, 'destroy']);
    Route::post('/plantillas/{plantilla}/editar', [PlantillaController::class, 'editar']);
    Route::post('/documentos/{documento}/guardar-plantilla', [PlantillaController::class, 'guardar']);
    Route::get('/plantillas-cargadas/{cargada}/descargar', [PlantillaController::class, 'descargarCargada']);
    Route::post('/plantillas-cargadas/{cargada}/usar', [PlantillaController::class, 'usarCargada']);

    Route::middleware('can:ver,documento')->group(function () {
        Route::get('/documentos/{documento}', [DocumentoController::class, 'show']);
        Route::get('/documentos/{documento}/contenido', [DocumentoController::class, 'obtenerContenido']);
        Route::get('/documentos/{documento}/combinacion', [PlantillaController::class, 'combinacion']);
        Route::get('/documentos/{documento}/cursores', [CursorController::class, 'obtenerCursores']);
        Route::get('/documentos/{documento}/comentarios', [ComentarioController::class, 'index']);
        Route::get('/documentos/{documento}/versiones', [VersionController::class, 'index']);
        Route::get('/documentos/{documento}/versiones/{versionIdA}/comparar/{versionIdB}', [VersionController::class, 'compararVersiones']);
        Route::get('/documentos/{documento}/sugerencias', [SugerenciaController::class, 'index']);
        Route::get('/documentos/{documento}/sugerencias/estadisticas', [SugerenciaController::class, 'estadisticas']);
        Route::get('/documentos/{documento}/exportar/{formato}', [DocumentoExportController::class, 'exportar'])
            ->whereIn('formato', ['pdf', 'docx', 'html', 'txt']);
    });

    Route::middleware(['can:editar,documento', 'throttle:sincronizacion'])->group(function () {
        Route::put('/documentos/{documento}', [DocumentoController::class, 'update']);
        Route::patch('/documentos/{documento}/configuracion', [DocumentoController::class, 'guardarConfiguracionPagina']);
        Route::post('/documentos/{documento}/sincronizar', [DocumentoController::class, 'sincronizar']);
        Route::post('/documentos/{documento}/cursores', [CursorController::class, 'actualizarCursor']);
        Route::post('/documentos/{documento}/comentarios', [ComentarioController::class, 'store']);
        Route::post('/documentos/{documento}/versiones', [DocumentoController::class, 'crearVersion']);
        Route::post('/documentos/{documento}/versiones/{versionId}/restaurar', [VersionController::class, 'restaurar']);
        Route::post('/documentos/{documento}/sugerencias', [SugerenciaController::class, 'store']);
        Route::post('/documentos/{documento}/sugerencias/{sugerencia}/aceptar', [SugerenciaController::class, 'aceptar']);
        Route::post('/documentos/{documento}/sugerencias/{sugerencia}/rechazar', [SugerenciaController::class, 'rechazar']);
        Route::delete('/documentos/{documento}/sugerencias/{sugerencia}', [SugerenciaController::class, 'destroy']);
    });

    // Firma con OTP: protege el documento; quién firma o retira las firmas lo decide el controlador
    Route::middleware('can:ver,documento')->group(function () {
        Route::get('/documentos/{documento}/firmas', [FirmaDocumentoController::class, 'index']);
        Route::post('/documentos/{documento}/firmas/solicitar-otp', [FirmaDocumentoController::class, 'solicitarOtp'])->middleware('throttle:firma-otp');
        Route::post('/documentos/{documento}/firmas', [FirmaDocumentoController::class, 'firmar'])->middleware('throttle:firma');
        Route::delete('/documentos/{documento}/firmas', [FirmaDocumentoController::class, 'retirar']);
        // Grupo colaborativo del documento (lo ve cualquier miembro)
        Route::get('/documentos/{documento}/grupo', [GrupoDocumentoController::class, 'show']);
    });

    Route::middleware('can:eliminar,documento')->group(function () {
        Route::delete('/documentos/{documento}', [DocumentoController::class, 'destroy']);
    });
});

// Antes el prefijo repetía «comentarios» (…/comentarios/comentarios/{id}) y el editor recibía 404
Route::middleware('auth:sanctum')->prefix('comunicaciones-recibidas')->group(function () {
    Route::delete('/comentarios/{comentario}', [ComentarioController::class, 'destroy']);
    Route::post('/comentarios/{comentario}/resolver', [ComentarioController::class, 'resolver']);
    Route::post('/comentarios/{comentario}/desresolver', [ComentarioController::class, 'desresolver']);
});
