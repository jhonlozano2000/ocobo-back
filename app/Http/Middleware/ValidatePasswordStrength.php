<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de Validación de Contraseñas
 *
 * OWASP A02:2021 - Cryptographic Failures
 * Valida que las contraseñas cumplan con requisitos de seguridad.
 */
class ValidatePasswordStrength
{
    /**
     * Longitud mínima de contraseña
     */
    private const MIN_LENGTH = 8;

    /**
     * Longitud máxima de contraseña
     */
    private const MAX_LENGTH = 128;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo validar en requests que contengan passwords
        if ($this->shouldValidate($request)) {
            $this->validatePasswordStrength($request);
        }

        return $next($request);
    }

    /**
     * Determina si se debe validar el password
     */
    private function shouldValidate(Request $request): bool
    {
        $passwordFields = ['password', 'password_confirmation', 'current_password', 'new_password'];

        foreach ($passwordFields as $field) {
            if ($request->has($field) && !empty($request->input($field))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valida que el password cumpla con los requisitos de seguridad
     */
    private function validatePasswordStrength(Request $request): void
    {
        $password = $request->input('password') ?? $request->input('new_password');

        if (empty($password)) {
            return;
        }

        $errors = [];

        // Validar longitud mínima
        if (strlen($password) < self::MIN_LENGTH) {
            $errors['password'][] = "La contraseña debe tener al menos " . self::MIN_LENGTH . " caracteres.";
        }

        // Validar longitud máxima
        if (strlen($password) > self::MAX_LENGTH) {
            $errors['password'][] = "La contraseña no debe exceder " . self::MAX_LENGTH . " caracteres.";
        }

        // Validar al menos una mayúscula
        if (!preg_match('/[A-Z]/', $password)) {
            $errors['password'][] = "La contraseña debe contener al menos una letra mayúscula.";
        }

        // Validar al menos una minúscula
        if (!preg_match('/[a-z]/', $password)) {
            $errors['password'][] = "La contraseña debe contener al menos una letra minúscula.";
        }

        // Validar al menos un número
        if (!preg_match('/[0-9]/', $password)) {
            $errors['password'][] = "La contraseña debe contener al menos un número.";
        }

        // Validar al menos un carácter especial
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>_\-\[\]\'\\\+\=\/\*]/', $password)) {
            $errors['password'][] = "La contraseña debe contener al menos un carácter especial.";
        }

        // Validar que no contenga el email del usuario
        $email = $request->input('email') ?? $request->user()?->email;
        if ($email && stripos($password, explode('@', $email)[0]) !== false) {
            $errors['password'][] = "La contraseña no debe contener parte del correo electrónico.";
        }

        // Validar que no sea una contraseña común
        if ($this->isCommonPassword($password)) {
            $errors['password'][] = "Esta contraseña es demasiado común. Elija una más segura.";
        }

        if (!empty($errors)) {
            abort(response()->json([
                'success' => false,
                'message' => 'La contraseña no cumple con los requisitos de seguridad.',
                'errors' => $errors,
                'error' => 'PASSWORD_TOO_WEAK'
            ], 422));
        }
    }

    /**
     * Verifica si la contraseña está en la lista de contraseñas comunes
     */
    private function isCommonPassword(string $password): bool
    {
        $commonPasswords = [
            'password', 'password123', '123456', '12345678', '123456789',
            'qwerty', 'abc123', 'monkey', '1234567', 'letmein',
            'trustno1', 'dragon', 'baseball', 'iloveyou', 'master',
            'sunshine', 'ashley', 'football', 'shadow', '123123',
            '654321', 'superman', 'qazwsx', 'michael', 'password1',
            'password123', 'welcome', 'welcome1', 'ninja', 'mustang',
            'admin', 'admin123', 'login', 'passw0rd', 'hello',
            'charlie', 'donald', 'password12', 'qwerty123', 'starwars',
        ];

        return in_array(strtolower($password), $commonPasswords);
    }
}
