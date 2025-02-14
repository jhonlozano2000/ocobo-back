<?php

use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDController;
use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDVersionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::resource('/trd', ClasificacionDocumentalTRDController::class)->except('create', 'edit');
    Route::post('/import-trd', [ClasificacionDocumentalTRDController::class, 'importarTRD'])->name("import.trd");
    Route::get('/estadisticas-trd/{id}', [ClasificacionDocumentalTRDController::class, 'estadistica'])->name('estadisticas.trd');
    Route::get('/dependencia/{id}', [ClasificacionDocumentalTRDController::class, 'listarPorDependencia']);

    Route::get('clasificacion/pendientes-aprobar', [ClasificacionDocumentalTRDVersionController::class, 'listarPendientesPorAprobar'])->name('pendiente.aprobar');
    Route::post('clasificacion/aprobar/{id}', [ClasificacionDocumentalTRDVersionController::class, 'aprobarVersion'])->name('aprobarVersion');
});
