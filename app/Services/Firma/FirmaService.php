<?php

namespace App\Services\Firma;

use App\Mail\OtpFirmaMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirmaService
{
    /**
     * Genera un OTP de 6 dÃgitos y lo envÃa al correo del usuario.
     */
    public function generarYEnviarOtp($user)
    {
        $otp = rand(100000, 999999);
        $cacheKey = "otp_firma_" . $user->id;

        // Guardar en cachÃ© por 5 minutos
        Cache::put($cacheKey, $otp, now()->addMinutes(5));

        // Enviar el correo
        Mail::to($user->email)->send(new OtpFirmaMail($otp, $user->name));

        return true;
    }

    /**
     * Valida que el OTP sea correcto y no haya expirado.
     */
    public function validarOtp($user, $otp)
    {
        $cacheKey = "otp_firma_" . $user->id;
        $otpGuardado = Cache::get($cacheKey);

        if (!$otpGuardado || $otpGuardado != $otp) {
            return false;
        }

        // Eliminar el OTP tras validarlo con Ã©xito
        Cache::forget($cacheKey);
        
        return true;
    }

    /**
     * Registra el evento de firma en la base de datos (PolimÃ³rfico).
     */
    public function registrarEventoFirma($user, $documentable, $data)
    {
        return DB::table("firmas_eventos")->insert([
            "documentable_id"   => $documentable->id,
            "documentable_type" => get_class($documentable),
            "user_id"           => $user->id,
            "hash_original"     => $data["hash_original"] ?? null,
            "hash_firmado"      => $data["hash_firmado"] ?? null,
            "otp_utilizado"     => $data["otp"],
            "ip_address"        => request()->ip(),
            "user_agent"        => request()->userAgent(),
            "fecha_firma"       => now(),
            "created_at"        => now(),
            "updated_at"        => now(),
        ]);
    }
}
