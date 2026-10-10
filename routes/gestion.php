<?php

use App\Http\Controllers\Gestion\GestionTerceroController;
use Illuminate\Support\Facades\Route;

/**
 * M16-01: terceros exige permiso. Quien radica (recibida, enviada, PQRSF) también
 * consulta, crea y actualiza terceros desde el formulario de radicación.
 */
$p = 'Gestión - Terceros -> ';
$radicar = ['Radicar -> Cores. Recibida -> ', 'Radicar -> Cores. Enviada -> ', 'Radicar -> PQRSF -> '];
$conRadicar = fn (string $accionGestion, array $accionesRadicar) => 'can_any:'.implode(',', array_merge(
    [$p.$accionGestion],
    ...array_map(fn ($modulo) => array_map(fn ($accion) => $modulo.$accion, $accionesRadicar), $radicar)
));

Route::middleware('auth:sanctum')->group(function () use ($p, $conRadicar) {
    Route::get('terceros/estadistica', [GestionTerceroController::class, 'estadisticas'])->name('estadistica')->middleware('can:'.$p.'Listar');
    Route::get('terceros-estadistica', [GestionTerceroController::class, 'estadisticas'])->middleware('can:'.$p.'Listar');

    // M16-02: listado siempre paginado (máx. 100) y con límite de solicitudes
    Route::get('terceros', [GestionTerceroController::class, 'index'])->name('terceros.index')
        ->middleware([$conRadicar('Listar', ['Listar', 'Crear', 'Editar']), 'throttle:60,1']);
    Route::post('terceros', [GestionTerceroController::class, 'store'])->name('terceros.store')
        ->middleware($conRadicar('Crear', ['Crear']));
    Route::get('terceros/{tercero}', [GestionTerceroController::class, 'show'])->name('terceros.show')
        ->middleware([$conRadicar('Mostrar', ['Listar', 'Crear', 'Editar']), 'throttle:60,1']);
    Route::match(['put', 'patch'], 'terceros/{tercero}', [GestionTerceroController::class, 'update'])->name('terceros.update')
        ->middleware($conRadicar('Editar', ['Crear', 'Editar']));
    Route::delete('terceros/{tercero}', [GestionTerceroController::class, 'destroy'])->name('terceros.destroy')
        ->middleware('can:'.$p.'Eliminar');
});
