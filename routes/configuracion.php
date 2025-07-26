<?php

use App\Http\Controllers\Configuracion\ConfigDiviPoliController;
use App\Http\Controllers\Configuracion\ConfigListaController;
use App\Http\Controllers\Configuracion\ConfigListaDetalleController;
use App\Http\Controllers\Configuracion\ConfigNumRadicadoController;
use App\Http\Controllers\Configuracion\ConfigSedeController;
use App\Http\Controllers\Configuracion\ConfigServerArchivoController;
use App\Http\Controllers\Configuracion\ConfigVariasController;
use App\Http\Controllers\VentanillaUnica\PermisosVentanillaUnicaController;
use App\Http\Controllers\VentanillaUnica\VentanillaUnicaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    /**
     * División política
     */
    // Ruta para estadísticas de división política (debe ir antes del resource)
    Route::get('/divipoli/estadisticas', [ConfigDiviPoliController::class, 'estadisticas'])->name('divipoli.estadisticas');

    Route::apiResource('divipoli', ConfigDiviPoliController::class)->parameters(['divipoli' => 'config_divi_poli',])->names('divipoli')->except('create', 'edit');

    // Rutas para listar países, departamentos y municipios
    Route::get('/divipoli/list/paises', [ConfigDiviPoliController::class, 'paises'])->name('divipoli.list.paises');
    Route::get('/divipoli/list/departamentos/{paisId}', [ConfigDiviPoliController::class, 'departamentos'])->name('divipoli.list.departamentos');
    Route::get('/divipoli/list/municipios/{departamentoId}', [ConfigDiviPoliController::class, 'municipios'])->name('divipoli.list.municipios');

    // Ruta para obtener estructura jerárquica completa
    Route::get('/divipoli/list/divi-poli-completa', [ConfigDiviPoliController::class, 'diviPoliCompleta'])->name('divipoli.list.divi.poli.completa');

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
    Route::apiResource('sedes', ConfigSedeController::class)->except('create', 'edit');
    //Route::apiResource('ventanillas', ConfigVentanillasController::class)->except('create', 'edit');;
    Route::get('sedes-estadisticas', [ConfigSedeController::class, 'estadisticas'])->name('deses.estadisticas');

    // Rutas para Ventanillas dentro de una Sede
    Route::apiResource('sedes.ventanillas', VentanillaUnicaController::class)
        ->except(['create', 'edit']);

    // Asignación de permisos de usuarios a ventanillas
    Route::post('ventanillas/{ventanilla}/permisos', [PermisosVentanillaUnicaController::class, 'asignarPermisos']);
    Route::get('usuarios/{usuario}/ventanillas', [PermisosVentanillaUnicaController::class, 'listarVentanillasPermitidas']);

    // Configuración de tipos documentales permitidos en una ventanilla
    Route::post('ventanillas/{ventanilla}/tipos-documentales', [VentanillaUnicaController::class, 'configurarTiposDocumentales']);
    Route::get('ventanillas/{ventanilla}/tipos-documentales', [VentanillaUnicaController::class, 'listarTiposDocumentales']);

    // Configuración de numeración (unificada o por sede)
    Route::post('configuracion/numeracion', [ConfigSedeController::class, 'configurarNumeracion']);
    Route::get('configuracion/numeracion', [ConfigSedeController::class, 'obtenerConfiguracionNumeracion']);
});
