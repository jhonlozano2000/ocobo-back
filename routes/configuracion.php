<?php

use App\Http\Controllers\Configuracion\ConfigDiviPoliController;
use App\Http\Controllers\Configuracion\ConfigListaController;
use App\Http\Controllers\Configuracion\ConfigListaDetalleController;
use App\Http\Controllers\Configuracion\ConfigNumRadicadoController;
use App\Http\Controllers\Configuracion\ConfigServerArchivoController;
use App\Http\Controllers\Configuracion\ConfigVariasController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    /**
     * División política
     */
    Route::apiResource('divipoli', ConfigDiviPoliController::class)->parameters(['divipoli' => 'config_divi_poli',])->except('create', 'edit');

    Route::get('/divipoli/list/paises', [ConfigDiviPoliController::class, 'paises'])->name('divipoli.list.paises');
    Route::get('/divipoli/list/departamentos/{paisId}', [ConfigDiviPoliController::class, 'departamentos'])->name('divipoli.list.departamentos');
    Route::get('/divipoli/list/municipios/{departamentoId}', [ConfigDiviPoliController::class, 'municipios'])->name('divipoli.list.municipios');

    /**
     * Listas
     */
    Route::apiResource('listas', ConfigListaController::class)->parameters(['listas' => 'lista'])->except('create', 'edit');
    Route::apiResource('listas-detalles', ConfigListaDetalleController::class)->parameters(['listas-detalles' => 'lista_detalle'])->except('create', 'edit');

    /**
     * Servidores de archivos
     */
    Route::apiResource('servidores-archivos', ConfigServerArchivoController::class)->parameters(['servidores-archivos' => 'config_server_archivo',])->except('create', 'edit');

    /**
     * Configuraciones varias
     */
    Route::get('config-varias', [ConfigVariasController::class, 'index'])->name('config.varias.list');
    Route::put('config-varias/{clave}', [ConfigVariasController::class, 'update'])->name('config.varias.update');

    Route::get('config-num-radicado', [ConfigNumRadicadoController::class, 'getConfiguracion'])->name('config.num.radicado.getConfiguracion');
    Route::put('config-num-radicado', [ConfigNumRadicadoController::class, 'updateConfiguracion'])->name('config.num.radicado.updateConfiguracion');
});
