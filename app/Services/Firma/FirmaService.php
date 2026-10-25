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

    /** Máximo de documentos por lote de firma masiva. */
    public const MAX_LOTE = 50;

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
     * M03-19: el firmante ya firmó ese documento. Internos: su fila de firmante;
     * enviados (sin estado por firmante): su evento de firma sobre el documento.
     */
    public function yaFirmo(string $documentableType, int $documentableId, int $userId): bool
    {
        return match ($documentableType) {
            'radicado_interno' => DB::table('ventanilla_radica_internos_firmantes')
                ->where('radica_interno_id', $documentableId)
                ->where('users_id', $userId)
                ->where('firmado', 1)
                ->exists(),
            'radicado_enviado' => DB::table('firmas_eventos')
                ->where('documentable_type', VentanillaRadicaEnviados::class)
                ->where('documentable_id', $documentableId)
                ->where('user_id', $userId)
                ->exists(),
            default => false,
        };
    }

    /**
     * Con varios firmantes el documento queda pendiente hasta que firma el último.
     * Sin firmantes designados (lo firma un responsable) basta una firma.
     */
    public function todosFirmaron(string $documentableType, int $documentableId): bool
    {
        return match ($documentableType) {
            'radicado_interno' => ! DB::table('ventanilla_radica_internos_firmantes')
                ->where('radica_interno_id', $documentableId)
                ->where('firmado', 0)
                ->exists(),
            'radicado_enviado' => ! DB::table('ventanilla_radica_enviados_firmas as f')
                ->join('users_cargos as uc', 'uc.id', '=', 'f.users_cargos_id')
                ->where('f.radica_enviado_id', $documentableId)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('firmas_eventos as e')
                    ->whereColumn('e.user_id', 'uc.user_id')
                    ->where('e.documentable_type', VentanillaRadicaEnviados::class)
                    ->where('e.documentable_id', $documentableId))
                ->exists(),
            default => true,
        };
    }

    /**
     * M03-19: registra la firma en la fila del firmante del radicado interno
     * (antes firmado/fecha_firma/otp_utilizado nunca se escribían).
     */
    public function marcarFirmante(string $documentableType, int $documentableId, int $userId, string $otp): void
    {
        if ($documentableType !== 'radicado_interno') {
            return;
        }

        DB::table('ventanilla_radica_internos_firmantes')
            ->where('radica_interno_id', $documentableId)
            ->where('users_id', $userId)
            ->where('firmado', 0)
            ->update([
                'firmado' => 1,
                'fecha_firma' => now(),
                'otp_utilizado' => '***'.substr($otp, -3),
                'updated_at' => now(),
            ]);
    }

    /**
     * Valida OTP por documento (recibidos/enviados/internos/grupos colaborativos).
     * El OTP es de un solo uso y se invalida tras OTP_MAX_INTENTOS fallidos.
     */
    public function validarOtpPorDocumento(string $otp, string $documentableType, int $documentableId, ?int $userId = null): bool
    {
        return $this->verificarOtp($this->claveOtp($documentableType, $documentableId, $userId), $otp);
    }

    /**
     * M06-13: un solo OTP para un lote de firma masiva, ligado al usuario y al conjunto exacto
     * de documentos (no sirve para otro lote ni para firmas individuales).
     *
     * @param  array<int, array{documentable_type: string, documentable_id: int}>  $documentos
     */
    public function generarYEnviarOtpLote(string $email, ?string $nombre, int $userId, array $documentos): void
    {
        $otp = $this->guardarOtp($this->claveOtpLote($userId, $documentos));

        MailConfigHelper::configureFromConfigVarias();
        Mail::to($email)->send(new OtpFirmaMail($otp, $nombre ?? 'Usuario'));
    }

    /**
     * @param  array<int, array{documentable_type: string, documentable_id: int}>  $documentos
     */
    public function validarOtpLote(string $otp, int $userId, array $documentos): bool
    {
        return $this->verificarOtp($this->claveOtpLote($userId, $documentos), $otp);
    }

    protected function claveOtpLote(int $userId, array $documentos): string
    {
        $firma = collect($documentos)
            ->map(fn ($d) => $d['documentable_type'].':'.(int) $d['documentable_id'])
            ->unique()->sort()->implode(',');

        return "otp_firma_lote_{$userId}_".hash('sha256', $firma);
    }

    /**
     * M06-13: documentos que el usuario tiene pendientes de firmar (los que la UI permite firmar):
     * enviados donde es firmante y aún no firmó, e internos donde su firma sigue pendiente.
     * Solo los que tienen PDF, porque sin él no hay qué estampar.
     */
    public function pendientesDe(int $userId): array
    {
        $enviados = DB::table('ventanilla_radica_enviados as r')
            ->join('ventanilla_radica_enviados_firmas as f', 'f.radica_enviado_id', '=', 'r.id')
            ->join('users_cargos as uc', 'uc.id', '=', 'f.users_cargos_id')
            ->where('uc.user_id', $userId)
            ->whereNotNull('r.archivo_digital')
            ->where(fn ($q) => $q->whereNull('r.estado_firma')->orWhere('r.estado_firma', '!=', 'firmado'))
            // Con varios firmantes, quien ya firmó no lo sigue viendo mientras faltan los demás
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('firmas_eventos as e')
                ->where('e.documentable_type', VentanillaRadicaEnviados::class)
                ->whereColumn('e.documentable_id', 'r.id')
                ->where('e.user_id', $userId))
            ->distinct()
            ->get(['r.id', 'r.num_radicado', 'r.asunto', 'r.created_at'])
            ->map(fn ($r) => $this->filaPendiente('radicado_enviado', $r));

        $internos = DB::table('ventanilla_radica_internos as r')
            ->join('ventanilla_radica_internos_firmantes as f', 'f.radica_interno_id', '=', 'r.id')
            ->where('f.users_id', $userId)
            ->where('f.firmado', 0)
            ->whereNotNull('r.archivo_digital')
            ->distinct()
            ->get(['r.id', 'r.num_radicado', 'r.asunto', 'r.created_at'])
            ->map(fn ($r) => $this->filaPendiente('radicado_interno', $r));

        return $enviados->concat($internos)->sortByDesc('created_at')->values()->all();
    }

    private function filaPendiente(string $tipo, object $r): array
    {
        return [
            'id' => "{$tipo}-{$r->id}",
            'documentable_type' => $tipo,
            'documentable_id' => (int) $r->id,
            'num_radicado' => $r->num_radicado,
            'asunto' => $r->asunto,
            'created_at' => $r->created_at,
        ];
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
