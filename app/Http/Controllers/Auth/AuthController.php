<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use \Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Validación de los datos
        $validator = \Validator::make($request->all(), [
            'num_docu' => 'required|string|max:20|unique:users',
            'nombres' => 'required|string|max:70',
            'apellidos' => 'required|string|max:70',
            'email' => 'required|string|email|max:70|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'num_docu.unique' => 'El número de documento ya está en uso',
            'num_docu.required' => 'Te hizo falta el número de documento',
            'nombres.required' => 'Te hizo falta el nombre',
            'apellidos.required' => 'Te hizo falta el apellido',
            'email.required' => 'Te hizo falta el correo electrónico',
            'email.email' => 'El correo electrónico no es válido',
            'email.max' => 'El correo electrónico es demasiado largo',
            'email.unique' => 'El correo electrónico ya está en uso',
            'password.required' => 'Te hizo falta la contraseña',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
            'password.confirmed' => 'La contraseña no coincide con la confirmación',
        ]);

        // Verificar si la validación falla
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400); // Devuelve un error 400 (Bad Request) con los errores
        }

        $user = User::create([
            'num_docu' => $request->num_docu,
            'nombres' => $request->nombres,
            'apellidos' => $request->apellidos,
            'tel' => $request->tel,
            'movil' => $request->movil,
            'dir' => $request->dir,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password) || $user->estado == 0) {
            // Si el usuario existe pero está inactivo, damos un mensaje específico
            if ($user && $user->estado == 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Tu cuenta se encuentra desactivada.'
                ], 401);
            }

            // Para otros casos, un mensaje genérico
            return response()->json([
                'status' => false,
                'message' => 'Las credenciales proporcionadas son incorrectas.'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Carga roles y permisos
        $user->load('roles', 'permissions');

        return response()->json([
            'status' => true,
            'user' => [
                'id' => $user->id,
                'num_docu' => $user->num_docu,
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
                'email' => $user->email,
                'tel' => $user->tel,
                'movil' => $user->movil,
                'dir' => $user->dir,
                'email' => $user->email,
                'firma' => $user->firma,
                'avatar' => $user->avatar,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged out'
        ]);
    }

    public function getMe(Request $request)
    {
        $user = Auth::user();

        $user->load('roles.permissions'); // Carga roles con permisos

        $permisos = $user->getAllPermissions()->pluck('name'); // Obtiene solo los nombres de permisos

        return response()->json([
            'id' => $user->id,
            'num_docu' => $user->num_docu,
            'nombres' => $user->nombres,
            'apellidos' => $user->apellidos,
            'email' => $user->email,
            'tel' => $user->tel,
            'movil' => $user->movil,
            'dir' => $user->dir,
            'email' => $user->email,
            'firma' => $user->firma,
            'avatar' => $user->avatar,
            'roles' => $user->getRoleNames(),
            'permisos' => $permisos
        ]);
    }

    public function refresh(Request $request)
    {
        // Revoca el token actual
        $request->user()->currentAccessToken()->delete();

        // Crea un nuevo token
        $newToken = $request->user()->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $newToken,
            'token_type' => 'Bearer',
        ]);
    }
}
