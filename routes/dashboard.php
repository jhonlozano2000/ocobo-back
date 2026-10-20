<?php

use App\Http\Controllers\DashboardGlobalController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('global', [DashboardGlobalController::class, 'index']);
    Route::get('sparklines', [DashboardGlobalController::class, 'sparklines']);
    Route::get('analytics', [DashboardGlobalController::class, 'analytics']);
    Route::get('crm', [DashboardGlobalController::class, 'crm']);
    Route::get('logistics', [DashboardGlobalController::class, 'logistics']);
    Route::get('academy', [DashboardGlobalController::class, 'academy']);
    Route::get('ecommerce', [DashboardGlobalController::class, 'ecommerce']);
});
