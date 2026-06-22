<?php

namespace App\Services\Firma;

use App\Helpers\MailConfigHelper;
use App\Mail\OtpFirmaMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FirmaService
{
    /**
     * Genera un OTP de 6 digitos y lo envia al correo del usuario (PQRS).
     */
    public function generarYEnviarOtp($user)
    {
        $otp = rand(100000, 999999);
        $cacheKey = 'otp_firma_'.$user->id;

        Cache::put($cacheKey, $otp, now()->addMinutes(5));

        MailConfigHelper::configureFromConfigVarias();
        Mail::to($user->email)->send(new OtpFirmaMail($otp, $user->name));

        return true;
    }

    /**
     * Genera un OTP de 6 digitos y lo envia al correo de un email especifico (tercero).
     */
    public function generarYEnviarOtpParaEmail(string $email, ?string $nombre, string $documentableType, int $documentableId): bool
    {
        $otp = (string) random_int(100000, 999999);
        $cacheKey = "otp_firma_{$documentableType}_{$documentableId}";

        Cache::put($cacheKey, $otp, now()->addMinutes(5));

        MailConfigHelper::configureFromConfigVarias();
        Mail::to($email)->send(new OtpFirmaMail($otp, $nombre ?? 'Usuario'));

        return true;
    }

    /**
     * Valida que el OTP sea correcto y no haya expirado (por usuario - PQRS).
     */
    public function validarOtp($user, $otp)
    {
        $cacheKey = 'otp_firma_'.$user->id;
        $otpGuardado = Cache::get($cacheKey);

        if (! $otpGuardado || $otpGuardado != $otp) {
            return false;
        }

        Cache::forget($cacheKey);

        return true;
    }

    /**
     * Valida OTP por documento (recibidos/enviados/internos).
     */
    public function validarOtpPorDocumento(string $otp, string $documentableType, int $documentableId): bool
    {
        $cacheKey = "otp_firma_{$documentableType}_{$documentableId}";
        $otpGuardado = Cache::get($cacheKey);

        if (! $otpGuardado || $otpGuardado != $otp) {
            return false;
        }

        Cache::forget($cacheKey);

        return true;
    }

    /**
     * Registra el evento de firma en la base de datos (Polimorfico).
     */
    public function registrarEventoFirma($user, $documentable, $data)
    {
        return DB::table('firmas_eventos')->insert([
            'documentable_id' => $documentable->id,
            'documentable_type' => get_class($documentable),
            'user_id' => $user->id,
            'hash_original' => $data['hash_original'] ?? null,
            'hash_firmado' => $data['hash_firmado'] ?? null,
            'otp_utilizado' => $data['otp'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'fecha_firma' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
