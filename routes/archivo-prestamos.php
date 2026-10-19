<?php

use App\Http\Controllers\OfiArchivo\OfiArchivoPrestamoController;
use Illuminate\Support\Facades\Route;

$perm = 'Gestion de Archivo -> Prestamos -> ';

Route::middleware(['auth:sanctum', 'throttle:archivo'])->group(function () use ($perm) {
    // §3.10: cada quien ve sus solicitudes; el permiso Ver da todas. Solicitar (o el antiguo Crear)
    // y las acciones del archivo se validan en PrestamosService.
    Route::get('prestamos', [OfiArchivoPrestamoController::class, 'index']);
    Route::post('prestamos', [OfiArchivoPrestamoController::class, 'store']);
    Route::get('prestamos/expedientes', [OfiArchivoPrestamoController::class, 'expedientes']);
    Route::get('prestamos/{id}', [OfiArchivoPrestamoController::class, 'show'])->whereNumber('id');
    Route::post('prestamos/{id}/aprobar', [OfiArchivoPrestamoController::class, 'aprobar'])->whereNumber('id')
        ->middleware('can:'.$perm.'Aprobar');
    Route::post('prestamos/{id}/rechazar', [OfiArchivoPrestamoController::class, 'rechazar'])->whereNumber('id')
        ->middleware('can:'.$perm.'Aprobar');
    Route::post('prestamos/{id}/cancelar', [OfiArchivoPrestamoController::class, 'cancelar'])->whereNumber('id');
    Route::post('prestamos/{id}/entregar', [OfiArchivoPrestamoController::class, 'entregar'])->whereNumber('id')
        ->middleware('can:'.$perm.'Devolver');
    Route::post('prestamos/{id}/devolver', [OfiArchivoPrestamoController::class, 'devolver'])->whereNumber('id')
        ->middleware('can:'.$perm.'Devolver');
    Route::get('prestamos-estadisticas', [OfiArchivoPrestamoController::class, 'estadisticas'])
        ->middleware('can:'.$perm.'Ver');
});
