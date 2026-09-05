<?php

/**
 * Archivo de rutas para el módulo de Email Radicados.
 *
 * Rutas para la gestión de correos electrónicos radicados (lectura directa desde IMAP).
 */

use App\Http\Controllers\VentanillaUnica\EmailRadicadosController;
use App\Http\Controllers\VentanillaUnica\TerceroSearchController;
use Illuminate\Support\Facades\Route;

$permEmail = 'Radicar -> Cores. Recibida -> ';

Route::middleware('auth:sanctum')->group(function () use ($permEmail) {
    // Lectura (throttle:api — 60/min)
    Route::middleware('throttle:api')->group(function () use ($permEmail) {
        Route::get('/email-radicados', [EmailRadicadosController::class, 'index'])
            ->name('email-radicados.index')
            ->middleware('can:'.$permEmail.'Listar');

        Route::get('/email-radicados/estadisticas', [EmailRadicadosController::class, 'estadisticas'])
            ->name('email-radicados.estadisticas')
            ->middleware('can:'.$permEmail.'Listar');

        Route::get('/email-radicados/terceros/buscar-por-email', [TerceroSearchController::class, 'buscarPorEmail'])
            ->name('email-radicados.terceros.buscar-por-email')
            ->middleware('can:'.$permEmail.'Listar');

        Route::get('/email-radicados/{id}', [EmailRadicadosController::class, 'show'])
            ->name('email-radicados.show')
            ->middleware('can:'.$permEmail.'Mostrar');

        Route::get('/email-radicados/{id}/rotulo', [EmailRadicadosController::class, 'rotulo'])
            ->name('email-radicados.rotulo')
            ->middleware('can:'.$permEmail.'Mostrar');
    });

    // Escritura (throttle:radicacion — 30/min)
    Route::middleware('throttle:radicacion')->group(function () use ($permEmail) {
        Route::post('/email-radicados/{id}/radicar', [EmailRadicadosController::class, 'radicar'])
            ->name('email-radicados.radicar')
            ->middleware('can:'.$permEmail.'Crear');

        Route::post('/email-radicados/{id}/responder', [EmailRadicadosController::class, 'responder'])
            ->name('email-radicados.responder')
            ->middleware('can:'.$permEmail.'Crear');
    });
});
