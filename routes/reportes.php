<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportesController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('unificado', [ReportesController::class, 'index']);
    Route::get('export', [ReportesController::class, 'export']);

    Route::get('programados', [ReportesController::class, 'programados']);
    Route::post('programados', [ReportesController::class, 'storeProgramado']);
    Route::put('programados/{id}', [ReportesController::class, 'updateProgramado']);
    Route::delete('programados/{id}', [ReportesController::class, 'destroyProgramado']);
    Route::post('programados/{id}/ejecutar', [ReportesController::class, 'ejecutarProgramado']);
});
