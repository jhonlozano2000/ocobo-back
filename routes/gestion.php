<?php

use App\Http\Controllers\Gestion\GestionTerceroController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::resource('terceros', GestionTerceroController::class)->except('create', 'edit');
    Route::get('terceros-estadistica', [GestionTerceroController::class, 'estadisticas'])->name('estadistica');
    Route::get('terceros-filter', [GestionTerceroController::class, 'filterTerceros']);
});
