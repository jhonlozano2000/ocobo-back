<?php

use App\Http\Controllers\ControlAcceso\NotificationSettingsController;
use App\Http\Controllers\ControlAcceso\RoleController;
use App\Http\Controllers\ControlAcceso\UserController;
use App\Http\Controllers\ControlAcceso\UserSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    /**
     * Usuarios
     */
    Route::resource('/users', UserController::class)->except('create', 'edit');

    /**
     * Roles y permisosas
     */
    Route::resource('/roles', RoleController::class)->except('create', 'edit');
    Route::get('/roles-usuarios', [RoleController::class, 'rolesConUsuarios']);
    Route::get('/roles-y-permisos', [RoleController::class, 'listRolesPermisos'])->name('roles.permisos.show');

    /**
     * Permisos
     */
    Route::get('/permisos', [RoleController::class, 'listPermisos'])->name('permisos.show');

    Route::put('/user/profile-information', [UserController::class, 'updateUserProfile']);

    Route::put('/user/changePassword', [UserController::class, 'updatePassword']);

    // routes/api.php
    Route::post('/user/activar-inactivar', [UserController::class, 'activarInactivar']);

    // routes/api.php
    Route::get('/user/recent-devices', [UserSessionController::class, 'index']);

    Route::get('/user/notification-settings', [NotificationSettingsController::class, 'show']);
    Route::put('/user/notification-settings', [NotificationSettingsController::class, 'update']);

    Route::get('/user/estadisticas', [UserController::class, 'estadisticas']);
});
