<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthLoginRequest;
use App\Http\Requests\Auth\AuthRegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseTrait;
use App\Mail\PasswordResetMail;
use App\Models\ControlAcceso\UsersSession;
use App\Models\User;
use App\Models\UsersAuthenticationLog;
use App\Services\Auth\TwoFactorToken;
use App\Services\Seguridad\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

        // Claves de bloqueo temporal por fuerza bruta (ISO 27001 / OWASP)
        $lockoutKey = 'login_lockout_'.sha1(strtolower($email));
        $attemptsKey = 'login_attempts_'.sha1(strtolower($email));

        if (Cache::has($lockoutKey)) {
            $secondsRemaining = (int) Cache::get($lockoutKey) - now()->timestamp;
            $minutesRemaining = max(1, (int) ceil($secondsRemaining / 60));

            return $this->errorResponse(
                "Cuenta bloqueada temporalmente por múltiples intentos fallidos. Intenta nuevamente en {$minutesRemaining} minuto(s).",
                null,
                429
            );
        }

        // Buscar usuario por email (case-insensitive)
        $user = User::where(function ($query) use ($email) {
            $query->where('email', $email)
                ->orWhereRaw('LOWER(email) = LOWER(?)', [$email]);
        })->first();

        // Si no se encuentra el usuario
        if (! $user) {
            $this->logFailedLoginAttempt(null, $email, 'Usuario no encontrado', $request);

            return $this->errorResponse('Las credenciales proporcionadas son incorrectas.', null, 401);
        }

        // Verificar estado de cuenta (asumiendo que 1 = activo, 0 = inactivo)
        if (! isset($user->estado) || $user->estado == 0) {
            $this->logFailedLoginAttempt($user->id, $user->email, 'Cuenta desactivada', $request);

            return $this->errorResponse('Tu cuenta se encuentra desactivada.', null, 401);
        }

        // Validar credenciales
        if (! Hash::check($credentials['password'], $user->password)) {
            $this->logFailedLoginAttempt($user->id, $user->email, 'Credenciales incorrectas', $request);

            // Incrementar contador de intentos fallidos con expiración de 15 minutos
            $attempts = (int) Cache::get($attemptsKey, 0) + 1;
            Cache::put($attemptsKey, $attempts, now()->addMinutes(15));

            if ($attempts >= 5) {
                // Bloquear la cuenta temporalmente por 15 minutos (900 segundos)
                Cache::put($lockoutKey, now()->addMinutes(15)->timestamp, now()->addMinutes(15));
                Cache::forget($attemptsKey);

                UsersAuthenticationLog::logEvent([
                    'user_id' => $user->id,
                    'event' => 'account_locked',
                    'success' => false,
                    'email' => $user->email,
                    'details' => 'Cuenta bloqueada temporalmente por 15 minutos tras 5 intentos fallidos consecutivos. IP: '.$request->ip(),
                ]);

                return $this->errorResponse(
                    'Demasiados intentos fallidos. Tu cuenta ha sido bloqueada temporalmente por 15 minutos por seguridad.',
                    null,
                    429
                );
            }

            return $this->errorResponse('Las credenciales proporcionadas son incorrectas.', null, 401);
        }

        // Limpiar contadores de intentos fallidos tras autenticación exitosa
        Cache::forget($attemptsKey);
        Cache::forget($lockoutKey);

        // Si el usuario tiene 2FA activo, NO crear sesión aún
        if ($user->twoFactorEnabled) {
            $token = TwoFactorToken::generate($user->id);

            return $this->successResponse([
                'requires_2fa' => true,
                'two_factor_token' => $token,
            ], 'Se requiere verificación de dos factores');
        }

        // Detectar sesión concurrente previa desde otra IP / dispositivo (ISO 27001 A.9.4.2)
        $sesionPrevia = UsersSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('ip_address', '!=', $request->ip())
            ->first();

        if ($sesionPrevia) {
            UsersAuthenticationLog::logEvent([
                'user_id' => $user->id,
                'event' => 'concurrent_session_detected',
                'success' => true,
                'email' => $user->email,
                'details' => "Nueva sesión iniciada desde IP {$request->ip()} mientras existía sesión activa previa desde IP {$sesionPrevia->ip_address}",
            ]);
        }

        // Autenticar al usuario (esto establecerá la sesión)
        Auth::login($user, $remember);

        // REGENERAR SESIÓN - Prevención Session Fixation (ISO 27001)
        // Cada login genera un nuevo ID de sesión
        $request->session()->regenerate();

        // Registrar timestamp de inicio de sesión para Techo Máximo Absoluto (ISO 27001 A.9.4.2)
        $request->session()->put('auth_login_at', now()->timestamp);

        // Registrar login exitoso
        $this->logSuccessfulLogin($user, $request);

        // PRINCIPIO DE PRIVILEGIO MÍNIMO (PoLP) - ISO 27001 A.9.4.1
        // No exponer datos sensibles innecesarios al cliente
        return $this->successResponse([
            'user' => new UserResource($user),
        ], 'Login exitoso');
    }

    /**
     * Registrar intento de login fallido
     */
    private function logFailedLoginAttempt(?int $userId, string $email, string $reason, AuthLoginRequest $request): void
    {
        // Auditoría ISO 27001 - Registro de intento fallido
        UsersAuthenticationLog::logEvent([
            'user_id' => $userId,
            'event' => 'login_failed',
            'success' => false,
            'email' => $email,
            'details' => $reason.' - IP: '.$request->ip().' | User-Agent: '.substr($request->userAgent(), 0, 200),
        ]);

        // Log centralizado de seguridad
        AuditLogService::logAutenticacion(
            AuditLogService::EVENTO_LOGIN_FALLO,
            false,
            ['ip' => $request->ip(), 'email_domain' => explode('@', $email)[1] ?? 'unknown']
        );
    }

    /**
     * Registrar login exitoso
     */
    private function logSuccessfulLogin(User $user, AuthLoginRequest $request): void
    {
        // Auditoría ISO 27001 - Registro de login exitoso
        UsersAuthenticationLog::logEvent([
            'user_id' => $user->id,
            'event' => 'login_success',
            'success' => true,
            'email' => $user->email,
            'details' => 'Login exitoso - IP: '.$request->ip().' | Dispositivo: '.$this->parseDevice($request->userAgent()),
        ]);

        // Log centralizado de seguridad
        AuditLogService::logAutenticacion(
            AuditLogService::EVENTO_LOGIN_EXITO,
            true,
            ['ip' => $request->ip(), 'device' => $this->parseDevice($request->userAgent())]
        );
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
                'user_id' => $user->id,
                'event' => 'logout',
                'success' => true,
                'details' => 'Logout exitoso',
            ]);

            AuditLogService::logAutenticacion(
                AuditLogService::EVENTO_LOGOUT,
                true,
                ['ip' => $request->ip()]
            );
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

        if (! $user) {
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
            'num_docu' => $request->num_docu,
            'nombres' => $request->nombres,
            'apellidos' => $request->apellidos,
            'tel' => $request->tel,
            'movil' => $request->movil,
            'dir' => $request->dir,
            'email' => filter_var($request->email, FILTER_SANITIZE_EMAIL),
            'password' => Hash::make($request->password),
            'estado' => 1,
        ]);

        if ($request->has('role')) {
            $user->assignRole($request->role);
        }

        Auth::login($user);
        $request->session()->regenerate();

        UsersAuthenticationLog::logEvent([
            'user_id' => $user->id,
            'event' => 'register_success',
            'success' => true,
            'details' => 'Nuevo usuario registrado',
        ]);

        return $this->successResponse([
            'user' => new UserResource($user),
        ], 'Usuario registrado correctamente', 201);
    }

    /**
     * Forgot Password - Enviar correo con enlace de restablecimiento
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.exists' => 'No encontramos una cuenta con ese correo electrónico.',
        ]);

        try {
            $user = User::where('email', $request->email)->first();
            $token = Password::getRepository()->create($user);

            $resetUrl = url("/es/reset-password?token={$token}&email=".urlencode($request->email));

            $resetUrl = url("/es/reset-password?token={$token}&email=".urlencode($request->email));

            Mail::to($request->email)->send(new PasswordResetMail($resetUrl));
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al enviar el correo. Intenta de nuevo más tarde.', null, 500);
        }

        UsersAuthenticationLog::logEvent([
            'event' => 'password_reset_requested',
            'success' => true,
            'email' => $request->email,
            'details' => 'Solicitud de restablecimiento de contraseña enviada',
        ]);

        return $this->successResponse(null, 'Te hemos enviado un correo con las instrucciones para restablecer tu contraseña.');
    }

    /**
     * Reset Password - Restablecer contraseña con token
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => [
                'required',
                'confirmed',
                'min:8',
                'max:128',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&.]/',
            ],
        ], [
            'token.required' => 'El token es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.exists' => 'No encontramos una cuenta con ese correo electrónico.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no debe exceder 128 caracteres.',
            'password.regex' => 'La contraseña debe contener al menos una mayúscula, una minúscula, un número y un carácter especial.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            UsersAuthenticationLog::logEvent([
                'event' => 'password_reset',
                'success' => true,
                'email' => $request->email,
                'details' => 'Contraseña restablecida correctamente',
            ]);

            return $this->successResponse(null, 'Contraseña restablecida correctamente. Ahora puedes iniciar sesión.');
        }

        return $this->errorResponse(
            $status === Password::INVALID_TOKEN
                ? 'El enlace de restablecimiento ha expirado o no es válido. Solicita uno nuevo.'
                : 'No se pudo restablecer la contraseña. Intenta de nuevo.',
            null,
            400
        );
    }

    /**
     * Parser de User-Agent para auditoría
     */
    private function parseDevice(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Unknown';
        }

        $device = 'Desktop';
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            $device = preg_match('/iPad/', $userAgent) ? 'Tablet' : 'Mobile';
        }

        $browser = 'Unknown';
        if (preg_match('/Chrome/', $userAgent) && ! preg_match('/Edg/', $userAgent)) {
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
