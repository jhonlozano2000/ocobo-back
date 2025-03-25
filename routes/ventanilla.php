<?php

use App\Http\Controllers\VentanillaUnida\VentanillaRadicaReciController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    /**
     * Correspondencia recibida
     */
    Route::apiResource('radica-recibida', VentanillaRadicaReciController::class)->except('create', 'edit');

    /**
     * Responsables
     */
});
