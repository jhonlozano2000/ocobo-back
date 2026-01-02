<?php

use App\Http\Controllers\Configuracion\ConfigDiviPoliController;
use App\Http\Controllers\Configuracion\ConfigListaController;
use App\Http\Controllers\Configuracion\ConfigListaDetalleController;
use App\Http\Controllers\Configuracion\ConfigNumRadicadoController;
use App\Http\Controllers\Configuracion\ConfigSedeController;
use App\Http\Controllers\Configuracion\ConfigServerArchivoController;
use App\Http\Controllers\Configuracion\ConfigVariasController;
use App\Http\Controllers\Configuracion\ConfigVentanillasController;
use App\Http\Controllers\VentanillaUnica\PermisosVentanillaUnicaController;
use App\Http\Controllers\VentanillaUnica\VentanillaUnicaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    /**
     * División política
     */
    // Ruta para estadísticas de división política (debe ir antes del resource)
    Route::get('/division-politica/estadisticas', [ConfigDiviPoliController::class, 'estadisticas'])->name('divipoli.estadisticas');

    Route::apiResource('division-politica', ConfigDiviPoliController::class)->parameters(['divipoli' => 'config_divi_poli',])->names('divipoli')->except('create', 'edit');

    // Rutas para listar países, departamentos y municipios
    Route::get('/division-politica/list/paises', [ConfigDiviPoliController::class, 'paises'])->name('divipoli.list.paises');
    Route::get('/division-politica/list/departamentos/{paisId}', [ConfigDiviPoliController::class, 'departamentos'])->name('divipoli.list.departamentos');
    Route::get('/division-politica/list/municipios/{departamentoId}', [ConfigDiviPoliController::class, 'municipios'])->name('divipoli.list.municipios');
    Route::get('/division-politica/list/por-tipo/{tipo}', [ConfigDiviPoliController::class, 'listarPorTipo'])->name('divipoli.list.por.tipo');

    // Ruta para obtener estructura jerárquica completa
    Route::get('/division-politica/list/divi-poli-completa', [ConfigDiviPoliController::class, 'diviPoliCompleta'])->name('divipoli.list.divi.poli.completa');

    /**
     * Listas
     */
    // Ruta para obtener todas las listas maestras con el detalle activas
    Route::get('/listas-detalles/activas/{lista_id}', [ConfigListaController::class, 'listasActivasDetalle'])->name('listas.activas');
    // Ruta para obtener todas las listas con sus detalles
    Route::get('/listas-con-detalle', [ConfigListaController::class, 'listaDetalle'])->name('listas.detalle');
    // Ruta para obtener solo las listas (cabezas) sin detalles
    Route::get('/listas-cabeza', [ConfigListaController::class, 'listaCabeza'])->name('listas.cabeza');
    Route::apiResource('listas', ConfigListaController::class)->parameters(['listas' => 'lista'])->except('create', 'edit');

    // Ruta para estadísticas de detalles de listas (debe ir antes del resource)
    Route::get('/listas-detalles/estadisticas', [ConfigListaDetalleController::class, 'estadisticas'])->name('listas.detalles.estadisticas');

    Route::apiResource('listas-detalles', ConfigListaDetalleController::class)->parameters(['listas-detalles' => 'lista_detalle'])->except('create', 'edit');

    /**
     * Servidores de archivos
     */
    Route::apiResource('servidores-archivos', ConfigServerArchivoController::class)->parameters(['servidores-archivos' => 'config_server_archivo',])->except('create', 'edit');

    /**
     * Configuraciones varias
     */
    Route::get('config-varias', [ConfigVariasController::class, 'index'])->name('config.varias.list');
    Route::post('config-varias', [ConfigVariasController::class, 'store'])->name('config.varias.store');
    Route::put('config-varias/{clave}', [ConfigVariasController::class, 'update'])->name('config.varias.update');

    // Rutas específicas para numeración unificada
    Route::get('config-varias/numeracion-unificada', [ConfigVariasController::class, 'getNumeracionUnificada'])->name('config.varias.numeracion.unificada.get');
    Route::put('config-varias/numeracion-unificada', [ConfigVariasController::class, 'updateNumeracionUnificada'])->name('config.varias.numeracion.unificada.update');

    Route::get('config-num-radicado', [ConfigNumRadicadoController::class, 'getConfiguracion'])->name('config.num.radicado.getConfiguracion');
    Route::put('config-num-radicado', [ConfigNumRadicadoController::class, 'updateConfiguracion'])->name('config.num.radicado.updateConfiguracion');

    /**
     * Sedes
     */
    Route::apiResource('sedes', ConfigSedeController::class)->except('create', 'edit');
    //Route::apiResource('ventanillas', ConfigVentanillasController::class)->except('create', 'edit');;
    Route::get('sedes-estadisticas', [ConfigSedeController::class, 'estadisticas'])->name('deses.estadisticas');

    /**
     * Ventanillas de Configuración
     */
    Route::get('/config-ventanillas/estadisticas', [ConfigVentanillasController::class, 'estadisticas']);
    Route::apiResource('config-ventanillas', ConfigVentanillasController::class)->except('create', 'edit');

    // Rutas para Ventanillas dentro de una Sede
    Route::apiResource('sedes.ventanillas', VentanillaUnicaController::class)
        ->except(['create', 'edit']);

    // Asignación de permisos de usuarios a ventanillas
    Route::post('ventanillas/{ventanilla}/permisos', [PermisosVentanillaUnicaController::class, 'asignarPermisos']);
    Route::get('usuarios/{usuario}/ventanillas', [PermisosVentanillaUnicaController::class, 'listarVentanillasPermitidas']);

    // Configuración de tipos documentales permitidos en una ventanilla
    Route::post('ventanillas/{ventanilla}/tipos-documentales', [VentanillaUnicaController::class, 'configurarTiposDocumentales']);
    Route::get('ventanillas/{ventanilla}/tipos-documentales', [VentanillaUnicaController::class, 'listarTiposDocumentales']);
});
