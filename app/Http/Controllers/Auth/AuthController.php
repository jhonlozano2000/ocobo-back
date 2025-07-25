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

        $token = $this->createToken($user);

        return $this->successResponse([
            'user'         => new UserResource($user),
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ], 'Usuario registrado correctamente', 201);
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
        if ($user->estado == 0) {
            $user->tokens()->delete();
            return $this->errorResponse('Tu cuenta se encuentra desactivada.', null, 401);
        }

        $token = $this->createToken($user);

        return $this->successResponse([
            'user'         => new UserResource($user),
            'access_token' => $token,
            'token_type'   => 'Bearer',
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
        return $this->successResponse([
            'user' => new UserResource($request->user()),
        ], 'Información del usuario');
    }

    /**
     * Refresca el token del usuario.
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $newToken = $this->createToken($user);

        return $this->successResponse([
            'user'         => new UserResource($user),
            'access_token' => $newToken,
            'token_type'   => 'Bearer',
        ], 'Token refrescado correctamente');
    }

    /**
     * Crea un nuevo token personal.
     *
     * @param User $user
     * @return string
     */
    private function createToken(User $user): string
    {
        return $user->createToken('auth_token')->plainTextToken;
    }
}
