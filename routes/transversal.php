<?php

use App\Http\Controllers\Firma\FirmaController;
use App\Http\Controllers\Transversal\FirmaEventosController;
use App\Http\Controllers\Transversal\InAppNotificationController;
use App\Http\Controllers\Transversal\NotificacionesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('firma-electronica')->group(function () {
    Route::post('/solicitar-otp', [FirmaController::class, 'solicitarOtp']);
    Route::post('/firmar', [FirmaController::class, 'validarYFirmar']);
});

// M16 — Notificaciones y Alertas (Ley 1437/2011)
Route::middleware('auth:sanctum')->prefix('notificaciones')->group(function () {
    Route::get('/pendientes', [NotificacionesController::class, 'pendientes']);
});

Route::middleware('auth:sanctum')->prefix('in-app-notifications')->group(function () {
    Route::get('/', [InAppNotificationController::class, 'index']);
    Route::get('/unread-count', [InAppNotificationController::class, 'unreadCount']);
    Route::patch('/{id}/read', [InAppNotificationController::class, 'markAsRead']);
    Route::post('/read-all', [InAppNotificationController::class, 'markAllAsRead']);
});

// M13 — Historial de firmas electrónicas (Ley 527/1999, ISO 27001 A.8.15)
Route::middleware('auth:sanctum')->prefix('firma-eventos')->group(function () {
    Route::get('/{tipo}/{documentoId}', [FirmaEventosController::class, 'historial']);
});
