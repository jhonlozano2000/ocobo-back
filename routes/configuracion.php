<?php

use App\Http\Controllers\Configuracion\ConfigDiviPoliController;
use App\Http\Controllers\Configuracion\ConfigListaController;
use App\Http\Controllers\Configuracion\ConfigListaDetalleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    /**
     * División política
     */
    Route::resource('/divipoli', ConfigDiviPoliController::class)->except('create', 'edit');
    Route::get('/divipoli/list/paises', [ConfigDiviPoliController::class, 'paises'])->name('divipoli.list.paises');
    Route::get('/divipoli/list/departamentos/{paisId}', [ConfigDiviPoliController::class, 'departamentos'])->name('divipoli.list.departamentos');
    Route::get('/divipoli/list/municipios/{departamentoId}', [ConfigDiviPoliController::class, 'municipios'])->name('divipoli.list.municipios');

    /**
     * Listas
     */
    Route::apiResource('listas', ConfigListaController::class);
    Route::apiResource('listas-detalles', ConfigListaDetalleController::class);
});
