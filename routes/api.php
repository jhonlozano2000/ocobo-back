<?php

use App\Http\Controllers\Auth\AuthController;
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

Route::post('/register', [AuthController::class, 'register'])
    ->withoutMiddleware(\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);
Route::post('/login', [AuthController::class, 'login'])
    ->withoutMiddleware(\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/getme', [AuthController::class, 'getMe']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
