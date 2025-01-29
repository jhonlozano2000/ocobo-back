<?php

use App\Http\Controllers\Calidad\CalidadOrganigramaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::resource('organigrama', CalidadOrganigramaController::class)->except('edit', 'create');
    Route::get('organigrama-dependencias', [CalidadOrganigramaController::class, 'listDependencias']);
    Route::get('organigrama-oficinas', [CalidadOrganigramaController::class, 'listOficinas']);
});
