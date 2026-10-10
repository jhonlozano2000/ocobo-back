<?php

use App\Http\Controllers\ControlAcceso\NotificationSettingsController;
use App\Http\Controllers\ControlAcceso\RoleController;
use App\Http\Controllers\ControlAcceso\UserCargoController;
use App\Http\Controllers\ControlAcceso\UserController;
use App\Http\Controllers\ControlAcceso\UserSedeController;
use App\Http\Controllers\ControlAcceso\UserSessionController;
use App\Http\Controllers\ControlAcceso\UserVentanillaController;
use Illuminate\Support\Facades\Route;

/**
 * Rate limiting específico para Control de Acceso
 */
Route::middleware(['throttle:config-operations', 'auth:sanctum'])->group(function () {
    /**
     * Perfil y autenticación de usuario (propio, sin permiso adicional)
     */
    Route::put('/user/changePassword', [UserController::class, 'updatePassword']);
    Route::put('/user/profile-information', [UserController::class, 'updateUserProfile']);

    /**
     * RBAC-001 / M14-001: toda la gestión de usuarios exige permiso explícito.
     */
    $permUsers = 'Control de acceso - Usuarios -> ';
    Route::post('/user/activar-inactivar', [UserController::class, 'activarInactivar'])->middleware('can:'.$permUsers.'Editar');

    /**
     * Listado mínimo para selectores (cualquier usuario autenticado, sin datos sensibles)
     */
    Route::get('/users/selector', [UserController::class, 'selector'])->name('users.selector');

    /**
     * Usuarios - Rutas específicas PRIMERO para evitar conflictos con las rutas con parámetro
     */
    Route::get('/users/estadisticas', [UserController::class, 'estadisticas'])->middleware('can:'.$permUsers.'Listar');
    Route::get('/users/usuarios-con-cargos', [UserController::class, 'listarUsuariosConCargos'])->middleware('can:'.$permUsers.'Listar');
    Route::get('/users/usuarios-activos-con-oficina-dependencia', [UserController::class, 'usuariosActivosConOficinaYDependencia'])->middleware('can:'.$permUsers.'Listar');
    Route::get('/users/usuarios-con-cargos-activos', [UserController::class, 'usuariosConCargosActivos'])->middleware('can:'.$permUsers.'Listar');

    /**
     * Configuración de notificaciones propia (ANTES de GET /users/{user})
     */
    Route::get('/users/notification-settings', [NotificationSettingsController::class, 'show']);
    Route::put('/users/notification-settings', [NotificationSettingsController::class, 'update']);

    /**
     * Usuarios - Rutas específicas con parámetros
     */
    Route::middleware('propio_o_can:'.$permUsers.'Mostrar')->group(function () {
        Route::get('/users/{userId}/perfil-completo', [UserController::class, 'getPerfilCompleto'])->name('users.perfil-completo');
        Route::get('/users/{userId}/actividad', [UserController::class, 'getActividad'])->name('users.actividad');
        Route::get('/users/{userId}/historial', [UserController::class, 'getHistorial'])->name('users.historial');
        Route::get('/users/{userId}/historial-cargos', [UserController::class, 'getHistorialCargos'])->name('users.historial-cargos');
        Route::get('/users/{userId}/historial-sedes', [UserController::class, 'getHistorialSedes'])->name('users.historial-sedes');
        Route::get('/users/{userId}/historial-roles', [UserController::class, 'getHistorialRoles'])->name('users.historial-roles');
        Route::get('/users/{userId}/conexiones', [UserController::class, 'getConexiones'])->name('users.conexiones');
        Route::get('/users/{userId}/permisos', [UserController::class, 'getPermisos'])->name('users.permisos');
        Route::get('/users/{userId}/cargo', [UserController::class, 'getUserCargo'])->name('users.cargo');
        Route::get('/users/{userId}/notification-settings', [NotificationSettingsController::class, 'getUserSettings']);
        Route::get('/users/{userId}/sessions', [UserSessionController::class, 'getUserSessions']);
    });
    Route::put('/users/{userId}/otros-permisos', [UserController::class, 'syncOtrosPermisos'])->name('users.otros-permisos')->middleware('can:'.$permUsers.'Asignar Roles');
    Route::put('/users/{userId}/notification-settings', [NotificationSettingsController::class, 'updateUserSettings'])->middleware('can:'.$permUsers.'Editar');

    /**
     * Usuarios - CRUD
     */
    Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware('can:'.$permUsers.'Listar');
    Route::post('/users', [UserController::class, 'store'])->name('users.store')->middleware('can:'.$permUsers.'Crear');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show')->middleware('can:'.$permUsers.'Mostrar');
    Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('can:'.$permUsers.'Editar');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('can:'.$permUsers.'Eliminar');

    /**
     * Roles y permisos
     */
    $permRoles = 'Control de acceso - Roles -> ';
    Route::get('/roles/estadisticas', [RoleController::class, 'estadisticas'])->name('roles.estadisticas')->middleware('can:'.$permRoles.'Listar');
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('can:'.$permRoles.'Listar');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('can:'.$permRoles.'Crear');
    Route::get('/roles/{role}', [RoleController::class, 'show'])->name('roles.show')->middleware('can:'.$permRoles.'Mostrar');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('can:'.$permRoles.'Editar');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('can:'.$permRoles.'Eliminar');
    Route::get('/roles-usuarios', [RoleController::class, 'rolesConUsuarios'])->middleware('can:'.$permRoles.'Listar');
    Route::get('/roles-y-permisos', [RoleController::class, 'listRolesPermisos'])->name('roles.permisos.show')->middleware('can:'.$permRoles.'Listar');

    /**
     * Permisos
     */
    Route::get('/permisos', [RoleController::class, 'listPermisos'])->name('permisos.show')->middleware('can:'.$permRoles.'Listar');

    /**
     * Sesiones propias
     */
    Route::get('/user/recent-devices', [UserSessionController::class, 'index']);
    Route::delete('/user/sessions/{sessionId}', [UserSessionController::class, 'destroy']);

    /**
     * Gestión de ventanillas por usuario
     */
    Route::get('/users-ventanillas/estadisticas', [UserVentanillaController::class, 'estadisticas'])->middleware('can:'.$permUsers.'Listar');
    Route::get('/users-ventanillas', [UserVentanillaController::class, 'index'])->middleware('can:'.$permUsers.'Listar');
    Route::get('/users-ventanillas/{userVentanilla}', [UserVentanillaController::class, 'show'])->middleware('can:'.$permUsers.'Mostrar');
    Route::post('/users-ventanillas', [UserVentanillaController::class, 'store'])->middleware('can:'.$permUsers.'Editar');
    Route::match(['put', 'patch'], '/users-ventanillas/{userVentanilla}', [UserVentanillaController::class, 'update'])->middleware('can:'.$permUsers.'Editar');
    Route::delete('/users-ventanillas/{userVentanilla}', [UserVentanillaController::class, 'destroy'])->middleware('can:'.$permUsers.'Editar');

    /**
     * Gestión de relaciones usuario-sede
     */
    Route::get('/user-sedes', [UserSedeController::class, 'index'])->middleware('can:'.$permUsers.'Listar');
    Route::get('/user-sedes/{userSede}', [UserSedeController::class, 'show'])->middleware('can:'.$permUsers.'Mostrar');
    Route::post('/user-sedes', [UserSedeController::class, 'store'])->middleware('can:'.$permUsers.'Editar');
    Route::match(['put', 'patch'], '/user-sedes/{userSede}', [UserSedeController::class, 'update'])->middleware('can:'.$permUsers.'Editar');
    Route::delete('/user-sedes/{userSede}', [UserSedeController::class, 'destroy'])->middleware('can:'.$permUsers.'Editar');
    Route::get('/users/{userId}/sedes', [UserSedeController::class, 'getUserSedes'])->middleware('can:'.$permUsers.'Mostrar');
    Route::put('/users/{userId}/sedes', [UserSedeController::class, 'updateUserSedes'])->middleware('can:'.$permUsers.'Editar');
    Route::get('/sedes/{sedeId}/users', [UserSedeController::class, 'getSedeUsers'])->middleware('can:'.$permUsers.'Listar');

    /**
     * ==================== GESTIÓN DE CARGOS USUARIOS ====================
     * M17-01: asignar/finalizar cargos exige permiso propio (antes: cualquier autenticado)
     */
    $permCargos = 'Control de acceso - Cargos -> ';
    Route::prefix('user-cargos')->group(function () use ($permCargos) {
        // Rutas específicas SIN parámetros (más específicas primero)
        Route::get('/estadisticas', [UserCargoController::class, 'estadisticas'])->name('user-cargos.estadisticas')->middleware('can:'.$permCargos.'Listar');
        Route::get('/cargos-disponibles', [UserCargoController::class, 'cargosDisponibles'])->name('user-cargos.disponibles')->middleware('can:'.$permCargos.'Listar');
        Route::post('/asignar', [UserCargoController::class, 'asignarCargo'])->name('user-cargos.asignar')->middleware('can:'.$permCargos.'Asignar');

        // Rutas específicas CON parámetros (después de las sin parámetros)
        Route::get('/usuario/{userId}/activo', [UserCargoController::class, 'cargoActivoUsuario'])->name('user-cargos.usuario.activo')->middleware('can:'.$permCargos.'Listar');
        Route::get('/usuario/{userId}/historial', [UserCargoController::class, 'historialUsuario'])->name('user-cargos.usuario.historial')->middleware('can:'.$permCargos.'Listar');
        Route::get('/cargo/{cargoId}/usuarios', [UserCargoController::class, 'usuariosCargo'])->name('user-cargos.cargo.usuarios')->middleware('can:'.$permCargos.'Listar');
        Route::put('/finalizar/{asignacionId}', [UserCargoController::class, 'finalizarCargo'])->name('user-cargos.finalizar')->middleware('can:'.$permCargos.'Finalizar');

        Route::get('/', [UserCargoController::class, 'index'])->name('user-cargos.index')->middleware('can:'.$permCargos.'Listar');
        Route::get('/{userCargo}', [UserCargoController::class, 'show'])->name('user-cargos.show')->middleware('can:'.$permCargos.'Listar');
    });

}); // Fin throttle
