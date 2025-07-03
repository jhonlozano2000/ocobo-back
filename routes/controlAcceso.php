<?php

use App\Http\Controllers\ControlAcceso\NotificationSettingsController;
use App\Http\Controllers\ControlAcceso\RoleControlleController;
use App\Http\Controllers\ControlAcceso\UserControlle;
use App\Http\Controllers\ControlAcceso\UserSessionController;
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

    Route::put('/user/changePassword', [UserControlle::class, 'updatePassword']);

    // routes/api.php
    Route::post('/user/activar-inactivar', [UserControlle::class, 'activarInactivar']);

    // routes/api.php
    Route::get('/user/recent-devices', [UserSessionController::class, 'index']);

    Route::get('/user/notification-settings', [NotificationSettingsController::class, 'show']);
    Route::put('/user/notification-settings', [NotificationSettingsController::class, 'update']);
});
