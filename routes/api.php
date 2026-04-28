<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\Request;
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

// Ruta de prueba de sesión - REMOVER EN PRODUCCIÓN
Route::get('/test-session', function () {
    return response()->json([
        'warning' => 'Debug endpoint - remove in production',
        'session_id' => session()->getId(),
        'user_id' => auth()->id(),
        'logged_in' => auth()->check(),
    ]);
})->middleware('throttle:api');

// ==========================================
// RUTAS DE GESTIÓN DE ARCHIVO (ISO 27001)
// ==========================================
Route::middleware("auth:sanctum")->prefix("archivo")->group(function () {
    Route::get("/expedientes", [\App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteController::class, "index"]);
    Route::post("/expedientes", [\App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteController::class, "store"]);
    Route::post("/expedientes/{id}/incorporar", [\App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteController::class, "incorporarDocumento"]);
    Route::get("/expedientes/{id}/indice", [\App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteController::class, "generarIndicePdf"]);
    Route::middleware('auth:sanctum')->prefix('archivo')->group(function () {
        Route::get('/expedientes', [\App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteController::class, 'index']);
        Route::post('/expedientes', [\App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteController::class, 'store']);
        Route::post('/expedientes/{id}/incorporar', [\App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteController::class, 'incorporarDocumento']);
        Route::get('/expedientes/{id}/indice', [\App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteController::class, 'generarIndicePdf']);
    });

    // ==========================================
    // RUTAS DE GESTIÓN DE TERCEROS (VISTA 360)
    // ==========================================
    Route::middleware("auth:sanctum")->get("/terceros/{identificacion}/historial", [\App\Http\Controllers\Gestion\GestionTerceroController::class, "showHistory"]);
    Route::middleware('auth:sanctum')->get('/terceros/{identificacion}/historial', [\App\Http\Controllers\Gestion\GestionTerceroController::class, 'showHistory']);

    // ==========================================
    // RUTAS DE FIRMA ELECTRÓNICA (LEY 527)
    // ==========================================
    Route::middleware('auth:sanctum')->prefix('firma-electronica')->group(function () {
        Route::post('/solicitar-otp', [\App\Http\Controllers\Transversal\FirmaElectronicaController::class, 'solicitarOtp']);
        Route::post('/firmar', [\App\Http\Controllers\Transversal\FirmaElectronicaController::class, 'firmarDocumento']);
    });

    // ==========================================
    // RUTAS SEGURAS DE DOCUMENTOS (ISO 27001)
    // ==========================================
    Route::get("/documentos/ver", [\App\Http\Controllers\VentanillaUnica\DocumentoController::class, "verDocumento"]);

    Route::get('/documentos/ver', [\App\Http\Controllers\VentanillaUnica\DocumentoController::class, 'verDocumento']);
});
