<?php

use App\Http\Controllers\Api\Workflows\WorkflowController;
use App\Http\Controllers\Api\Workflows\WorkflowNodoController;
use App\Http\Controllers\Api\Workflows\WorkflowInstanciaController;
use App\Http\Controllers\Api\Workflows\WorkFlowTareaController;
use App\Http\Controllers\Api\Workflows\WorkFlowArchivoController;
use App\Http\Controllers\Api\Workflows\TareaController;
use App\Http\Controllers\Api\Workflows\TareaChecklistController;
use Illuminate\Support\Facades\Route;

// ==========================================
// RUTAS DE WORKFLOWS (ISO 27001 A.9, A.12)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('workflows', [WorkflowController::class, 'index']);
    Route::post('workflows', [WorkflowController::class, 'store']);
    Route::get('workflows/{workflow}', [WorkflowController::class, 'show']);
    Route::put('workflows/{workflow}', [WorkflowController::class, 'update']);
    Route::delete('workflows/{workflow}', [WorkflowController::class, 'destroy']);
    Route::post('workflows/{workflow}/duplicar', [WorkflowController::class, 'duplicar']);
    Route::patch('workflows/{workflow}/estado', [WorkflowController::class, 'cambiarEstado']);
    Route::post('workflows/{workflow}/canvas', [WorkflowController::class, 'guardarCanvas']);

    Route::get('workflows/{workflow}/nodos', [WorkflowNodoController::class, 'index']);
    Route::post('workflows/{workflow}/nodos', [WorkflowNodoController::class, 'store']);
    Route::put('workflows/{workflow}/nodos/{nodo}', [WorkflowNodoController::class, 'update']);
    Route::delete('workflows/{workflow}/nodos/{nodo}', [WorkflowNodoController::class, 'destroy']);

    Route::get('workflows/{workflow}/instancias', [WorkflowInstanciaController::class, 'index']);
    Route::post('workflows/{workflow}/instancias', [WorkflowInstanciaController::class, 'store']);
    Route::get('workflows/{workflow}/instancias/{instancia}', [WorkflowInstanciaController::class, 'show']);
    Route::post('workflows/{workflow}/instancias/{instancia}/ejecutar', [WorkflowInstanciaController::class, 'ejecutarNodo']);
    Route::patch('workflows/{workflow}/instancias/{instancia}/detener', [WorkflowInstanciaController::class, 'detener']);

    Route::get('workflows/{workflow}/nodos/{nodo}/tareas', [WorkFlowTareaController::class, 'index']);
    Route::post('workflows/{workflow}/nodos/{nodo}/tareas', [WorkFlowTareaController::class, 'store']);
    Route::get('workflows/{workflow}/nodos/{nodo}/tareas/{tarea}', [WorkFlowTareaController::class, 'show']);
    Route::put('workflows/{workflow}/nodos/{nodo}/tareas/{tarea}', [WorkFlowTareaController::class, 'update']);
    Route::delete('workflows/{workflow}/nodos/{nodo}/tareas/{tarea}', [WorkFlowTareaController::class, 'destroy']);
    Route::post('workflows/{workflow}/nodos/{nodo}/tareas/{tarea}/asignar', [WorkFlowTareaController::class, 'asignar']);
    Route::patch('workflows/{workflow}/nodos/{nodo}/tareas/{tarea}/estado', [WorkFlowTareaController::class, 'cambiarEstado']);
    Route::put('workflows/{workflow}/nodos/{nodo}/tareas/reordenar', [WorkFlowTareaController::class, 'reordenar']);

    Route::get('workflows/{workflow}/archivos', [WorkFlowArchivoController::class, 'index']);
    Route::post('workflows/{workflow}/archivos', [WorkFlowArchivoController::class, 'store']);
    Route::get('workflows/{workflow}/archivos/{archivo}/download', [WorkFlowArchivoController::class, 'download']);
    Route::delete('workflows/{workflow}/archivos/{archivo}', [WorkFlowArchivoController::class, 'destroy']);

    // Tareas (module-level, not nodo-level)
    Route::get('{workflow}/tareas', [TareaController::class, 'index']);
    Route::post('tareas', [TareaController::class, 'store']);
    Route::get('tareas/{tarea}', [TareaController::class, 'show']);
    Route::put('tareas/{tarea}', [TareaController::class, 'update']);
    Route::delete('tareas/{tarea}', [TareaController::class, 'destroy']);
    Route::post('tareas/{tarea}/completar', [TareaController::class, 'completar']);

    // Checklist items (scoped to parent tarea)
    Route::get('tareas/{tarea}/checklists', [TareaChecklistController::class, 'index']);
    Route::post('tareas/{tarea}/checklists', [TareaChecklistController::class, 'store']);
    Route::put('tareas/{tarea}/checklists/{checklist}', [TareaChecklistController::class, 'update']);
    Route::delete('tareas/{tarea}/checklists/{checklist}', [TareaChecklistController::class, 'destroy']);
    Route::put('tareas/{tarea}/checklists/reordenar', [TareaChecklistController::class, 'reordenar']);
});
