<?php

use App\Http\Controllers\Configuracion\ConfigCalendarioFestivoController;
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

/**
 * Rutas del módulo Configuración.
 *
 * Middleware global: auth:sanctum + throttle:config-operations
 * Autorización granular: can:'Config - {Submodulo} -> {Acción}'
 *
 * @author Jhon Javer Lozano Arce
 *
 * @date 2026-08-24
 */
Route::middleware(['auth:sanctum', 'throttle:config-operations'])->group(function () {

    // ────────────────────────────────────────────────────────────────
    // DIVISIÓN POLÍTICA
    // ────────────────────────────────────────────────────────────────
    $p = 'Config - División política -> ';

    Route::get('/division-politica/estadisticas', [ConfigDiviPoliController::class, 'estadisticas'])->name('divipoli.estadisticas')->middleware('can:'.$p.'Listar');
    Route::get('/division-politica/{id}/recursivo', [ConfigDiviPoliController::class, 'cargarRecursivo'])->name('divipoli.recursivo')->middleware('can:'.$p.'Mostrar');

    Route::get('/division-politica', [ConfigDiviPoliController::class, 'index'])->name('divipoli.index')->middleware('can:'.$p.'Listar');
    Route::post('/division-politica', [ConfigDiviPoliController::class, 'store'])->name('divipoli.store')->middleware('can:'.$p.'Crear');
    Route::get('/division-politica/{id}', [ConfigDiviPoliController::class, 'show'])->name('divipoli.show')->middleware('can:'.$p.'Mostrar');
    Route::put('/division-politica/{id}', [ConfigDiviPoliController::class, 'update'])->name('divipoli.update')->middleware('can:'.$p.'Editar');
    Route::delete('/division-politica/{id}', [ConfigDiviPoliController::class, 'destroy'])->name('divipoli.destroy')->middleware('can:'.$p.'Eliminar');

    Route::get('/division-politica/list/paises', [ConfigDiviPoliController::class, 'paises'])->name('divipoli.list.paises')->middleware('can:'.$p.'Listar');
    Route::get('/division-politica/list/departamentos/{paisId}', [ConfigDiviPoliController::class, 'departamentos'])->name('divipoli.list.departamentos')->middleware('can:'.$p.'Listar');
    Route::get('/division-politica/list/municipios/{departamentoId}', [ConfigDiviPoliController::class, 'municipios'])->name('divipoli.list.municipios')->middleware('can:'.$p.'Listar');
    Route::get('/division-politica/list/por-tipo/{tipo}', [ConfigDiviPoliController::class, 'listarPorTipo'])->name('divipoli.list.por.tipo')->middleware('can:'.$p.'Listar');
    Route::get('/division-politica/list/divi-poli-completa', [ConfigDiviPoliController::class, 'diviPoliCompleta'])->name('divipoli.list.divi.poli.completa')->middleware('can:'.$p.'Listar');

    // ────────────────────────────────────────────────────────────────
    // LISTAS
    // ────────────────────────────────────────────────────────────────
    $p = 'Config - Listas -> ';

    Route::get('/listas-detalles/activas/{lista_id}', [ConfigListaController::class, 'listasActivasDetalle'])->name('listas.activas')->middleware('can:'.$p.'Listar');
    Route::get('/listas-con-detalle', [ConfigListaController::class, 'listaDetalle'])->name('listas.detalle')->middleware('can:'.$p.'Listar');
    Route::get('/listas-cabeza', [ConfigListaController::class, 'listaCabeza'])->name('listas.cabeza')->middleware('can:'.$p.'Listar');

    Route::get('/listas', [ConfigListaController::class, 'index'])->name('listas.index')->middleware('can:'.$p.'Listar');
    Route::post('/listas', [ConfigListaController::class, 'store'])->name('listas.store')->middleware('can:'.$p.'Crear');
    Route::get('/listas/{lista}', [ConfigListaController::class, 'show'])->name('listas.show')->middleware('can:'.$p.'Mostrar');
    Route::put('/listas/{lista}', [ConfigListaController::class, 'update'])->name('listas.update')->middleware('can:'.$p.'Editar');
    Route::delete('/listas/{lista}', [ConfigListaController::class, 'destroy'])->name('listas.destroy')->middleware('can:'.$p.'Eliminar');

    Route::get('/listas-detalles/estadisticas', [ConfigListaDetalleController::class, 'estadisticas'])->name('listas.detalles.estadisticas')->middleware('can:'.$p.'Listar');

    Route::get('/listas-detalles', [ConfigListaDetalleController::class, 'index'])->name('listas_detalles.index')->middleware('can:'.$p.'Listar');
    Route::post('/listas-detalles', [ConfigListaDetalleController::class, 'store'])->name('listas_detalles.store')->middleware('can:'.$p.'Crear');
    Route::get('/listas-detalles/{lista_detalle}', [ConfigListaDetalleController::class, 'show'])->name('listas_detalles.show')->middleware('can:'.$p.'Mostrar');
    Route::put('/listas-detalles/{lista_detalle}', [ConfigListaDetalleController::class, 'update'])->name('listas_detalles.update')->middleware('can:'.$p.'Editar');
    Route::delete('/listas-detalles/{lista_detalle}', [ConfigListaDetalleController::class, 'destroy'])->name('listas_detalles.destroy')->middleware('can:'.$p.'Eliminar');

    // ────────────────────────────────────────────────────────────────
    // SERVIDORES DE ARCHIVOS
    // ────────────────────────────────────────────────────────────────
    $p = 'Config - Servidor de almacenamiento -> ';

    Route::get('servidores-archivos/estadisticas', [ConfigServerArchivoController::class, 'estadisticas'])->name('servidores.archivos.estadisticas')->middleware('can:'.$p.'Listar');

    Route::get('servidores-archivos', [ConfigServerArchivoController::class, 'index'])->name('servidores_archivos.index')->middleware('can:'.$p.'Listar');
    Route::post('servidores-archivos', [ConfigServerArchivoController::class, 'store'])->name('servidores_archivos.store')->middleware('can:'.$p.'Crear');
    Route::get('servidores-archivos/{config_server_archivo}', [ConfigServerArchivoController::class, 'show'])->name('servidores_archivos.show')->middleware('can:'.$p.'Mostrar');
    Route::put('servidores-archivos/{config_server_archivo}', [ConfigServerArchivoController::class, 'update'])->name('servidores_archivos.update')->middleware('can:'.$p.'Editar');
    Route::delete('servidores-archivos/{config_server_archivo}', [ConfigServerArchivoController::class, 'destroy'])->name('servidores_archivos.destroy')->middleware('can:'.$p.'Eliminar');

    // ────────────────────────────────────────────────────────────────
    // OTRAS CONFIGURACIONES
    // ────────────────────────────────────────────────────────────────
    $p = 'Config - Otras configuraciones -> ';

    Route::get('config-varias', [ConfigVariasController::class, 'index'])->name('config.varias.list');
    Route::post('config-varias', [ConfigVariasController::class, 'store'])->name('config.varias.store')->middleware('can:'.$p.'Editar');
    Route::put('config-varias/{clave}', [ConfigVariasController::class, 'update'])->name('config.varias.update')->middleware('can:'.$p.'Editar');
    Route::post('config-varias/batch', [ConfigVariasController::class, 'updateBatch'])->name('config.varias.update.batch')->middleware('can:'.$p.'Editar');

    Route::get('config-varias/numeracion-unificada', [ConfigVariasController::class, 'getNumeracionUnificada'])->name('config.varias.numeracion.unificada.get')->middleware('can:'.$p.'Listar');
    Route::put('config-varias/numeracion-unificada', [ConfigVariasController::class, 'updateNumeracionUnificada'])->name('config.varias.numeracion.unificada.update')->middleware('can:'.$p.'Editar');

    Route::get('config-num-radicado', [ConfigNumRadicadoController::class, 'getConfiguracion'])->name('config.num.radicado.getConfiguracion')->middleware('can:'.$p.'Listar');
    Route::put('config-num-radicado', [ConfigNumRadicadoController::class, 'updateConfiguracion'])->name('config.num.radicado.updateConfiguracion')->middleware('can:'.$p.'Editar');

    // ────────────────────────────────────────────────────────────────
    // SEDES
    // ────────────────────────────────────────────────────────────────
    $p = 'Config - Sedes -> ';

    Route::get('sedes-estadisticas', [ConfigSedeController::class, 'estadisticas'])->name('sedes.estadisticas')->middleware('can:'.$p.'Listar');
    Route::get('sedes-activas', [ConfigSedeController::class, 'sedesActivas'])->name('sedes.activas')->middleware('can:'.$p.'Listar');

    Route::get('sedes', [ConfigSedeController::class, 'index'])->name('sedes.index')->middleware('can:'.$p.'Listar');
    Route::post('sedes', [ConfigSedeController::class, 'store'])->name('sedes.store')->middleware('can:'.$p.'Crear');
    Route::get('sedes/{sede}', [ConfigSedeController::class, 'show'])->name('sedes.show')->middleware('can:'.$p.'Mostrar');
    Route::put('sedes/{sede}', [ConfigSedeController::class, 'update'])->name('sedes.update')->middleware('can:'.$p.'Editar');
    Route::delete('sedes/{sede}', [ConfigSedeController::class, 'destroy'])->name('sedes.destroy')->middleware('can:'.$p.'Eliminar');

    // ────────────────────────────────────────────────────────────────
    // VENTANILLAS DE CONFIGURACIÓN
    // ────────────────────────────────────────────────────────────────
    $p = 'Config - Ventanillas -> ';

    Route::get('/config-ventanillas/estadisticas', [ConfigVentanillasController::class, 'estadisticas'])->middleware('can:'.$p.'Listar');

    Route::get('config-ventanillas', [ConfigVentanillasController::class, 'index'])->middleware('can:'.$p.'Listar');
    Route::post('config-ventanillas', [ConfigVentanillasController::class, 'store'])->middleware('can:'.$p.'Crear');
    Route::get('config-ventanillas/{config_ventanilla}', [ConfigVentanillasController::class, 'show'])->middleware('can:'.$p.'Mostrar');
    Route::put('config-ventanillas/{config_ventanilla}', [ConfigVentanillasController::class, 'update'])->middleware('can:'.$p.'Editar');
    Route::delete('config-ventanillas/{config_ventanilla}', [ConfigVentanillasController::class, 'destroy'])->middleware('can:'.$p.'Eliminar');

    // Ventanillas dentro de una sede
    Route::get('sedes/{sedeId}/ventanillas', [VentanillaUnicaController::class, 'index'])->middleware('can:'.$p.'Listar');
    Route::post('sedes/{sedeId}/ventanillas', [VentanillaUnicaController::class, 'store'])->middleware('can:'.$p.'Crear');
    Route::get('sedes/{sedeId}/ventanillas/{id}', [VentanillaUnicaController::class, 'show'])->middleware('can:'.$p.'Mostrar');
    Route::put('sedes/{sedeId}/ventanillas/{id}', [VentanillaUnicaController::class, 'update'])->middleware('can:'.$p.'Editar');
    Route::delete('sedes/{sedeId}/ventanillas/{id}', [VentanillaUnicaController::class, 'destroy'])->middleware('can:'.$p.'Eliminar');

    // Permisos y tipos documentales de ventanillas
    Route::post('ventanillas/{ventanilla}/permisos', [PermisosVentanillaUnicaController::class, 'asignarPermisos'])->middleware('can:'.$p.'Editar');
    Route::get('usuarios/{usuario}/ventanillas', [PermisosVentanillaUnicaController::class, 'listarVentanillasPermitidas'])->middleware('can:'.$p.'Listar');
    Route::post('ventanillas/{ventanilla}/tipos-documentales', [VentanillaUnicaController::class, 'configurarTiposDocumentales'])->middleware('can:'.$p.'Editar');
    Route::get('ventanillas/{ventanilla}/tipos-documentales', [VentanillaUnicaController::class, 'listarTiposDocumentales'])->middleware('can:'.$p.'Listar');

    // ────────────────────────────────────────────────────────────────
    // CALENDARIO DE FESTIVOS
    // ────────────────────────────────────────────────────────────────
    $p = 'Config - Calendario -> ';

    Route::prefix('calendario-festivos')->group(function () use ($p) {
        Route::get('/', [ConfigCalendarioFestivoController::class, 'index'])->middleware('can:'.$p.'Listar');
        Route::post('/', [ConfigCalendarioFestivoController::class, 'store'])->middleware('can:'.$p.'Crear');
        Route::put('/{id}', [ConfigCalendarioFestivoController::class, 'update'])->middleware('can:'.$p.'Editar');
        Route::delete('/{id}', [ConfigCalendarioFestivoController::class, 'destroy'])->middleware('can:'.$p.'Eliminar');
        Route::get('/verificar/{fecha}', [ConfigCalendarioFestivoController::class, 'verificarFecha'])->middleware('can:'.$p.'Listar');
        Route::get('/anio/{anio}', [ConfigCalendarioFestivoController::class, 'festivosPorAnio'])->middleware('can:'.$p.'Listar');
        Route::post('/anio/{anio}/generar-colombia', [ConfigCalendarioFestivoController::class, 'generarFestivosColombia'])->middleware('can:'.$p.'Crear');
        Route::post('/calcular-vencimiento', [ConfigCalendarioFestivoController::class, 'calcularVencimiento'])->middleware('can:'.$p.'Listar');
        Route::post('/importar', [ConfigCalendarioFestivoController::class, 'importar'])->middleware('can:'.$p.'Crear');
        Route::post('/clear-cache', [ConfigCalendarioFestivoController::class, 'clearCache'])->middleware('can:'.$p.'Editar');
    });

}); // Fin throttle:config-operations

// ────────────────────────────────────────────────────────────────
// VENTANILLAS ÚNICAS (fuera del throttle general)
// ────────────────────────────────────────────────────────────────
$permConfig = 'Config - Ventanillas -> ';
Route::prefix('sedes/{sedeId}/ventanillas')->group(function () use ($permConfig) {
    Route::get('/', [VentanillaUnicaController::class, 'index'])->middleware('can:'.$permConfig.'Listar');
    Route::post('/', [VentanillaUnicaController::class, 'store'])->middleware('can:'.$permConfig.'Crear');
    Route::get('/{id}', [VentanillaUnicaController::class, 'show'])->middleware('can:'.$permConfig.'Mostrar');
    Route::put('/{id}', [VentanillaUnicaController::class, 'update'])->middleware('can:'.$permConfig.'Editar');
    Route::delete('/{id}', [VentanillaUnicaController::class, 'destroy'])->middleware('can:'.$permConfig.'Eliminar');
});

Route::prefix('ventanillas/{id}')->group(function () use ($permConfig) {
    Route::post('/tipos-documentales', [VentanillaUnicaController::class, 'configurarTiposDocumentales'])->middleware('can:'.$permConfig.'Editar');
    Route::get('/tipos-documentales', [VentanillaUnicaController::class, 'listarTiposDocumentales'])->middleware('can:'.$permConfig.'Mostrar');
});

Route::prefix('ventanillas/{ventanillaId}')->group(function () use ($permConfig) {
    Route::post('/permisos', [PermisosVentanillaUnicaController::class, 'asignarPermisos'])->middleware('can:'.$permConfig.'Editar');
    Route::get('/usuarios-permitidos', [PermisosVentanillaUnicaController::class, 'listarUsuariosPermitidos'])->middleware('can:'.$permConfig.'Listar');
    Route::delete('/permisos/{usuarioId}', [PermisosVentanillaUnicaController::class, 'revocarPermisos'])->middleware('can:'.$permConfig.'Editar');
});

Route::prefix('usuarios/{usuarioId}')->group(function () use ($permConfig) {
    Route::get('/ventanillas-permitidas', [PermisosVentanillaUnicaController::class, 'listarVentanillasPermitidas'])->middleware('can:'.$permConfig.'Listar');
});
