<?php

use App\Http\Controllers\VentanillaUnida\VentanillaRadicaReciArchivosController;
use App\Http\Controllers\VentanillaUnida\VentanillaRadicaReciController;
use App\Http\Controllers\VentanillaUnida\VentanillaRadicaReciResponsaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    /**
     * Correspondencia recibida
     */
    Route::apiResource('radica-recibida', VentanillaRadicaReciController::class)->except('create', 'edit');
    Route::post('radica-recibida/{id}/archivo', [VentanillaRadicaReciArchivosController::class, 'upload'])->name('corres.recibida.upload');
    Route::get('radica-recibida/{id}/archivo', [VentanillaRadicaReciArchivosController::class, 'download'])->name('corres.recibida.download');
    Route::delete('radica-recibida/{id}/archivo', [VentanillaRadicaReciArchivosController::class, 'deleteFile'])->name('corres.recibida.deleteFile');

    /**
     * Responsables
     */
    Route::apiResource('radica-recibida-responsables', VentanillaRadicaReciResponsaController::class)->except('create', 'edit');
    Route::get('radica-recibida-recibida/{id}/responsables', [VentanillaRadicaReciResponsaController::class, 'getByRadicado']);
});
