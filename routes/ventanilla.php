<?php

use Illuminate\Support\Facades\Route;

Route::middleware("auth:sanctum")->group(function () {
    /**
     * Gestión de PQRS (Ley 1755)
     */
    Route::prefix("pqrs")->group(function () {
        Route::get("/", [\App\Http\Controllers\VentanillaUnica\VentanillaPqrsController::class, "index"]);
        Route::post("/", [\App\Http\Controllers\VentanillaUnica\VentanillaPqrsController::class, "store"]);
        Route::post("/{id}/prorroga", [\App\Http\Controllers\VentanillaUnica\VentanillaPqrsController::class, "aplicarProrroga"]);
    });
});

