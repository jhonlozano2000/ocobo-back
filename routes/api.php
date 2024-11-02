<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ControlAcceso\UserControlle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/getme', [AuthController::class, 'getMe']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/register', [AuthController::class, 'register']);

    Route::resource('/users', UserControlle::class)->except('create', 'edit');
});
