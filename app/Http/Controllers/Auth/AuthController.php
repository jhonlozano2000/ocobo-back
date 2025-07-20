<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthLoginRequest;
use App\Http\Requests\Auth\AuthRegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Registra un nuevo usuario.
     */
    public function register(AuthRegisterRequest $request)
    {
        DB::beginTransaction();

        try {
            $user = User::create([
                'num_docu' => $request->num_docu,
                'nombres' => $request->nombres,
                'apellidos' => $request->apellidos,
                'tel' => $request->tel,
                'movil' => $request->movil,
                'dir' => $request->dir,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'estado' => 1, // Usuario activo por defecto
            ]);

            // Asignar rol por defecto si se especifica
            if ($request->has('role')) {
                $user->assignRole($request->role);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return $this->successResponse([
                'user' => $this->formatUserData($user),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 'Usuario registrado correctamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al registrar el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Autentica un usuario y retorna token con roles y permisos.
     */
    public function login(AuthLoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return $this->errorResponse('Las credenciales proporcionadas son incorrectas.', null, 401);
        }

        $user = Auth::user();

        // Verificar si la cuenta está activa
        if ($user->estado == 0) {
            $user->tokens()->delete();
            return $this->errorResponse('Tu cuenta se encuentra desactivada.', null, 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $this->formatUserData($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 'Login exitoso');
    }

    /**
     * Cierra la sesión del usuario.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Sesión cerrada correctamente');
    }

    /**
     * Obtiene la información del usuario autenticado.
     */
    public function getMe(Request $request)
    {
        $user = Auth::user();

        return $this->successResponse([
            'user' => $this->formatUserData($user)
        ], 'Información del usuario');
    }

    /**
     * Refresca el token del usuario.
     */
    public function refresh(Request $request)
    {
        $user = $request->user();

        // Revoca el token actual
        $user->currentAccessToken()->delete();

        // Crea un nuevo token
        $newToken = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $this->formatUserData($user),
            'access_token' => $newToken,
            'token_type' => 'Bearer',
        ], 'Token refrescado correctamente');
    }

    /**
     * Formatea los datos del usuario de manera consistente.
     */
    private function formatUserData(User $user): array
    {
        // Limpiar cache de permisos para asegurar datos actualizados
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Cargar relaciones necesarias
        $user->load(['roles.permissions', 'permissions']);

        return [
            'id' => $user->id,
            'num_docu' => $user->num_docu,
            'nombres' => $user->nombres,
            'apellidos' => $user->apellidos,
            'email' => $user->email,
            'tel' => $user->tel,
            'movil' => $user->movil,
            'dir' => $user->dir,
            'estado' => $user->estado,
            'firma' => $user->firma,
            'avatar' => $user->avatar,
            'firma_url' => $user->firma_url,
            'avatar_url' => $user->avatar_url,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Respuesta exitosa estándar.
     */
    private function successResponse($data, $message, $code = 200)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Respuesta de error estándar.
     */
    private function errorResponse($message, $error = null, $code = 400)
    {
        $response = [
            'status' => false,
            'message' => $message,
        ];

        if ($error) {
            $response['error'] = $error;
        }

        return response()->json($response, $code);
    }
}
