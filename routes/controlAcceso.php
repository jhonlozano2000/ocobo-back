<?php

use App\Http\Controllers\ControlAcceso\RoleControlleController;
use App\Http\Controllers\ControlAcceso\UserControlle;

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    /**
     * Usuarios
     */
    Route::resource('/users', UserControlle::class)->except('create', 'edit');

    /**
     * Roles y permisosas
     */
    Route::resource('/roles', RoleControlleController::class)->except('create', 'edit');
    Route::get('/roles-y-permisos', [RoleControlleController::class, 'listRolesPermisos'])->name('roles.permisos.show');

    /**
     * Permisos
     */
    Route::get('/permisos', [RoleControlleController::class, 'listPermisos'])->name('permisos.show');

    Route::put('/user/profile-information', [UserControlle::class, 'updateUserProfile']);
});
