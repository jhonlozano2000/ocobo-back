<?php

use App\Http\Controllers\OfiArchivo\OfiArchivoPrestamoController;
use Illuminate\Support\Facades\Route;

$perm = 'Gestion de Archivo -> Prestamos -> ';

Route::middleware('auth:sanctum')->group(function () use ($perm) {
    Route::get('prestamos', [OfiArchivoPrestamoController::class, 'index'])
        ->middleware('can:'.$perm.'Ver');
    Route::post('prestamos', [OfiArchivoPrestamoController::class, 'store'])
        ->middleware('can:'.$perm.'Crear');
    Route::get('prestamos/{id}', [OfiArchivoPrestamoController::class, 'show'])
        ->middleware('can:'.$perm.'Ver');
    Route::post('prestamos/{id}/devolver', [OfiArchivoPrestamoController::class, 'devolver'])
        ->middleware('can:'.$perm.'Devolver');
    Route::get('prestamos-estadisticas', [OfiArchivoPrestamoController::class, 'estadisticas'])
        ->middleware('can:'.$perm.'Ver');
});
