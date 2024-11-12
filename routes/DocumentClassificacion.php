<?php

use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::resource('/trd', ClasificacionDocumentalTRDController::class)->except('create', 'edit');
    Route::get('/import-trd', [ClasificacionDocumentalTRDController::class, 'importTRD']);
    Route::get('/estadisticas-trd/{id}', [ClasificacionDocumentalTRDController::class, 'estadistica']);
});
