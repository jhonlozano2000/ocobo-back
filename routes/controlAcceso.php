<?php

use App\Http\Controllers\ControlAcceso\UserControlle;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::resource('/users', UserControlle::class)->except('create', 'edit');
});
