<?php

use App\Http\Controllers\OfiArchivo\OfiArchivoDisposicionController;
use Illuminate\Support\Facades\Route;

$perm = 'Gestion de Archivo -> Disposicion -> ';

Route::middleware(['auth:sanctum', 'throttle:archivo'])->group(function () use ($perm) {
    // B22: expedientes en disposición final desde el archivo central
    Route::get('disposicion/expedientes', [OfiArchivoDisposicionController::class, 'expedientes'])
        ->middleware('can:'.$perm.'Ver');

    // Selección (S): propuesta según el método de la TRD, ajuste y confirmación
    Route::get('disposicion/selecciones/pendientes', [OfiArchivoDisposicionController::class, 'pendientesSeleccion'])
        ->middleware('can:'.$perm.'Ver');
    Route::get('disposicion/selecciones', [OfiArchivoDisposicionController::class, 'selecciones'])
        ->middleware('can:'.$perm.'Ver');
    Route::get('disposicion/selecciones/{id}', [OfiArchivoDisposicionController::class, 'seleccion'])->whereNumber('id')
        ->middleware('can:'.$perm.'Ver');
    Route::post('disposicion/selecciones', [OfiArchivoDisposicionController::class, 'proponer'])
        ->middleware('can:'.$perm.'Seleccionar');
    Route::patch('disposicion/selecciones/{id}/items/{item}', [OfiArchivoDisposicionController::class, 'ajustar'])
        ->whereNumber(['id', 'item'])->middleware('can:'.$perm.'Seleccionar');
    Route::post('disposicion/selecciones/{id}/confirmar', [OfiArchivoDisposicionController::class, 'confirmar'])->whereNumber('id')
        ->middleware('can:'.$perm.'Seleccionar');
    Route::post('disposicion/selecciones/{id}/anular', [OfiArchivoDisposicionController::class, 'anular'])->whereNumber('id')
        ->middleware('can:'.$perm.'Seleccionar');

    // Digitalización (M/D): copia con su huella y certificado; el original no se toca
    Route::get('disposicion/expedientes/{id}/digitalizaciones', [OfiArchivoDisposicionController::class, 'digitalizaciones'])
        ->whereNumber('id')->middleware('can:'.$perm.'Ver');
    Route::post('disposicion/expedientes/{id}/digitalizaciones', [OfiArchivoDisposicionController::class, 'digitalizar'])
        ->whereNumber('id')->middleware('can:'.$perm.'Digitalizar');
    Route::get('disposicion/digitalizaciones/{id}/certificado', [OfiArchivoDisposicionController::class, 'certificado'])
        ->whereNumber('id')->middleware('can:'.$perm.'Ver');
    Route::get('disposicion/digitalizaciones/{id}/archivo', [OfiArchivoDisposicionController::class, 'archivo'])
        ->whereNumber('id')->middleware('can:'.$perm.'Ver');
});
