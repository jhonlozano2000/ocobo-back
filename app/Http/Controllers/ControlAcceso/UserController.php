<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Helpers\ArchivoHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\ControlAcceso\UserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with(['cargos', 'roles', 'permissions'])->get()->each->append(['avatar_url', 'firma_url']);

        return response()->json([
            'status' => true,
            'data' => $users,
            'message' => 'Listado de usuarios'
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserRequest $request)
    {
        // Obtiene los datos ya validados por UserRequest
        $validatedData = $request->validated();

        DB::beginTransaction();

        try {

            // Subir el avatar
            if ($request->hasFile('avatar')) {
                $fileAvatar = ArchivoHelper::guardarArchivo($request, 'avatar', 'avatars', null);
                $validatedData['avatar'] = $fileAvatar;
            }

            // Subir la firma
            if ($request->hasFile('firma')) {
                $fileFirma = ArchivoHelper::guardarArchivo($request, 'firma', 'firmas', null);
                $validatedData['firma'] = $fileFirma;
            }

            // Ciframos la contraseña
            $validatedData['password'] = Hash::make($validatedData['password']);

            // Creamos el usuario con TODOS los datos, incluyendo las rutas de los archivos
            $user = User::create($validatedData);

            // Asignamos roles
            if ($request->has('roles')) {
                $user->syncRoles($request->roles);
            }

            // Si todo va bien, confirmamos los cambios en la base de datos
            DB::commit();

            return response()->json([
                'status' => true,
                'data' => $user->load('roles')->append(['avatar_url', 'firma_url']),
                'message' => 'Usuario creado correctamente'
            ], 201);
        } catch (\Exception $e) {
            // Si algo falla, revertimos la transacción de la BD
            DB::rollBack();

            // Y eliminamos los archivos que se hayan subido, si existen
            if (isset($validatedData['avatar'])) {
                Storage::disk('avatars')->delete($validatedData['avatar']);
            }
            if (isset($validatedData['firma'])) {
                Storage::disk('firmas')->delete($validatedData['firma']);
            }

            // Devolvemos una respuesta de error clara
            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error al crear el usuario.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $user->append(['avatar_url', 'firma_url']),
            'message' => 'Usuario encontrado'
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserRequest $request, User $user)
    {
        // Obtiene los datos ya validados por UserRequest.
        $validatedData = $request->validated();

        // Iniciamos la transacción para asegurar la integridad de los datos.
        DB::beginTransaction();

        try {
            // Guardamos las rutas de los archivos antiguos para eliminarlos DESPUÉS de que todo salga bien.
            $oldAvatarPath = $user->avatar;
            $oldFirmaPath = $user->firma;

            // Subir el avatar
            $user->avatar = ArchivoHelper::guardarArchivo($request, 'avatar', 'avatars', $user->avatar);
            // Subir la firma
            $user->firma = ArchivoHelper::guardarArchivo($request, 'firma', 'firmas', $user->firma);

            // Solo actualizamos la contraseña si se proporciona una nueva.
            if (!empty($validatedData['password'])) {
                $validatedData['password'] = Hash::make($validatedData['password']);
            } else {
                // Si el campo de contraseña viene vacío, lo eliminamos para no sobreescribir la existente.
                unset($validatedData['password']);
            }

            // Actualizamos los datos del usuario en la base de datos.
            $user->update($validatedData);

            // Sincronizamos los roles (elimina los viejos y añade los nuevos).
            if ($request->has('roles')) {
                $user->syncRoles($request->roles);
            }

            // Si todo va bien, confirmamos la transacción.
            DB::commit();

            // --- ELIMINACIÓN DE ARCHIVOS ANTIGUOS ---
            // Solo eliminamos los archivos viejos si la transacción fue exitosa y se subió uno nuevo.
            if ($request->hasFile('avatar') && $oldAvatarPath) {
                Storage::disk('avatars')->delete($oldAvatarPath);
            }
            if ($request->hasFile('firma') && $oldFirmaPath) {
                Storage::disk('firmas')->delete($oldFirmaPath);
            }

            return response()->json([
                'status' => true,
                'data' => $user->load('roles')->append(['avatar_url', 'firma_url']),
                'message' => 'Usuario actualizado correctamente'
            ], 200);
        } catch (\Exception $e) {
            // Si algo falla, revertimos la transacción de la BD.
            DB::rollBack();

            // Y eliminamos los NUEVOS archivos que se hayan subido antes del error.
            if (isset($validatedData['avatar'])) {
                Storage::disk('avatars')->delete($validatedData['avatar']);
            }
            if (isset($validatedData['firma'])) {
                Storage::disk('firmas')->delete($validatedData['firma']);
            }

            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error al actualizar el usuario.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            // Encuentra el usuario por ID
            $user = User::find($id);
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            ArchivoHelper::eliminarArchivo($user->avatar, 'avatars');
            ArchivoHelper::eliminarArchivo($user->firma, 'firmas');
            $user->delete();

            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Usuario eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error al eliminar el usuario.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics.
     */
    public function estadisticas()
    {
        $totalUsers = User::count();
        $totalUsersActivos = User::where('estado', 1)->count();
        $totalUsersInactivos = User::where('estado', 0)->count();
        $totalSesiones = DB::table('users_sessions')->count();

        return response()->json([
            'status' => true,
            'data' => [
                'total_users' => $totalUsers,
                'total_users_activos' => $totalUsersActivos,
                'total_users_inactivos' => $totalUsersInactivos,
                'total_sesiones' => $totalSesiones,
            ]
        ], 200);
    }

    /**
     * Update user profile information.
     * @param Request $request
     */
    public function updateUserProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nombres' => 'required|string|max:70',
            'apellidos' => 'required|string|max:70',
        ]);

        $user->update($validated);

        return response()->json($user->append(['avatar_url', 'firma_url']));
    }

    /**
     * Update user password.
     * @param Request $request
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        // 1. Validar que la contraseña actual es correcta
        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'La contraseña actual que ingresaste no es correcta.',
            ]);
        }

        // 2. Definir las reglas de validación
        $rules = [
            'current_password' => 'required',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
        ];

        // 3. Definir los mensajes personalizados en español para cada regla
        $messages = [
            'current_password.required' => 'Debes ingresar tu contraseña actual.',
            'password.required'         => 'Debes ingresar una nueva contraseña.',
            'password.confirmed'        => 'La confirmación de la nueva contraseña no coincide.',
            'password.min'              => 'La nueva contraseña debe tener al menos :min caracteres.',
            'password.mixedCase'        => 'La nueva contraseña debe contener al menos una letra mayúscula y una minúscula.',
            'password.numbers'          => 'La nueva contraseña debe contener al menos un número.',
            'password.symbols'          => 'La nueva contraseña debe contener al menos un símbolo.',
        ];

        // 4. Ejecutar la validación con las reglas y los mensajes
        $request->validate($rules, $messages);

        // 5. Actualizar la contraseña
        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        // 6. Devolver una respuesta de éxito
        return response()->json(['message' => 'Contraseña actualizada con éxito.']);
    }

    /**
     * Activar o inactivar el usuario.
     * @param Request $request
     */
    public function activarInactivar(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();

            // 1. Validar que la contraseña proporcionada es correcta
            if (!Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'La contraseña proporcionada no es correcta.',
                ]);
            }

            // 2. Invalidar todos los tokens del usuario para cerrar todas sus sesiones
            $user->tokens()->delete();

            // 3. Cambiar el estado del usuario a 0 (Inactivo) y guardar
            $user->estado = 0;
            $user->save();

            DB::commit();
            // 4. Devolver una respuesta de éxito
            return response()->json(['message' => 'Tu cuenta ha sido desactivada con éxito.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error al desactivar la cuenta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
