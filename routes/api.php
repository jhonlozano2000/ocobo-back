<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Gestion\GestionTerceroController;
use App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteController;
use App\Http\Controllers\OfiArchivo\OfiArchivoExpedienteTerceroController;
use App\Http\Controllers\OfiArchivo\OfiArchivoPendientesController;
use App\Http\Controllers\OfiArchivo\OfiArchivoSugerenciaController;
use App\Http\Controllers\Transversal\FirmaElectronicaController;
use App\Http\Controllers\VentanillaUnica\DocumentoController;
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
Route::middleware('throttle:forgot-password')->post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::middleware('throttle:forgot-password')->post('/reset-password', [AuthController::class, 'resetPassword']);
Route::middleware(['auth:sanctum', 'throttle:login'])->post('/logout', [AuthController::class, 'logout']);

// Verificación de sesión activa (usado por GuestOnlyRoute)
Route::middleware('auth:sanctum')->get('/auth/check', function () {
    return response()->json(['authenticated' => true]);
});

// Usuario autenticado (usado por SessionGuard del frontend)
Route::middleware('auth:sanctum')->get('/getme', [AuthController::class, 'getMe']);

// Verificación de permisos server-side (OWASP A01 - Broken Access Control)
Route::middleware('auth:sanctum')->post('/auth/check-permissions', function (Request $request) {
    $user = $request->user();
    $permissions = $request->input('permissions', []);

    if (empty($permissions)) {
        $all = $user->getAllPermissions();
        $results = [];
        foreach ($all as $perm) {
            $results[$perm->name] = true;
        }
    } else {
        $results = [];
        foreach ($permissions as $perm) {
            $results[$perm] = $user->can($perm);
        }
    }

    return response()->json(['data' => $results]);
});

// Rutas de 2FA
Route::middleware('auth:sanctum')->prefix('2fa')->group(function () {
    Route::get('/setup', [TwoFactorController::class, 'setup']);
    Route::post('/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:2fa-confirm');
    Route::post('/disable', [TwoFactorController::class, 'disable'])->middleware('throttle:2fa-disable');
});

Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->middleware('throttle:2fa-verify');

// ==========================================
// RUTAS DE GESTIÓN DE ARCHIVO (ISO 27001)
$permExp = 'Gestion de Archivo -> Expedientes -> ';

Route::middleware(['auth:sanctum', 'throttle:archivo'])->prefix('archivo')->group(function () use ($permExp) {
    Route::get('/expedientes', [OfiArchivoExpedienteController::class, 'index'])->middleware('can:'.$permExp.'Ver');
    Route::post('/expedientes', [OfiArchivoExpedienteController::class, 'store'])->middleware('can:'.$permExp.'Crear');
    Route::post('/expedientes/{id}/incorporar', [OfiArchivoExpedienteController::class, 'incorporarDocumento'])->middleware('can:'.$permExp.'Incorporar');
    // B09: panel "Expediente" del radicado recibido y de la PQRS (diseño §6.1)
    Route::get('/expedientes/sugerencias', OfiArchivoSugerenciaController::class)->middleware('can:'.$permExp.'Ver');
    // Bandeja "Pendientes de archivar" (diseño §3.4 y §6.1); va antes de /expedientes/{id}
    Route::get('/expedientes/pendientes-archivar', [OfiArchivoPendientesController::class, 'index'])->middleware('can:'.$permExp.'Ver');
    Route::post('/expedientes/pendientes-archivar/instancias/{instanciaId}/reintentar', [OfiArchivoPendientesController::class, 'reintentar'])->whereNumber('instanciaId')->middleware('can:'.$permExp.'Incorporar');
    Route::get('/expedientes/{id}', [OfiArchivoExpedienteController::class, 'show'])->middleware('can:'.$permExp.'Ver');
    Route::put('/expedientes/{id}', [OfiArchivoExpedienteController::class, 'update'])->middleware('can:'.$permExp.'Editar');
    Route::post('/expedientes/{id}/cerrar', [OfiArchivoExpedienteController::class, 'cerrarExpediente'])->middleware('can:'.$permExp.'Cerrar');
    Route::post('/expedientes/{id}/reabrir', [OfiArchivoExpedienteController::class, 'reabrirExpediente'])->middleware('can:'.$permExp.'Reabrir');
    // B24: terceros relacionados con el expediente y su rol
    Route::get('/expedientes/{id}/terceros', [OfiArchivoExpedienteTerceroController::class, 'index'])->whereNumber('id')->middleware('can:'.$permExp.'Ver');
    Route::post('/expedientes/{id}/terceros', [OfiArchivoExpedienteTerceroController::class, 'store'])->whereNumber('id')->middleware('can:'.$permExp.'Editar');
    Route::delete('/expedientes/{id}/terceros/{vinculoId}', [OfiArchivoExpedienteTerceroController::class, 'destroy'])->whereNumber(['id', 'vinculoId'])->middleware('can:'.$permExp.'Editar');
    Route::post('/expedientes/{id}/archivos', [OfiArchivoExpedienteController::class, 'subirArchivos'])->middleware('can:'.$permExp.'Incorporar');
    Route::post('/expedientes/{expedienteId}/documentos/{documentoId}/soft-delete', [OfiArchivoExpedienteController::class, 'softDeleteDocumento'])->middleware('can:'.$permExp.'Editar');
    Route::get('/expedientes/{id}/indice', [OfiArchivoExpedienteController::class, 'generarIndicePdf'])->middleware('can:'.$permExp.'Ver');
    Route::get('/expedientes/{id}/indice-xml', [OfiArchivoExpedienteController::class, 'generarIndiceXml'])->middleware('can:'.$permExp.'Ver');
    Route::get('/expedientes/{id}/indice/versiones', [OfiArchivoExpedienteController::class, 'versionesIndice'])->middleware('can:'.$permExp.'Ver');
    Route::get('/expedientes/{id}/indice/versiones/{version}/pdf', [OfiArchivoExpedienteController::class, 'descargarVersionIndice'])->whereNumber('version')->middleware('can:'.$permExp.'Ver');
    Route::get('/expedientes/{id}/historial', [OfiArchivoExpedienteController::class, 'historial'])->middleware('can:'.$permExp.'Ver');
    Route::get('/expedientes/{id}/tipos-documentales', [OfiArchivoExpedienteController::class, 'tiposDocumentales'])->middleware('can:'.$permExp.'Ver');
    Route::put('/expedientes/{expedienteId}/documentos/{documentoId}/tipo-documental', [OfiArchivoExpedienteController::class, 'tipificarDocumento'])->middleware('can:'.$permExp.'Editar');

    // RUTAS DE GESTIÓN DE TERCEROS (VISTA 360)
    Route::get('/terceros/{identificacion}/historial', [GestionTerceroController::class, 'showHistory'])->middleware('can:Gestión - Terceros -> Mostrar');

    // RUTAS DE FIRMA ELECTRÓNICA (LEY 527)
    Route::prefix('firma-electronica')->group(function () {
        Route::post('/solicitar-otp', [FirmaElectronicaController::class, 'solicitarOtp'])->middleware('throttle:firma-otp');
        Route::post('/firmar', [FirmaElectronicaController::class, 'firmarDocumento'])->middleware('throttle:firma');
    });

    // RUTAS SEGURAS DE DOCUMENTOS (ISO 27001)
    Route::get('/documentos/ver', [DocumentoController::class, 'verDocumento'])->name('documentos.ver');
});
