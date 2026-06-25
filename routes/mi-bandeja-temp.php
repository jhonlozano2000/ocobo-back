<?php

use App\Http\Controllers\MiBandeja\Temp\MiBandejaTempController;
use App\Http\Controllers\MiBandeja\Temp\MiBandejaTempGrupoResponsableController;
use App\Http\Controllers\MiBandeja\Temp\MiBandejaTempGrupoFirmanteController;
use App\Http\Controllers\MiBandeja\Temp\MiBandejaTempGrupoProyectorController;
use App\Http\Controllers\MiBandeja\Temp\MiBandejaTempGrupoAdjuntoController;
use App\Http\Controllers\MiBandeja\Temp\MiBandejaTempNotaController;
use App\Http\Controllers\MiBandeja\Temp\MiBandejaTempVersionController;
use App\Http\Controllers\MiBandeja\Temp\MisGruposActivosController;
use Illuminate\Support\Facades\Route;

$permMiBandeja = 'Mi Bandeja - Grupos Colaborativos -> ';

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('grupos-colaborativos')->name('mi-bandeja.temp.grupos.')->group(function () use ($permMiBandeja) {
    // mis-grupos-activos FIRST (before parameterized routes)
    Route::get('/mis-grupos-activos', [MisGruposActivosController::class, 'index'])
        ->name('mis-grupos-activos')
        ->middleware('can:'.$permMiBandeja.'Ver');

    // Grupo principal
    Route::get('/', [MiBandejaTempController::class, 'index'])->name('index')->middleware('can:'.$permMiBandeja.'Ver');
    Route::post('/', [MiBandejaTempController::class, 'store'])->name('store')->middleware('can:'.$permMiBandeja.'Crear');
    Route::get('/{id}', [MiBandejaTempController::class, 'show'])->name('show')->middleware('can:'.$permMiBandeja.'Ver');
    Route::put('/{id}', [MiBandejaTempController::class, 'update'])->name('update')->middleware('can:'.$permMiBandeja.'Editar');
    Route::delete('/{id}', [MiBandejaTempController::class, 'destroy'])->name('destroy')->middleware('can:'.$permMiBandeja.'Eliminar');

    // Acciones de flujo
    Route::post('/{id}/marcar-terminado', [MiBandejaTempController::class, 'marcarTerminado'])->name('marcar-terminado')->middleware('can:'.$permMiBandeja.'Ver');
    Route::post('/{id}/enviar-tramite', [MiBandejaTempController::class, 'enviarTramite'])->name('enviar-tramite')->middleware('can:'.$permMiBandeja.'Enviar Tramite');

    // Check-out / Check-in del documento
    Route::get('/{id}/check-out', [MiBandejaTempController::class, 'checkOut'])->name('check-out')->middleware('can:'.$permMiBandeja.'Ver');
    Route::post('/{id}/descargar-con-variables', [MiBandejaTempController::class, 'descargarConVariables'])->name('descargar-con-variables')->middleware('can:'.$permMiBandeja.'Ver');
    Route::get('/{id}/variables-plantilla', [MiBandejaTempController::class, 'variablesPlantilla'])->name('variables-plantilla')->middleware('can:'.$permMiBandeja.'Ver');
    Route::post('/{id}/check-in', [MiBandejaTempController::class, 'checkIn'])->name('check-in')->middleware('can:'.$permMiBandeja.'Ver');
    Route::post('/{id}/subir-version-inicial', [MiBandejaTempController::class, 'subirVersionInicial'])->name('subir-version-inicial')->middleware('can:'.$permMiBandeja.'Crear');

    Route::post('/{id}/liberar-bloqueo', [MisGruposActivosController::class, 'liberarBloqueo'])
        ->name('liberar-bloqueo')
        ->middleware('can:'.$permMiBandeja.'Ver');

    // Responsables
    Route::prefix('{grupo}/responsables')->name('responsables.')->group(function () use ($permMiBandeja) {
        Route::get('/', [MiBandejaTempGrupoResponsableController::class, 'index'])->name('index')->middleware('can:'.$permMiBandeja.'Ver');
        Route::post('/', [MiBandejaTempGrupoResponsableController::class, 'store'])->name('store')->middleware('can:'.$permMiBandeja.'Gestionar Miembros');
        Route::put('/{id}', [MiBandejaTempGrupoResponsableController::class, 'update'])->name('update')->middleware('can:'.$permMiBandeja.'Gestionar Miembros');
        Route::delete('/{id}', [MiBandejaTempGrupoResponsableController::class, 'destroy'])->name('destroy')->middleware('can:'.$permMiBandeja.'Gestionar Miembros');
        Route::post('/{id}/marcar-terminado', [MiBandejaTempGrupoResponsableController::class, 'marcarTerminado'])->name('marcar-terminado')->middleware('can:'.$permMiBandeja.'Ver');
    });

    // Firmantes
    Route::prefix('{grupo}/firmantes')->name('firmantes.')->group(function () use ($permMiBandeja) {
        Route::get('/', [MiBandejaTempGrupoFirmanteController::class, 'index'])->name('index')->middleware('can:'.$permMiBandeja.'Ver');
        Route::post('/', [MiBandejaTempGrupoFirmanteController::class, 'store'])->name('store')->middleware('can:'.$permMiBandeja.'Gestionar Miembros');
        Route::put('/{id}', [MiBandejaTempGrupoFirmanteController::class, 'update'])->name('update')->middleware('can:'.$permMiBandeja.'Gestionar Miembros');
        Route::delete('/{id}', [MiBandejaTempGrupoFirmanteController::class, 'destroy'])->name('destroy')->middleware('can:'.$permMiBandeja.'Gestionar Miembros');
        Route::post('/{id}/marcar-terminado', [MiBandejaTempGrupoFirmanteController::class, 'marcarTerminado'])->name('marcar-terminado')->middleware('can:'.$permMiBandeja.'Ver');
        Route::post('/{id}/firmar', [MiBandejaTempGrupoFirmanteController::class, 'firmar'])->name('firmar')->middleware('can:'.$permMiBandeja.'Gestionar Miembros');
    });

    // Proyectores
    Route::prefix('{grupo}/proyectores')->name('proyectores.')->group(function () use ($permMiBandeja) {
        Route::get('/', [MiBandejaTempGrupoProyectorController::class, 'index'])->name('index')->middleware('can:'.$permMiBandeja.'Ver');
        Route::post('/', [MiBandejaTempGrupoProyectorController::class, 'store'])->name('store')->middleware('can:'.$permMiBandeja.'Gestionar Miembros');
        Route::put('/{id}', [MiBandejaTempGrupoProyectorController::class, 'update'])->name('update')->middleware('can:'.$permMiBandeja.'Gestionar Miembros');
        Route::delete('/{id}', [MiBandejaTempGrupoProyectorController::class, 'destroy'])->name('destroy')->middleware('can:'.$permMiBandeja.'Gestionar Miembros');
        Route::post('/{id}/marcar-terminado', [MiBandejaTempGrupoProyectorController::class, 'marcarTerminado'])->name('marcar-terminado')->middleware('can:'.$permMiBandeja.'Ver');
    });

    // Anular grupo (solo creador)
    Route::post('/{id}/anular', [MiBandejaTempController::class, 'anular'])->name('anular')->middleware('can:'.$permMiBandeja.'Eliminar');

    // Adjuntos
    Route::prefix('{grupo}/adjuntos')->name('adjuntos.')->group(function () use ($permMiBandeja) {
        Route::get('/', [MiBandejaTempGrupoAdjuntoController::class, 'index'])->name('index')->middleware('can:'.$permMiBandeja.'Ver');
        Route::post('/', [MiBandejaTempGrupoAdjuntoController::class, 'store'])->name('store')->middleware('can:'.$permMiBandeja.'Subir Adjuntos');
        Route::delete('/{id}', [MiBandejaTempGrupoAdjuntoController::class, 'destroy'])->name('destroy')->middleware('can:'.$permMiBandeja.'Subir Adjuntos');
        Route::get('/{id}/descargar', [MiBandejaTempGrupoAdjuntoController::class, 'descargar'])->name('descargar')->middleware('can:'.$permMiBandeja.'Ver');
    });

    // Versiones del documento
    Route::prefix('{grupo}/versiones')->name('versiones.')->group(function () use ($permMiBandeja) {
        Route::get('/', [MiBandejaTempVersionController::class, 'index'])->name('index')->middleware('can:'.$permMiBandeja.'Ver');
        Route::get('/{versionId}/descargar', [MiBandejaTempVersionController::class, 'descargarVersion'])->name('descargar')->middleware('can:'.$permMiBandeja.'Ver');
    });

    // Notas / comentarios
    Route::prefix('{grupo}/notas')->name('notas.')->group(function () use ($permMiBandeja) {
        Route::get('/', [MiBandejaTempNotaController::class, 'index'])->name('index')->middleware('can:'.$permMiBandeja.'Ver');
        Route::post('/', [MiBandejaTempNotaController::class, 'store'])->name('store')->middleware('can:'.$permMiBandeja.'Ver');
    });
});
