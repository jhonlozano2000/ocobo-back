<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorConfirmRequest;
use App\Http\Requests\Auth\TwoFactorDisableRequest;
use App\Http\Requests\Auth\TwoFactorVerifyRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use App\Models\UsersAuthenticationLog;
use App\Services\Auth\TwoFactorToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FALaravel\Google2FA;

class TwoFactorController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private Google2FA $google2fa
    ) {}

    /**
     * Setup - Generar secret + QR + recovery codes
     */
    public function setup(Request $request)
    {
        $user = $request->user();

        $secret = $this->google2fa->generateSecretKey();
        $user->two_factor_secret = $secret;
        $user->save();

        $qrSvg = $this->google2fa->getQRCodeSvg(
            $request->getHttpHost(),
            $user->email,
            $secret
        );

        return $this->successResponse([
            'secret' => $secret,
            'qr_svg' => $qrSvg,
        ], 'Escanea el código QR con tu aplicación de autenticación');
    }

    /**
     * Confirm - Activar 2FA verificando primer código TOTP
     */
    public function confirm(TwoFactorConfirmRequest $request)
    {
        $user = $request->user();

        if (!$user->two_factor_secret) {
            return $this->errorResponse('Primero debes generar el código QR.', null, 400);
        }

        $code = $request->input('code');

        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $code);

        if (!$valid) {
            UsersAuthenticationLog::logEvent([
                'user_id' => $user->id,
                'event' => 'mfa_code_failed',
                'success' => false,
                'details' => 'Código TOTP inválido al confirmar 2FA',
            ]);

            return $this->errorResponse('El código ingresado no es válido. Intenta de nuevo.', null, 422);
        }

        $user->two_factor_confirmed_at = now();

        // Generar recovery codes definitivos
        $recoveryCodes = [];
        $hashedCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $plain = strtoupper(bin2hex(random_bytes(5)));
            $recoveryCodes[] = $plain;
            $hashedCodes[] = Hash::make($plain);
        }
        $user->two_factor_recovery_codes = $hashedCodes;
        $user->save();

        UsersAuthenticationLog::logEvent([
            'user_id' => $user->id,
            'event' => '2fa_enabled',
            'success' => true,
            'details' => '2FA activado correctamente',
        ]);

        return $this->successResponse([
            'recovery_codes' => $recoveryCodes,
        ], 'Autenticación de dos factores activada correctamente. Guarda estos códigos de recuperación en un lugar seguro.');
    }

    /**
     * Disable - Desactivar 2FA (requiere password)
     */
    public function disable(TwoFactorDisableRequest $request)
    {
        $user = $request->user();

        if (!Hash::check($request->input('password'), $user->password)) {
            return $this->errorResponse('La contraseña no es correcta.', null, 422);
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        UsersAuthenticationLog::logEvent([
            'user_id' => $user->id,
            'event' => '2fa_disabled',
            'success' => true,
            'details' => '2FA desactivado correctamente',
        ]);

        return $this->successResponse(null, 'Autenticación de dos factores desactivada.');
    }

    /**
     * Verify - Segundo paso del login
     */
    public function verify(TwoFactorVerifyRequest $request)
    {
        $token = $request->input('two_factor_token');
        $code = $request->input('code');

        // Validar token
        $userId = TwoFactorToken::validate($token);
        if (!$userId) {
            return $this->errorResponse('El token ha expirado o no es válido. Inicia sesión de nuevo.', null, 401);
        }

        $user = User::find($userId);
        if (!$user || !$user->twoFactorEnabled) {
            return $this->errorResponse('Usuario no encontrado o 2FA no está activo.', null, 401);
        }

        // Intentar verificar TOTP con tolerancia de 1 período
        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $code, 1);

        // Si TOTP falla, probar recovery codes
        if (!$valid) {
            $recoveryCodes = $user->two_factor_recovery_codes;
            if ($recoveryCodes) {
                foreach ($recoveryCodes as $index => $hashedCode) {
                    if (Hash::check($code, $hashedCode)) {
                        unset($recoveryCodes[$index]);
                        $user->two_factor_recovery_codes = array_values($recoveryCodes);
                        $user->save();
                        $valid = true;
                        break;
                    }
                }
            }
        }

        if (!$valid) {
            UsersAuthenticationLog::logEvent([
                'user_id' => $user->id,
                'event' => 'mfa_code_failed',
                'success' => false,
                'details' => 'Código 2FA inválido en login',
            ]);

            return $this->errorResponse('El código ingresado no es válido.', null, 422);
        }

        // Autenticar sesión
        Auth::login($user);
        $request->session()->regenerate();

        UsersAuthenticationLog::logEvent([
            'user_id' => $user->id,
            'event' => 'mfa_code_verified',
            'success' => true,
            'details' => '2FA verificado correctamente en login',
        ]);

        return $this->successResponse([
            'user' => new UserResource($user),
        ], 'Autenticación exitosa');
    }
}
