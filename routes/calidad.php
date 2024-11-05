<?php

use App\Http\Controllers\Calidad\Organigrama\CalidadOrganiCargoController;
use App\Http\Controllers\Calidad\Organigrama\CalidadOrganiDependenciaController;
use App\Http\Controllers\Calidad\Organigrama\CalidadOrganiOficinaController;
use Illuminate\Support\Facades\Route;

Route::resource('dependencias', CalidadOrganiDependenciaController::class)->except('create', 'edit');
Route::middleware('auth:sanctum')->group(function () {
    Route::resource('oficinas', CalidadOrganiOficinaController::class)->except('create', 'edit');
    Route::resource('cargos', CalidadOrganiCargoController::class)->except('create', 'edit');

    Route::get('getalldependencias', [CalidadOrganiDependenciaController::class, 'getAllDependencia']);
});
