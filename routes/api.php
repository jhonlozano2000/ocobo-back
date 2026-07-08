<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Gestion\GestionTerceroController;
use App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteController;
use App\Http\Controllers\Transversal\FirmaElectronicaController;
use App\Http\Controllers\VentanillaUnica\DocumentoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - OCOBO v2.0
|--------------------------------------------------------------------------
*/

// Rutas públicas con rate limiting específico
Route::middleware('throttle:login')->post('/login', [AuthController::class, 'login']);
Route::middleware('throttle:register')->post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout']);

// Verificación de sesión activa (usado por GuestOnlyRoute)
Route::middleware('auth:sanctum')->get('/auth/check', function () {
    return response()->json(['authenticated' => true]);
});

// Usuario autenticado (usado por SessionGuard del frontend)
Route::middleware('auth:sanctum')->get('/getme', [AuthController::class, 'getMe']);

// ==========================================
// RUTAS DE GESTIÓN DE ARCHIVO (ISO 27001)
Route::middleware('auth:sanctum')->prefix('archivo')->group(function () {
    Route::get('/expedientes', [OfiArchivoExpedienteController::class, 'index']);
    Route::post('/expedientes', [OfiArchivoExpedienteController::class, 'store']);
    Route::post('/expedientes/{id}/incorporar', [OfiArchivoExpedienteController::class, 'incorporarDocumento']);
    Route::get('/expedientes/{id}', [OfiArchivoExpedienteController::class, 'show']);
    Route::put('/expedientes/{id}', [OfiArchivoExpedienteController::class, 'update']);
    Route::post('/expedientes/{id}/cerrar', [OfiArchivoExpedienteController::class, 'cerrarExpediente']);
    Route::post('/expedientes/{id}/archivos', [OfiArchivoExpedienteController::class, 'subirArchivos']);
    Route::post('/expedientes/{expedienteId}/documentos/{documentoId}/soft-delete', [OfiArchivoExpedienteController::class, 'softDeleteDocumento']);
    Route::get('/expedientes/{id}/indice', [OfiArchivoExpedienteController::class, 'generarIndicePdf']);

    // RUTAS DE GESTIÓN DE TERCEROS (VISTA 360)
    Route::get('/terceros/{identificacion}/historial', [GestionTerceroController::class, 'showHistory']);

    // RUTAS DE FIRMA ELECTRÓNICA (LEY 527)
    Route::prefix('firma-electronica')->group(function () {
        Route::post('/solicitar-otp', [FirmaElectronicaController::class, 'solicitarOtp']);
        Route::post('/firmar', [FirmaElectronicaController::class, 'firmarDocumento']);
    });

    // RUTAS SEGURAS DE DOCUMENTOS (ISO 27001)
    Route::get('/documentos/ver', [DocumentoController::class, 'verDocumento']);
});
