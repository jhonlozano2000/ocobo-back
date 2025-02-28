<?php

use App\Http\Controllers\ConfigSedeController;
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

    /**
     * Sedes
     */
    // 📌 Rutas para Sedes
    Route::apiResource('sedes', ConfigSedeController::class);

    // 📌 Rutas para Ventanillas dentro de una Sede
    Route::apiResource('sedes.ventanillas', VentanillaUnicaController::class)
        ->except(['create', 'edit']);

    // 📌 Asignación de permisos de usuarios a ventanillas
    Route::post('ventanillas/{ventanilla}/permisos', [PermisoVentanillaController::class, 'asignarPermisos']);
    Route::get('usuarios/{usuario}/ventanillas', [PermisoVentanillaController::class, 'listarVentanillasPermitidas']);

    // 📌 Configuración de tipos documentales permitidos en una ventanilla
    Route::post('ventanillas/{ventanilla}/tipos-documentales', [VentanillaUnicaController::class, 'configurarTiposDocumentales']);
    Route::get('ventanillas/{ventanilla}/tipos-documentales', [VentanillaUnicaController::class, 'listarTiposDocumentales']);

    // 📌 Configuración de numeración (unificada o por sede)
    Route::post('configuracion/numeracion', [ConfigSedeController::class, 'configurarNumeracion']);
    Route::get('configuracion/numeracion', [ConfigSedeController::class, 'obtenerConfiguracionNumeracion']);
});
