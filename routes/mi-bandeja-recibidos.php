<?php

use App\Http\Controllers\MiBandeja\Recibidos\MiBandejaRecibidosController;
use Illuminate\Support\Facades\Route;

$permMiBandeja = 'Mi Bandeja - Recibidos -> ';

Route::middleware('auth:sanctum')->group(function () use ($permMiBandeja) {
    Route::get('/mis-radicados', [MiBandejaRecibidosController::class, 'misRadicados'])
        ->name('mi-bandeja.recibidos.mis-radicados')
        ->middleware('can:'.$permMiBandeja.'Ver');
});
