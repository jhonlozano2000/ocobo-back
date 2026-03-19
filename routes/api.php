<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthController::class, 'register'])
    ->withoutMiddleware(\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);
Route::post('/login', [AuthController::class, 'login'])
    ->withoutMiddleware(\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/getme', [AuthController::class, 'getMe']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

// ==========================================
// RUTAS DE GESTIÓN DE ARCHIVO (ISO 27001)
// ==========================================
Route::middleware('auth:sanctum')->prefix('archivo')->group(function () {
    Route::get('/expedientes', [\App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteController::class, 'index']);
    Route::post('/expedientes', [\App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteController::class, 'store']);
    Route::post('/expedientes/{id}/incorporar', [\App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteController::class, 'incorporarDocumento']);
    Route::get('/expedientes/{id}/indice', [\App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteController::class, 'generarIndicePdf']);
});

// ==========================================
// RUTAS DE GESTIÓN DE TERCEROS (VISTA 360)
// ==========================================
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
Route::get('/documentos/ver', [\App\Http\Controllers\VentanillaUnica\DocumentoController::class, 'verDocumento']);
