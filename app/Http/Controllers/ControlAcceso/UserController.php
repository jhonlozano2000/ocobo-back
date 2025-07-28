<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Helpers\ArchivoHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use App\Http\Requests\ControlAcceso\StoreUserRequest;
use App\Http\Requests\ControlAcceso\UpdateUserRequest;
use App\Http\Requests\ControlAcceso\UpdateUserProfileRequest;
use App\Http\Requests\ControlAcceso\UpdatePasswordRequest;
use App\Http\Requests\ControlAcceso\ActivarInactivarRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de todos los usuarios del sistema.
     *
     * Este método retorna todos los usuarios registrados en el sistema junto con
     * sus relaciones (cargos, roles, permisos) y URLs de archivos (avatar, firma).
     * Es útil para interfaces de administración donde se necesita mostrar una
     * lista completa de usuarios.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de usuarios
     *
     * @response 200 {
     *   "status": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "nombres": "Juan",
     *       "apellidos": "Pérez",
     *       "email": "juan.perez@example.com",
     *       "estado": 1,
     *       "avatar_url": "http://example.com/avatars/user.jpg",
     *       "firma_url": "http://example.com/firmas/user.jpg",
     *       "cargos": [],
     *       "roles": [
     *         {
     *           "id": 1,
     *           "name": "Administrador"
     *         }
     *       ],
     *       "permissions": []
     *     }
     *   ],
     *   "message": "Listado de usuarios obtenido exitosamente"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de usuarios",
     *   "error": "Error message"
     * }
     */
    public function index()
    {
        try {
            $users = User::with(['cargos', 'roles', 'permissions'])
                ->get()
                ->each->append(['avatar_url', 'firma_url']);

            return $this->successResponse($users, 'Listado de usuarios obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de usuarios', $e->getMessage(), 500);
        }
    }

    /**
     * Crea un nuevo usuario en el sistema.
     *
     * Este método permite crear un nuevo usuario con todos sus datos personales,
     * archivos (avatar y firma) y asignación de roles. El proceso se ejecuta
     * dentro de una transacción para garantizar la integridad de los datos.
     * Si algo falla, se eliminan los archivos subidos y se revierte la transacción.
     *
     * @param StoreUserRequest $request La solicitud HTTP validada con los datos del usuario
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el usuario creado
     *
     * @bodyParam divi_poli_id integer required ID de la división política. Example: 1
     * @bodyParam num_docu string required Número de documento único. Example: "12345678"
     * @bodyParam nombres string required Nombres del usuario. Example: "Juan Carlos"
     * @bodyParam apellidos string required Apellidos del usuario. Example: "Pérez García"
     * @bodyParam tel string Teléfono fijo. Example: "1234567"
     * @bodyParam movil string Teléfono móvil. Example: "3001234567"
     * @bodyParam dir string Dirección. Example: "Calle 123 #45-67"
     * @bodyParam email string required Email único. Example: "juan.perez@example.com"
     * @bodyParam password string required Contraseña. Example: "Password123!"
     * @bodyParam estado boolean Estado del usuario. Example: true
     * @bodyParam roles array required Array de nombres de roles. Example: ["admin", "editor"]
     * @bodyParam avatar file Archivo de avatar. Example: "user.jpg"
     * @bodyParam firma file Archivo de firma. Example: "signature.jpg"
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Usuario creado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombres": "Juan Carlos",
     *     "apellidos": "Pérez García",
     *     "email": "juan.perez@example.com",
     *     "avatar_url": "http://example.com/avatars/user.jpg",
     *     "firma_url": "http://example.com/firmas/signature.jpg",
     *     "roles": [
     *       {
     *         "id": 1,
     *         "name": "admin"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "email": ["El email ya se encuentra registrado."],
     *     "num_docu": ["El número de documento ya se encuentra registrado."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear el usuario",
     *   "error": "Error message"
     * }
     */
    public function store(StoreUserRequest $request)
    {
        $validatedData = $request->validated();

        DB::beginTransaction();

        try {
            // Procesar archivos si se proporcionan
            if ($request->hasFile('avatar')) {
                $validatedData['avatar'] = ArchivoHelper::guardarArchivo($request, 'avatar', 'avatars', null);
            }

            if ($request->hasFile('firma')) {
                $validatedData['firma'] = ArchivoHelper::guardarArchivo($request, 'firma', 'firmas', null);
            }

            // Cifrar contraseña
            $validatedData['password'] = Hash::make($validatedData['password']);

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            // Crear usuario
            $user = User::create($validatedData);

            // Asignar roles
            if ($request->has('roles')) {
                $user->syncRoles($request->roles);
            }

            DB::commit();

            return $this->successResponse(
                $user->load('roles')->append(['avatar_url', 'firma_url']),
                'Usuario creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            // Limpiar archivos subidos en caso de error
            if (isset($validatedData['avatar'])) {
                Storage::disk('avatars')->delete($validatedData['avatar']);
            }
            if (isset($validatedData['firma'])) {
                Storage::disk('firmas')->delete($validatedData['firma']);
            }

            return $this->errorResponse('Error al crear el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un usuario específico por su ID.
     *
     * Este método permite obtener la información detallada de un usuario específico,
     * incluyendo sus URLs de archivos (avatar y firma). Es útil para mostrar
     * los detalles de un usuario o para formularios de edición.
     *
     * @param string $id El ID del usuario a obtener
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el usuario
     *
     * @urlParam id integer required El ID del usuario. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Usuario encontrado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombres": "Juan Carlos",
     *     "apellidos": "Pérez García",
     *     "email": "juan.perez@example.com",
     *     "avatar_url": "http://example.com/avatars/user.jpg",
     *     "firma_url": "http://example.com/firmas/signature.jpg"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el usuario",
     *   "error": "Error message"
     * }
     */
    public function show(string $id)
    {
        Log::info('Método show ejecutado con ID:', ['id' => $id]);

        try {
            $user = User::find($id);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            return $this->successResponse(
                $user->append(['avatar_url', 'firma_url']),
                'Usuario encontrado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un usuario existente en el sistema.
     *
     * Este método permite modificar los datos de un usuario existente, incluyendo
     * la actualización de archivos (avatar y firma) y roles. El proceso se ejecuta
     * dentro de una transacción para garantizar la integridad de los datos.
     * Los archivos antiguos se eliminan solo después de que la actualización sea exitosa.
     *
     * @param UpdateUserRequest $request La solicitud HTTP validada con los datos actualizados
     * @param User $user El usuario a actualizar (inyectado por Laravel)
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el usuario actualizado
     *
     * @bodyParam divi_poli_id integer ID de la división política. Example: 1
     * @bodyParam num_docu string Número de documento único. Example: "12345678"
     * @bodyParam nombres string Nombres del usuario. Example: "Juan Carlos"
     * @bodyParam apellidos string Apellidos del usuario. Example: "Pérez García"
     * @bodyParam tel string Teléfono fijo. Example: "1234567"
     * @bodyParam movil string Teléfono móvil. Example: "3001234567"
     * @bodyParam dir string Dirección. Example: "Calle 123 #45-67"
     * @bodyParam email string Email único. Example: "juan.perez@example.com"
     * @bodyParam password string Nueva contraseña (opcional). Example: "NewPassword123!"
     * @bodyParam estado boolean Estado del usuario. Example: true
     * @bodyParam roles array Array de nombres de roles. Example: ["admin", "editor"]
     * @bodyParam avatar file Archivo de avatar. Example: "new_user.jpg"
     * @bodyParam firma file Archivo de firma. Example: "new_signature.jpg"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Usuario actualizado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombres": "Juan Carlos",
     *     "apellidos": "Pérez García",
     *     "email": "juan.perez@example.com",
     *     "avatar_url": "http://example.com/avatars/new_user.jpg",
     *     "firma_url": "http://example.com/firmas/new_signature.jpg",
     *     "roles": [
     *       {
     *         "id": 1,
     *         "name": "admin"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "email": ["El email ya se encuentra registrado."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar el usuario",
     *   "error": "Error message"
     * }
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $validatedData = $request->validated();

        DB::beginTransaction();

        try {
            // Guardar rutas de archivos antiguos
            $oldAvatarPath = $user->avatar;
            $oldFirmaPath = $user->firma;

            // Procesar archivos si se proporcionan
            $user->avatar = ArchivoHelper::guardarArchivo($request, 'avatar', 'avatars', $user->avatar);
            $user->firma = ArchivoHelper::guardarArchivo($request, 'firma', 'firmas', $user->firma);

            // Procesar contraseña si se proporciona
            if (!empty($validatedData['password'])) {
                $validatedData['password'] = Hash::make($validatedData['password']);
            } else {
                unset($validatedData['password']);
            }

            // Convertir estado a booleano si se proporciona
            if (isset($validatedData['estado'])) {
                $validatedData['estado'] = filter_var($validatedData['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            // Actualizar usuario
            $user->update($validatedData);

            // Sincronizar roles
            if ($request->has('roles')) {
                $user->syncRoles($request->roles);
            }

            DB::commit();

            // Eliminar archivos antiguos después de confirmar la transacción
            if ($request->hasFile('avatar') && $oldAvatarPath) {
                Storage::disk('avatars')->delete($oldAvatarPath);
            }
            if ($request->hasFile('firma') && $oldFirmaPath) {
                Storage::disk('firmas')->delete($oldFirmaPath);
            }

            return $this->successResponse(
                $user->load('roles')->append(['avatar_url', 'firma_url']),
                'Usuario actualizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            // Limpiar archivos nuevos en caso de error
            if (isset($validatedData['avatar'])) {
                Storage::disk('avatars')->delete($validatedData['avatar']);
            }
            if (isset($validatedData['firma'])) {
                Storage::disk('firmas')->delete($validatedData['firma']);
            }

            return $this->errorResponse('Error al actualizar el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un usuario del sistema.
     *
     * Este método permite eliminar un usuario del sistema junto con todos sus
     * archivos asociados (avatar y firma). El proceso se ejecuta dentro de una
     * transacción para garantizar la integridad de los datos.
     *
     * @param string $id El ID del usuario a eliminar
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam id integer required El ID del usuario a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Usuario eliminado exitosamente"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Usuario no encontrado"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar el usuario",
     *   "error": "Error message"
     * }
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();

        try {
            $user = User::find($id);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            // Eliminar archivos
            ArchivoHelper::eliminarArchivo($user->avatar, 'avatars');
            ArchivoHelper::eliminarArchivo($user->firma, 'firmas');

            // Eliminar usuario
            $user->delete();

            DB::commit();

            return $this->successResponse(null, 'Usuario eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene estadísticas de usuarios del sistema.
     *
     * Este método proporciona estadísticas generales sobre los usuarios del sistema,
     * incluyendo el total de usuarios, usuarios activos/inactivos y sesiones.
     * Es útil para dashboards de administración y reportes.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las estadísticas
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Estadísticas obtenidas exitosamente",
     *   "data": {
     *     "total_users": 150,
     *     "total_users_activos": 120,
     *     "total_users_inactivos": 30,
     *     "total_sesiones": 45
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener las estadísticas",
     *   "error": "Error message"
     * }
     */
    public function estadisticas()
    {
        \Log::info('Método estadisticas ejecutado');

        try {
            $totalUsers = User::count();
            $totalUsersActivos = User::where('estado', 1)->count();
            $totalUsersInactivos = User::where('estado', 0)->count();
            $totalSesiones = DB::table('users_sessions')->count();

            $estadisticas = [
                'total_users' => $totalUsers,
                'total_users_activos' => $totalUsersActivos,
                'total_users_inactivos' => $totalUsersInactivos,
                'total_sesiones' => $totalSesiones,
            ];

            \Log::info('Estadísticas calculadas:', $estadisticas);

            return $this->successResponse($estadisticas, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            \Log::error('Error en estadisticas:', ['error' => $e->getMessage()]);
            return $this->errorResponse('Error al obtener las estadísticas', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza la información del perfil del usuario autenticado.
     *
     * Este método permite al usuario autenticado actualizar su información
     * personal básica (nombres y apellidos). Es útil para que los usuarios
     * puedan mantener sus datos actualizados.
     *
     * @param UpdateUserProfileRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el usuario actualizado
     *
     * @bodyParam nombres string required Nombres del usuario. Example: "Juan Carlos"
     * @bodyParam apellidos string required Apellidos del usuario. Example: "Pérez García"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Perfil actualizado exitosamente",
     *   "data": {
     *     "id": 1,
     *     "nombres": "Juan Carlos",
     *     "apellidos": "Pérez García",
     *     "avatar_url": "http://example.com/avatars/user.jpg",
     *     "firma_url": "http://example.com/firmas/signature.jpg"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "nombres": ["Los nombres son obligatorios."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar el perfil",
     *   "error": "Error message"
     * }
     */
    public function updateUserProfile(UpdateUserProfileRequest $request)
    {
        try {
            $user = Auth::user();
            $user->update($request->validated());

            return $this->successResponse(
                $user->append(['avatar_url', 'firma_url']),
                'Perfil actualizado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar el perfil', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza la contraseña del usuario autenticado.
     *
     * Este método permite al usuario autenticado cambiar su contraseña.
     * Requiere la contraseña actual para verificar la identidad del usuario
     * y valida que la nueva contraseña cumpla con los requisitos de seguridad.
     *
     * @param UpdatePasswordRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando el cambio
     *
     * @bodyParam current_password string required Contraseña actual. Example: "OldPassword123!"
     * @bodyParam password string required Nueva contraseña. Example: "NewPassword123!"
     * @bodyParam password_confirmation string required Confirmación de nueva contraseña. Example: "NewPassword123!"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Contraseña actualizada exitosamente"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "current_password": ["La contraseña actual que ingresaste no es correcta."],
     *     "password": ["La nueva contraseña debe contener al menos un símbolo."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la contraseña",
     *   "error": "Error message"
     * }
     */
    public function updatePassword(UpdatePasswordRequest $request)
    {
        try {
            $user = Auth::user();

            // Verificar contraseña actual
            if (!Hash::check($request->validated('current_password'), $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'La contraseña actual que ingresaste no es correcta.',
                ]);
            }

            // Actualizar contraseña
            $user->forceFill([
                'password' => Hash::make($request->validated('password')),
            ])->save();

            return $this->successResponse(null, 'Contraseña actualizada exitosamente');
        } catch (ValidationException $e) {
            return $this->errorResponse('Datos de validación incorrectos', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar la contraseña', $e->getMessage(), 500);
        }
    }

    /**
     * Desactiva la cuenta del usuario autenticado.
     *
     * Este método permite al usuario autenticado desactivar su cuenta.
     * Requiere la contraseña para confirmar la acción y elimina todos
     * los tokens de sesión activos del usuario.
     *
     * @param ActivarInactivarRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la desactivación
     *
     * @bodyParam password string required Contraseña para confirmar la acción. Example: "MyPassword123!"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Cuenta desactivada exitosamente"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "password": ["La contraseña proporcionada no es correcta."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al desactivar la cuenta",
     *   "error": "Error message"
     * }
     */
    public function activarInactivar(ActivarInactivarRequest $request)
    {
        DB::beginTransaction();

        try {
            $user = Auth::user();

            // Verificar contraseña
            if (!Hash::check($request->validated('password'), $user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'La contraseña proporcionada no es correcta.',
                ]);
            }

            // Eliminar tokens de sesión
            $user->tokens()->delete();

            // Desactivar cuenta
            $user->estado = 0;
            $user->save();

            DB::commit();

            return $this->successResponse(null, 'Cuenta desactivada exitosamente');
        } catch (ValidationException $e) {
            return $this->errorResponse('Datos de validación incorrectos', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al desactivar la cuenta', $e->getMessage(), 500);
        }
    }
}
