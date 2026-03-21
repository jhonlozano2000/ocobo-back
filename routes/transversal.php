<?php

use App\Http\Controllers\Firma\FirmaController;
use Illuminate\Support\Facades\Route;

Route::middleware("auth:sanctum")->prefix("firma-electronica")->group(function () {
    Route::post("/solicitar-otp", [FirmaController::class, "solicitarOtp"]);
    Route::post("/firmar", [FirmaController::class, "validarYFirmar"]);
});

