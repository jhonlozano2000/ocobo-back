<?php

use App\Http\Controllers\MiBandeja\MiDiscoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('mi-disco')->group(function () {
    Route::get('/', [MiDiscoController::class, 'index'])->name('mi-disco.index');
    Route::post('/carpetas', [MiDiscoController::class, 'storeCarpeta'])->name('mi-disco.carpeta.crear');
    Route::delete('/carpetas/{id}', [MiDiscoController::class, 'destroyCarpeta'])->name('mi-disco.carpeta.eliminar');
    Route::post('/archivos', [MiDiscoController::class, 'subirArchivo'])->name('mi-disco.archivo.subir');
    Route::get('/archivos/{id}/download', [MiDiscoController::class, 'downloadArchivo'])->name('mi-disco.archivo.descargar');
    Route::delete('/archivos/{id}', [MiDiscoController::class, 'destroyArchivo'])->name('mi-disco.archivo.eliminar');
});
