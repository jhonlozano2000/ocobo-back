<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthLoginRequest;
use Illuminate\Http\Request;
use App\Models\User;
use Dotenv\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Validación de los datos
        $validator = Validator::make($request->all(), [
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

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'user' => $user,
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
        return response()->json(
            [
                'status' => true,
                'data' => $request->user()
            ],
            201
        );
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
