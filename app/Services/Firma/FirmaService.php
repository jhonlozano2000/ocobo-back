<?php

namespace App\Services\Firma;

use App\Helpers\MailConfigHelper;
use App\Mail\OtpFirmaMail;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class FirmaService
{
    /**
     * Tipos de documento firmables: alias => [modelo, disco].
     */
    public const TIPOS_DOCUMENTO = [
        'radicado_recibido' => [VentanillaRadicaReci::class, 'radicados_recibidos'],
        'radicado_enviado' => [VentanillaRadicaEnviados::class, 'radicados_enviados'],
        'radicado_interno' => [VentanillaRadicaInterno::class, 'radicados_internos'],
    ];

    public const OTP_MINUTOS = 5;

    public const OTP_MAX_INTENTOS = 5;

    /**
     * M06-02: el usuario es firmante designado del documento.
     * Recibidos: responsable. Enviados: firmante o responsable. Internos: firmante o responsable.
     */
    public function esFirmanteDesignado(string $documentableType, int $documentableId, int $userId): bool
    {
        $porCargo = fn (string $tabla, string $columna) => DB::table($tabla)
            ->join('users_cargos', 'users_cargos.id', '=', "{$tabla}.users_cargos_id")
            ->where("{$tabla}.{$columna}", $documentableId)
            ->where('users_cargos.user_id', $userId)
            ->exists();

        return match ($documentableType) {
            'radicado_recibido' => $porCargo('ventanilla_radica_reci_responsa', 'radica_reci_id'),
            'radicado_enviado' => $porCargo('ventanilla_radica_enviados_firmas', 'radica_enviado_id')
                || $porCargo('ventanilla_radica_enviados_responsable', 'radica_enviado_id'),
            'radicado_interno' => DB::table('ventanilla_radica_internos_firmantes')
                ->where('radica_interno_id', $documentableId)->where('users_id', $userId)->exists()
                || $porCargo('ventanilla_radica_internos_responsa', 'radica_interno_id'),
            default => false,
        };
    }

    /**
     * Genera un OTP de 6 digitos ligado al documento (y al usuario, si se indica) y lo envia por correo.
     */
    public function generarYEnviarOtpParaEmail(string $email, ?string $nombre, string $documentableType, int $documentableId, ?int $userId = null): bool
    {
        $otp = $this->guardarOtp($this->claveOtp($documentableType, $documentableId, $userId));

        MailConfigHelper::configureFromConfigVarias();
        Mail::to($email)->send(new OtpFirmaMail($otp, $nombre ?? 'Usuario'));

        return true;
    }

    /**
     * Valida OTP por documento (recibidos/enviados/internos/grupos colaborativos).
     * El OTP es de un solo uso y se invalida tras OTP_MAX_INTENTOS fallidos.
     */
    public function validarOtpPorDocumento(string $otp, string $documentableType, int $documentableId, ?int $userId = null): bool
    {
        return $this->verificarOtp($this->claveOtp($documentableType, $documentableId, $userId), $otp);
    }

    protected function claveOtp(string $documentableType, int $documentableId, ?int $userId): string
    {
        $suffix = $userId ? "_{$userId}" : '';

        return "otp_firma_{$documentableType}_{$documentableId}{$suffix}";
    }

    /**
     * M06-06/07: OTP con random_int y guardado solo como hash.
     */
    protected function guardarOtp(string $clave): string
    {
        $otp = (string) random_int(100000, 999999);
        $expira = now()->addMinutes(self::OTP_MINUTOS);

        Cache::put($clave, ['hash' => Hash::make($otp), 'intentos' => 0, 'expira' => $expira->getTimestamp()], $expira);

        return $otp;
    }

    protected function verificarOtp(string $clave, string $otp): bool
    {
        $datos = Cache::get($clave);

        if (! is_array($datos) || empty($datos['hash'])) {
            return false;
        }

        if (Hash::check($otp, $datos['hash'])) {
            // Un solo uso: solo una petición concurrente logra retirarlo
            return Cache::pull($clave) !== null;
        }

        $datos['intentos'] = ($datos['intentos'] ?? 0) + 1;
        $restante = ($datos['expira'] ?? 0) - now()->getTimestamp();

        if ($datos['intentos'] >= self::OTP_MAX_INTENTOS || $restante <= 0) {
            Cache::forget($clave);
        } else {
            Cache::put($clave, $datos, $restante);
        }

        return false;
    }
}
