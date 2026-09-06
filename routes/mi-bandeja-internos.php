<?php

use App\Http\Controllers\MiBandeja\Internos\MiBandejaInternosController;
use Illuminate\Support\Facades\Route;

$permMiBandeja = 'Mi Bandeja - Internos -> ';

Route::middleware('auth:sanctum')->group(function () use ($permMiBandeja) {
    Route::get('/mis-radicados', [MiBandejaInternosController::class, 'misRadicados'])
        ->name('mi-bandeja.internos.mis-radicados')
        ->middleware('can:'.$permMiBandeja.'Ver');

    Route::get('/mis-radicados/estadisticas', [MiBandejaInternosController::class, 'estadisticas'])
        ->name('mi-bandeja.internos.estadisticas')
        ->middleware('can:'.$permMiBandeja.'Ver');
});
