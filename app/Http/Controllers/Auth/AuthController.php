<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthLoginRequest;
use App\Http\Requests\Auth\AuthRegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * Registra un nuevo usuario.
     */
    public function register(AuthRegisterRequest $request)
    {
        $user = User::create([
            'num_docu' => $request->num_docu,
            'nombres'  => $request->nombres,
            'apellidos' => $request->apellidos,
            'tel'      => $request->tel,
            'movil'    => $request->movil,
            'dir'      => $request->dir,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'estado'   => 1,
        ]);

        if ($request->has('role')) {
            $user->assignRole($request->role);
        }

        // En un entorno SPA con estado, podemos autenticar automáticamente al registrarse
        Auth::login($user);
        $request->session()->regenerate();

        return $this->successResponse([
            'user' => new UserResource($user),
        ], 'Usuario registrado correctamente', 201);
    }

    /**
     * Autentica un usuario e inicializa la sesión segura (cookie httpOnly).
     */
    public function login(AuthLoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember', false); // Manejo de "Recordarme"

        if (!Auth::attempt($credentials, $remember)) {
            return $this->errorResponse('Las credenciales proporcionadas son incorrectas.', null, 401);
        }

        $user = Auth::user();
        if ($user->estado == 0) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return $this->errorResponse('Tu cuenta se encuentra desactivada.', null, 401);
        }

        // Rotar el ID de sesión para prevenir ataques de Session Fixation
        $request->session()->regenerate();

        return $this->successResponse([
            'user' => new UserResource($user),
        ], 'Login exitoso');
    }

    /**
     * Cierra la sesión del usuario de forma segura.
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->successResponse(null, 'Sesión cerrada correctamente');
    }

    /**
     * Obtiene la información del usuario autenticado incluyendo cargo, oficina y dependencia.
     */
    public function getMe(Request $request)
    {
        return $this->successResponse([
            'user' => new UserResource($request->user()),
        ], 'Información del usuario');
    }

    /**
     * En una SPA basada en cookies, no hay necesidad de un endpoint "refresh" de token,
     * ya que la sesión persiste mediante la cookie, renovada implícitamente por el servidor.
     */
}
