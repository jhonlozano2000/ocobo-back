<?php

use App\Http\Controllers\Ia\IaAsistenteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de Asistencia IA para Documentos en Blanco (/api/ia/*)
|--------------------------------------------------------------------------
|
| Endpoints protegidos con Sanctum, control ABAC por dependencia del usuario
| y rate limiting específico para proteger los recursos de CPU del servidor local.
|
*/

Route::middleware(['auth:sanctum', 'throttle:ia-drafts'])->group(function () {
    // Sugerir texto inicial en documento en blanco a partir de una frase obligatoria
    Route::post('/sugerir-documento-blanco', [IaAsistenteController::class, 'sugerirDocumentoBlanco']);

    // Registrar trazabilidad de aceptación, edición, regeneración o descarte del borrador sugerido
    Route::post('/auditoria/{auditoriaId}/feedback', [IaAsistenteController::class, 'registrarFeedbackDocumentoBlanco'])
        ->whereNumber('auditoriaId');
});
