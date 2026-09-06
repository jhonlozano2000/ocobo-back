<?php

use App\Http\Controllers\DashboardGlobalController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('global', [DashboardGlobalController::class, 'index']);
    Route::get('sparklines', [DashboardGlobalController::class, 'sparklines']);
});
