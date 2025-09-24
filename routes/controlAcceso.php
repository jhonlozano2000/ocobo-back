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
    Route::get('/users/stats/estadisticas', [UserController::class, 'estadisticas']);
    Route::get('/users/usuarios-con-cargos', [UserController::class, 'listarUsuariosConCargos']);
    Route::get('/users/usuarios-activos-con-oficina-dependencia', [UserController::class, 'usuariosActivosConOficinaYDependencia']);
    Route::get('/users/usuarios-con-cargos-activos', [UserController::class, 'usuariosConCargosActivos']);
    Route::get('/users/debug-relaciones', [UserController::class, 'debugUsuariosRelaciones']);
    Route::get('/users/debug-oficinas-cargos', [UserController::class, 'debugOficinasYCargos']);
    Route::get('/users/debug-organigrama-estructura', [UserController::class, 'debugOrganigramaEstructura']);

    /**
     * Usuarios - Resource routes
     */
    Route::resource('/users', UserController::class)->except('create', 'edit');

    /**
     * Roles y permisos
     */
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
    Route::get('/sedes/{sedeId}/users', [UserSedeController::class, 'getSedeUsers']);

    /**
     * ==================== GESTIÓN DE CARGOS USUARIOS ====================
     */

    // Rutas principales de gestión de cargos
    Route::prefix('user-cargos')->group(function () {
        // Listar asignaciones con filtros
        Route::get('/', [UserCargoController::class, 'index'])->name('user-cargos.index');

        // Asignar cargo a usuario
        Route::post('/asignar', [UserCargoController::class, 'asignarCargo'])->name('user-cargos.asignar');

        // Finalizar asignación de cargo
        Route::put('/finalizar/{asignacionId}', [UserCargoController::class, 'finalizarCargo'])->name('user-cargos.finalizar');

        // Consultas específicas por usuario
        Route::get('/usuario/{userId}/activo', [UserCargoController::class, 'cargoActivoUsuario'])->name('user-cargos.usuario.activo');
        Route::get('/usuario/{userId}/historial', [UserCargoController::class, 'historialUsuario'])->name('user-cargos.usuario.historial');

        // Consultas específicas por cargo
        Route::get('/cargo/{cargoId}/usuarios', [UserCargoController::class, 'usuariosCargo'])->name('user-cargos.cargo.usuarios');

        // Estadísticas y reportes
        Route::get('/estadisticas', [UserCargoController::class, 'estadisticas'])->name('user-cargos.estadisticas');

        // Cargos disponibles
        Route::get('/cargos-disponibles', [UserCargoController::class, 'cargosDisponibles'])->name('user-cargos.disponibles');
    });
});
