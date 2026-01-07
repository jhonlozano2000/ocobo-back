<?php

use App\Http\Controllers\ControlAcceso\NotificationSettingsController;
use App\Http\Controllers\ControlAcceso\RoleController;
use App\Http\Controllers\ControlAcceso\UserCargoController;
use App\Http\Controllers\ControlAcceso\UserController;
use App\Http\Controllers\ControlAcceso\UserSessionController;
use App\Http\Controllers\ControlAcceso\UserSedeController;
use App\Http\Controllers\ControlAcceso\UserVentanillaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    /**
     * Usuarios - Rutas específicas PRIMERO para evitar conflictos con resource
     */
    Route::get('/users/estadisticas', [UserController::class, 'estadisticas']);
    Route::get('/users/usuarios-con-cargos', [UserController::class, 'listarUsuariosConCargos']);
    Route::get('/users/usuarios-activos-con-oficina-dependencia', [UserController::class, 'usuariosActivosConOficinaYDependencia']);
    Route::get('/users/usuarios-con-cargos-activos', [UserController::class, 'usuariosConCargosActivos']);
    Route::get('/users/debug-relaciones', [UserController::class, 'debugUsuariosRelaciones']);
    Route::get('/users/debug-oficinas-cargos', [UserController::class, 'debugOficinasYCargos']);
    Route::get('/users/debug-organigrama-estructura', [UserController::class, 'debugOrganigramaEstructura']);

    /**
     * Usuarios - Rutas específicas con parámetros (antes del resource)
     */
    Route::get('/users/{userId}/perfil-completo', [UserController::class, 'getPerfilCompleto'])->name('users.perfil-completo');
    Route::get('/users/{userId}/actividad', [UserController::class, 'getActividad'])->name('users.actividad');
    Route::get('/users/{userId}/historial', [UserController::class, 'getHistorial'])->name('users.historial');
    Route::get('/users/{userId}/historial-cargos', [UserController::class, 'getHistorialCargos'])->name('users.historial-cargos');
    Route::get('/users/{userId}/historial-sedes', [UserController::class, 'getHistorialSedes'])->name('users.historial-sedes');
    Route::get('/users/{userId}/historial-roles', [UserController::class, 'getHistorialRoles'])->name('users.historial-roles');
    Route::get('/users/{userId}/conexiones', [UserController::class, 'getConexiones'])->name('users.conexiones');
    Route::get('/users/{userId}/permisos', [UserController::class, 'getPermisos'])->name('users.permisos');
    Route::get('/users/{userId}/cargo', [UserController::class, 'getUserCargo'])->name('users.cargo');

    /**
     * Usuarios - Resource routes
     */
    Route::resource('/users', UserController::class)->except('create', 'edit');

    /**
     * Roles y permisos
     */
    Route::get('/roles/estadisticas', [RoleController::class, 'estadisticas'])->name('roles.estadisticas');
    Route::resource('/roles', RoleController::class)->except('create', 'edit');
    Route::get('/roles-usuarios', [RoleController::class, 'rolesConUsuarios']);
    Route::get('/roles-y-permisos', [RoleController::class, 'listRolesPermisos'])->name('roles.permisos.show');

    /**
     * Permisos
     */
    Route::get('/permisos', [RoleController::class, 'listPermisos'])->name('permisos.show');

    /**
     * Perfil y autenticación de usuario
     */
    Route::put('/user/profile-information', [UserController::class, 'updateUserProfile']);
    Route::put('/user/changePassword', [UserController::class, 'updatePassword']);
    Route::post('/user/activar-inactivar', [UserController::class, 'activarInactivar']);

    /**
     * Sesiones de usuario
     */
    Route::get('/user/recent-devices', [UserSessionController::class, 'index']);
    Route::get('/users/{userId}/sessions', [UserSessionController::class, 'getUserSessions']);
    Route::delete('/user/sessions/{sessionId}', [UserSessionController::class, 'destroy']);

    /**
     * Configuración de notificaciones
     */
    Route::get('/users/notification-settings', [NotificationSettingsController::class, 'show']);
    Route::put('/users/notification-settings', [NotificationSettingsController::class, 'update']);
    Route::get('/users/{userId}/notification-settings', [NotificationSettingsController::class, 'getUserSettings']);
    Route::put('/users/{userId}/notification-settings', [NotificationSettingsController::class, 'updateUserSettings']);

    /**
     * Gestión de ventanillas por usuario
     */
    Route::get('/users-ventanillas/estadisticas', [UserVentanillaController::class, 'estadisticas']);
    Route::resource('/users-ventanillas', UserVentanillaController::class)->except('create', 'edit');

    /**
     * Gestión de relaciones usuario-sede
     */
    Route::resource('/user-sedes', UserSedeController::class)->except('create', 'edit');
    Route::get('/users/{userId}/sedes', [UserSedeController::class, 'getUserSedes']);
    Route::put('/users/{userId}/sedes', [UserSedeController::class, 'updateUserSedes']);
    Route::get('/sedes/{sedeId}/users', [UserSedeController::class, 'getSedeUsers']);

    /**
     * ==================== GESTIÓN DE CARGOS USUARIOS ====================
     */

    // Rutas principales de gestión de cargos
    Route::prefix('user-cargos')->group(function () {
        // Rutas específicas SIN parámetros (más específicas primero)
        Route::get('/estadisticas', [UserCargoController::class, 'estadisticas'])->name('user-cargos.estadisticas');
        Route::get('/cargos-disponibles', [UserCargoController::class, 'cargosDisponibles'])->name('user-cargos.disponibles');
        Route::post('/asignar', [UserCargoController::class, 'asignarCargo'])->name('user-cargos.asignar');

        // Rutas específicas CON parámetros (después de las sin parámetros)
        Route::get('/usuario/{userId}/activo', [UserCargoController::class, 'cargoActivoUsuario'])->name('user-cargos.usuario.activo');
        Route::get('/usuario/{userId}/historial', [UserCargoController::class, 'historialUsuario'])->name('user-cargos.usuario.historial');
        Route::get('/cargo/{cargoId}/usuarios', [UserCargoController::class, 'usuariosCargo'])->name('user-cargos.cargo.usuarios');
        Route::put('/finalizar/{asignacionId}', [UserCargoController::class, 'finalizarCargo'])->name('user-cargos.finalizar');

        // Resource route (debe ir al final)
        Route::apiResource('', UserCargoController::class)
            ->parameters(['' => 'userCargo'])
            ->names([
                'index' => 'index',
                'show' => 'show',
            ])->only('index', 'show');
    });
});
