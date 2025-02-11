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
    Route::post('radica-recibida/{id}/archivo', [VentanillaRadicaReciArchivosController::class, 'upload'])->name('radica.corres.recibida.upload');
    Route::get('radica-recibida/{id}/archivo', [VentanillaRadicaReciArchivosController::class, 'download'])->name('radica.corres.recibida.download');
    Route::delete('radica-recibida/{id}/archivo', [VentanillaRadicaReciArchivosController::class, 'deleteFile'])->name('radica.corres.recibida.deleteFile');

    /**
     * Responsables
     */
    Route::apiResource('radica-recibida-responsables', VentanillaRadicaReciResponsaController::class)->except('create', 'edit');
    Route::get('radica-recibida-recibida/{id}/responsables', [VentanillaRadicaReciResponsaController::class, 'getByRadicado']);
});
