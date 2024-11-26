<?php

use App\Http\Controllers\Configuracion\ConfigDiviPoliController;
use App\Models\Configuracion\ConfigDiviPoli;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::resource('/divipoli', ConfigDiviPoliController::class)->except('create', 'edit');
    Route::get('/divipoli/list/paises', [ConfigDiviPoliController::class, 'paises'])->name('divipoli.list.paises');
    Route::get('/divipoli/list/departamentos/{paisId}', [ConfigDiviPoliController::class, 'departamento'])->name('divipoli.list.departamento');
    Route::get('/divipoli/list/municipios/{departamentoId}', [ConfigDiviPoliController::class, 'departamento'])->name('divipoli.list.municipios');
});
