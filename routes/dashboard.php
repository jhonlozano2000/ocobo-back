<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardGlobalController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('global', [DashboardGlobalController::class, 'index']);
});
