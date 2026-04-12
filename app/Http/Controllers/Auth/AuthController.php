<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthLoginRequest;
use App\Http\Requests\Auth\AuthRegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\UsersAuthenticationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * CSRF Cookie Endpoint - Obligatorio previo a cualquier autenticación
     * Laravel Sanctum maneja automáticamente este endpoint
     * Ruta: GET /sanctum/csrf-cookie
     */

    /**
     * Login - Autenticación de usuario con cookies HttpOnly
     * 
     * Flujo:
     * 1. Validar CSRF token (Sanctum)
     * 2. Validar credenciales
     * 3. Regenerar sesión (Session Fixation prevention - ISO 27001 A.9.4.1)
     * 4. Crear cookie HttpOnly
     * 5. Registrar auditoría
     */
    public function login(AuthLoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember', false);
        
        // Sanitización de entrada para prevenir inyección
        $email = filter_var($credentials['email'], FILTER_SANITIZE_EMAIL);

        if (!Auth::attempt(['email' => $email, 'password' => $credentials['password']], $remember)) {
            // Auditoría ISO 27001 - Registro de intento fallido
            UsersAuthenticationLog::logEvent([
                'user_id'  => null,
                'event'    => 'login_failed',
                'success'  => false,
                'email'    => $email,
                'details'  => 'Credenciales incorrectas - IP: ' . $request->ip() . ' | User-Agent: ' . substr($request->userAgent(), 0, 200),
            ]);

            return $this->errorResponse('Las credenciales proporcionadas son incorrectas.', null, 401);
        }

        $user = Auth::user();
        
        // Verificar estado de cuenta
        if ($user->estado == 0) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            UsersAuthenticationLog::logEvent([
                'user_id'  => $user->id,
                'event'    => 'login_failed',
                'success'  => false,
                'email'    => $user->email,
                'details'  => 'Cuenta desactivada',
            ]);

            return $this->errorResponse('Tu cuenta se encuentra desactivada.', null, 401);
        }

        // REGENERAR SESIÓN - Prevención Session Fixation (ISO 27001)
        // Cada login genera un nuevo ID de sesión
        $request->session()->regenerate();

        // Auditoría ISO 27001 - Registro de login exitoso
        UsersAuthenticationLog::logEvent([
            'user_id'  => $user->id,
            'event'    => 'login_success',
            'success'  => true,
            'email'    => $user->email,
            'details'  => 'Login exitoso - IP: ' . $request->ip() . ' | Dispositivo: ' . $this->parseDevice($request->userAgent()),
        ]);

        // PRINCIPIO DE PRIVILEGIO MÍNIMO (PoLP) - ISO 27001 A.9.4.1
        // No exponer datos sensibles innecesarios al cliente
        return $this->successResponse([
            'user' => new UserResource($user),
        ], 'Login exitoso');
    }

    /**
     * Logout - Cierre de sesión seguro
     * 
     * Flujo:
     * 1. Invalidar sesión
     * 2. Regenerar token CSRF
     * 3. Eliminar cookie
     * 4. Registrar auditoría
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        
        if ($user) {
            UsersAuthenticationLog::logEvent([
                'user_id'  => $user->id,
                'event'    => 'logout',
                'success'  => true,
                'details'  => 'Logout exitoso',
            ]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->successResponse(null, 'Sesión cerrada correctamente');
    }

    /**
     * GetMe - Obtener usuario autenticado
     * 
     * IMPORTANTE: Este endpoint ES la verificación de sesión
     * El frontend lo usa para verificar si la cookie sigue válida
     */
    public function getMe(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return $this->errorResponse('No autenticado', null, 401);
        }

        return $this->successResponse([
            'user' => new UserResource($user),
        ], 'Información del usuario');
    }

    /**
     * Register - Registro de nuevo usuario
     */
    public function register(AuthRegisterRequest $request)
    {
        $user = User::create([
            'num_docu'   => $request->num_docu,
            'nombres'    => $request->nombres,
            'apellidos'  => $request->apellidos,
            'tel'        => $request->tel,
            'movil'      => $request->movil,
            'dir'        => $request->dir,
            'email'      => filter_var($request->email, FILTER_SANITIZE_EMAIL),
            'password'   => Hash::make($request->password),
            'estado'     => 1,
        ]);

        if ($request->has('role')) {
            $user->assignRole($request->role);
        }

        Auth::login($user);
        $request->session()->regenerate();

        UsersAuthenticationLog::logEvent([
            'user_id'  => $user->id,
            'event'    => 'register_success',
            'success'  => true,
            'details'  => 'Nuevo usuario registrado',
        ]);

        return $this->successResponse([
            'user' => new UserResource($user),
        ], 'Usuario registrado correctamente', 201);
    }

    /**
     * Parser de User-Agent para auditoría
     */
    private function parseDevice(?string $userAgent): string
    {
        if (!$userAgent) return 'Unknown';

        $device = 'Desktop';
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            $device = preg_match('/iPad/', $userAgent) ? 'Tablet' : 'Mobile';
        }

        $browser = 'Unknown';
        if (preg_match('/Chrome/', $userAgent) && !preg_match('/Edg/', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox/', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari/', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edg/', $userAgent)) {
            $browser = 'Edge';
        }

        return "{$device} - {$browser}";
    }
}