<?php

use App\Http\Controllers\Ia\IaAsistenteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del Asistente de IA Local (OCOBO SGDEA v2.0)
|--------------------------------------------------------------------------
|
| Endpoints de asistencia documental, búsqueda RAG de precedentes y
| proyección asistida de borradores oficiales con control ABAC.
|
*/

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Consulta de estado y métricas de la IA local
    Route::get('/status', [IaAsistenteController::class, 'status']);

    // Búsqueda de precedentes institucionales en el índice RAG
    Route::post('/buscar-precedentes', [IaAsistenteController::class, 'buscarPrecedentes']);

    // Proyección de borrador de respuesta oficial asistido por LLM (Síncrono)
    Route::post('/proyectar-borrador', [IaAsistenteController::class, 'proyectarBorrador']);

    // Proyección asíncrona con notificación en tiempo real vía Reverb (F2-03)
    Route::post('/proyectar-borrador-async', [IaAsistenteController::class, 'proyectarBorradorAsync']);

    // Historial y trazabilidad de auditoría ISO 27001
    Route::get('/auditoria', [IaAsistenteController::class, 'historialAuditoria']);
});
