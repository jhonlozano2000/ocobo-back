<?php

use App\Http\Controllers\OfiArchivo\EliminacionPublicaController;
use Illuminate\Support\Facades\Route;

/*
 * B18 (diseño de expedientes §3.8 E): sin sesión. Inventarios de eliminación publicados, sus
 * actas y las observaciones de la ciudadanía. Limitado por IP.
 */
Route::middleware('throttle:publico')->group(function () {
    Route::get('eliminaciones', [EliminacionPublicaController::class, 'index']);
    Route::get('eliminaciones/{codigo}', [EliminacionPublicaController::class, 'show'])->where('codigo', 'EL-\d{4}-\d{4}');
    Route::get('eliminaciones/{codigo}/inventario', [EliminacionPublicaController::class, 'inventario'])->where('codigo', 'EL-\d{4}-\d{4}');
    Route::get('eliminaciones/{codigo}/acta', [EliminacionPublicaController::class, 'acta'])->where('codigo', 'EL-\d{4}-\d{4}');
});
Route::middleware('throttle:publico-observaciones')
    ->post('eliminaciones/{codigo}/observaciones', [EliminacionPublicaController::class, 'observar'])->where('codigo', 'EL-\d{4}-\d{4}');
