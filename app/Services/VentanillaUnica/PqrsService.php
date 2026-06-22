<?php

namespace App\Services\VentanillaUnica;

use App\Helpers\CalendarioHelper;
use App\Mail\OtpFirmaMail;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\Gestion\GestionTercero;
use App\Models\Transversal\FirmaEvento;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PqrsService
{
    public function crearDesdeRadicado(int $radicadoId, array $datosPqrs): VentanillaPqrs
    {
        return DB::transaction(function () use ($radicadoId, $datosPqrs) {
            $radicado = VentanillaRadicaReci::with('tercero')->findOrFail($radicadoId);

            $tercero = $radicado->tercero;

            $tipoPqrs = ConfigListaDetalle::find($datosPqrs['tipo_pqrs_id']);
            $tipoLabel = $tipoPqrs?->nombre ?? 'Peticion';

            $diasTermino = VentanillaPqrs::TERMINOS[$tipoLabel] ?? 15;

            if (($datosPqrs['prioridad'] ?? 'Normal') === 'Tutela') {
                $diasTermino = 2;
            }

            $prioridad = $datosPqrs['prioridad'] ?? 'Normal';
            $falloJudicial = $datosPqrs['fallo_judicial'] ?? 'No';
            $fechorTramite = $datosPqrs['fechor_tramite'] ?? now();
            $fechaVencimiento = CalendarioHelper::calcularVencimiento(Carbon::parse($fechorTramite), $diasTermino);

            $pqrs = VentanillaPqrs::create([
                'ventanilla_radica_reci_id' => $radicadoId,
                'gestion_tercero_id' => $tercero->id,
                'tipo_pqrs_id' => $datosPqrs['tipo_pqrs_id'],
                'clasificacion_documental_trd_id' => $datosPqrs['clasificacion_documental_trd_id'] ?? $radicado->clasifica_documen_id,
                'config_divi_poli_id_afectado' => $datosPqrs['config_divi_poli_id_afectado'] ?? $tercero->config_divi_poli_id ?? null,
                'prioridad' => $prioridad,
                'estado_tramite' => 'Pendiente',
                'fecha_vencimiento' => $fechaVencimiento,
                'fecha_vencimiento_original' => $fechaVencimiento,
                'tiene_prorroga' => false,
                'fallo_judicial' => $falloJudicial,
                'fechor_tramite' => Carbon::parse($fechorTramite),
                'num_docu_afectado' => $tercero->num_docu_nit,
                'nom_afectado' => $tercero->nom_razo_soci,
                'dir_afectado' => $tercero->direccion,
                'tel_afectado' => $tercero->telefono,
                'movil_afectado' => $tercero->movil,
                'detalle_solicitud' => $radicado->asunto,
                'observaciones' => $datosPqrs['observaciones'] ?? null,
                'modalidad' => $datosPqrs['modalidad'] ?? null,
                'autoridad_destino' => $datosPqrs['autoridad_destino'] ?? null,
                'derecho_solicitado' => $datosPqrs['derecho_solicitado'] ?? null,
                'area_afectada' => $datosPqrs['area_afectada'] ?? null,
                'funcionarios_implicados' => $datosPqrs['funcionarios_implicados'] ?? null,
                'derecho_vulnerado' => $datosPqrs['derecho_vulnerado'] ?? null,
                'pretension' => $datosPqrs['pretension'] ?? null,
                'area_mejora' => $datosPqrs['area_mejora'] ?? null,
                'motivo_felicitacion' => $datosPqrs['motivo_felicitacion'] ?? null,
                'tipo_persona' => $datosPqrs['tipo_persona'] ?? 'Natural',
            ]);

            $pqrs->load(['radicado', 'tercero', 'tipoPqrs', 'clasificacionDocumental']);

            $this->limpiarCacheEstadisticas();

            return $pqrs;
        });
    }

    public function crearIndependiente(array $datosPqrs): VentanillaPqrs
    {
        return DB::transaction(function () use ($datosPqrs) {
            $tipoPqrs = ConfigListaDetalle::find($datosPqrs['tipo_pqrs_id']);
            $tipoLabel = $tipoPqrs?->nombre ?? 'Peticion';

            $diasTermino = VentanillaPqrs::TERMINOS[$tipoLabel] ?? 15;

            if (($datosPqrs['prioridad'] ?? 'Normal') === 'Tutela') {
                $diasTermino = 2;
            }

            $prioridad = $datosPqrs['prioridad'] ?? 'Normal';
            $falloJudicial = $datosPqrs['fallo_judicial'] ?? 'No';
            $fechorTramite = $datosPqrs['fechor_tramite'] ?? now();
            $fechaVencimiento = CalendarioHelper::calcularVencimiento(Carbon::parse($fechorTramite), $diasTermino);

            $tercero = null;
            if (! empty($datosPqrs['gestion_tercero_id'])) {
                $tercero = GestionTercero::find($datosPqrs['gestion_tercero_id']);
            }

            $pqrs = VentanillaPqrs::create([
                'ventanilla_radica_reci_id' => $datosPqrs['ventanilla_radica_reci_id'] ?? null,
                'gestion_tercero_id' => $tercero?->id,
                'tipo_pqrs_id' => $datosPqrs['tipo_pqrs_id'],
                'clasificacion_documental_trd_id' => $datosPqrs['clasificacion_documental_trd_id'] ?? null,
                'config_divi_poli_id_afectado' => $datosPqrs['config_divi_poli_id_afectado'] ?? $tercero?->config_divi_poli_id ?? null,
                'prioridad' => $prioridad,
                'estado_tramite' => 'Pendiente',
                'fecha_vencimiento' => $fechaVencimiento,
                'fecha_vencimiento_original' => $fechaVencimiento,
                'tiene_prorroga' => false,
                'fallo_judicial' => $falloJudicial,
                'fechor_tramite' => Carbon::parse($fechorTramite),
                'num_docu_afectado' => $datosPqrs['num_docu_afectado'] ?? $tercero?->num_docu_nit,
                'nom_afectado' => $datosPqrs['nom_afectado'] ?? $tercero?->nom_razo_soci,
                'dir_afectado' => $datosPqrs['dir_afectado'] ?? $tercero?->direccion,
                'tel_afectado' => $datosPqrs['tel_afectado'] ?? $tercero?->telefono,
                'movil_afectado' => $datosPqrs['movil_afectado'] ?? $tercero?->movil,
                'detalle_solicitud' => $datosPqrs['detalle_solicitud'] ?? '',
                'observaciones' => $datosPqrs['observaciones'] ?? null,
                'modalidad' => $datosPqrs['modalidad'] ?? null,
                'autoridad_destino' => $datosPqrs['autoridad_destino'] ?? null,
                'derecho_solicitado' => $datosPqrs['derecho_solicitado'] ?? null,
                'area_afectada' => $datosPqrs['area_afectada'] ?? null,
                'funcionarios_implicados' => $datosPqrs['funcionarios_implicados'] ?? null,
                'derecho_vulnerado' => $datosPqrs['derecho_vulnerado'] ?? null,
                'pretension' => $datosPqrs['pretension'] ?? null,
                'area_mejora' => $datosPqrs['area_mejora'] ?? null,
                'motivo_felicitacion' => $datosPqrs['motivo_felicitacion'] ?? null,
                'tipo_persona' => $datosPqrs['tipo_persona'] ?? 'Natural',
            ]);

            $pqrs->load(['radicado', 'tercero', 'tipoPqrs', 'clasificacionDocumental']);

            $this->limpiarCacheEstadisticas();

            return $pqrs;
        });
    }

    public function aplicarProrroga(int $pqrsId): VentanillaPqrs
    {
        $pqrs = VentanillaPqrs::with('tipoPqrs')->findOrFail($pqrsId);

        if ($pqrs->tiene_prorroga) {
            throw new \Exception('Esta PQRS ya tiene una prórroga aplicada.');
        }

        $pqrs->aplicarProrroga();

        $pqrs->load(['radicado', 'tercero', 'tipoPqrs', 'clasificacionDocumental']);

        $this->limpiarCacheEstadisticas();

        return $pqrs;
    }

    public function cambiarEstado(int $pqrsId, string $nuevoEstado, ?string $fechaRespuesta = null): VentanillaPqrs
    {
        $pqrs = VentanillaPqrs::findOrFail($pqrsId);

        $updateData = ['estado_tramite' => $nuevoEstado];

        if ($nuevoEstado === 'Respondida') {
            $updateData['fecha_respuesta'] = $fechaRespuesta ?? now();
        }

        $pqrs->update($updateData);

        $pqrs->load(['radicado', 'tercero', 'tipoPqrs', 'clasificacionDocumental']);

        $this->limpiarCacheEstadisticas();

        return $pqrs;
    }

    public function getEstadisticas(): array
    {
        $cacheKey = 'ventanilla_pqrs_estadisticas';
        $cached = Cache::get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $baseQuery = VentanillaPqrs::whereNotNull('ventanilla_radica_reci_id');

        $total = (clone $baseQuery)->count();
        $pendientes = (clone $baseQuery)->where('estado_tramite', 'Pendiente')->count();
        $enTramite = (clone $baseQuery)->where('estado_tramite', 'En Tramite')->count();
        $respondidas = (clone $baseQuery)->where('estado_tramite', 'Respondida')->count();
        $vencidas = (clone $baseQuery)->where('estado_tramite', 'Vencida')->count();
        $urgentes = (clone $baseQuery)->where('prioridad', 'Urgente')
            ->whereIn('estado_tramite', ['Pendiente', 'En Tramite'])
            ->count();
        $tutelas = (clone $baseQuery)->where('prioridad', 'Tutela')
            ->whereIn('estado_tramite', ['Pendiente', 'En Tramite'])
            ->count();

        $proximoVencer = (clone $baseQuery)->with(['radicado.tercero', 'tipoPqrs'])
            ->whereIn('estado_tramite', ['Pendiente', 'En Tramite'])
            ->whereDate('fecha_vencimiento', '<=', now()->addDays(5))
            ->whereDate('fecha_vencimiento', '>=', now())
            ->orderBy('fecha_vencimiento')
            ->limit(10)
            ->get();

        $proximoVencer->transform(function ($item) {
            $item->dias_habiles_restantes = $item->getDiasHabilesRestantes();

            return $item;
        });

        $estadisticas = [
            'total' => $total,
            'pendientes' => $pendientes,
            'en_tramite' => $enTramite,
            'respondidas' => $respondidas,
            'vencidas' => $vencidas,
            'urgentes' => $urgentes,
            'tutelas' => $tutelas,
            'proximo_vencer' => $proximoVencer,
        ];

        Cache::put($cacheKey, $estadisticas, now()->addMinutes(10));

        return $estadisticas;
    }

    private function limpiarCacheEstadisticas(): void
    {
        Cache::forget('ventanilla_pqrs_estadisticas');
    }

    /**
     * Genera un OTP de 6 dígitos y lo envía al correo del usuario para firmar PQRS.
     */
    public function solicitarOtpFirma($user, VentanillaPqrs $pqrs): bool
    {
        $otp = (string) random_int(100000, 999999);
        $cacheKey = "pqrs_firma_otp_{$pqrs->id}_{$user->id}";

        Cache::put($cacheKey, $otp, now()->addMinutes(5));

        Mail::to($user->email)->send(new OtpFirmaMail($otp, $user->nombres));

        return true;
    }

    /**
     * Valida que el OTP sea correcto y no haya expirado para firma de PQRS.
     */
    public function validarOtpFirma($user, string $otp, VentanillaPqrs $pqrs): bool
    {
        $cacheKey = "pqrs_firma_otp_{$pqrs->id}_{$user->id}";
        $otpGuardado = Cache::get($cacheKey);

        if (! $otpGuardado || $otpGuardado !== $otp) {
            return false;
        }

        Cache::forget($cacheKey);

        return true;
    }

    /**
     * Registra la firma electrónica de un PQRS.
     */
    public function guardarFirma(VentanillaPqrs $pqrs, array $data, $user): VentanillaPqrs
    {
        $pqrs->update([
            'estado_firma' => 'firmada',
            'firma_digital' => $data['firma_digital'],
            'fecha_firma' => now(),
            'ip_firma' => request()->ip(),
            'firmado_en_representacion' => $data['firmado_en_representacion'] ?? false,
            'nombre_representado' => $data['nombre_representado'] ?? null,
        ]);

        FirmaEvento::create([
            'documentable_id' => $pqrs->id,
            'documentable_type' => get_class($pqrs),
            'user_id' => $user->id,
            'hash_original' => null,
            'hash_firmado' => hash('sha256', $data['firma_digital']),
            'otp_utilizado' => '***',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'fecha_firma' => now(),
        ]);

        $pqrs->load(['radicado', 'tercero', 'tipoPqrs', 'clasificacionDocumental']);

        return $pqrs;
    }

    /**
     * Anula una PQRS con motivo.
     */
    public function anularPqrs(VentanillaPqrs $pqrs, string $motivo): VentanillaPqrs
    {
        $observacionesActuales = $pqrs->observaciones ?? '';
        $nuevasObservaciones = trim($observacionesActuales."\n[ANULADO] ".$motivo);

        $pqrs->update([
            'estado_tramite' => 'Vencida',
            'observaciones' => $nuevasObservaciones,
        ]);

        $pqrs->load(['radicado', 'tercero', 'tipoPqrs', 'clasificacionDocumental']);

        $this->limpiarCacheEstadisticas();

        return $pqrs;
    }

    /**
     * Obtiene PQRS con firma pendiente.
     */
    public function pendientesFirma()
    {
        return VentanillaPqrs::with(['radicado', 'tercero', 'tipoPqrs'])
            ->where('estado_firma', 'pendiente')
            ->whereNotNull('ventanilla_radica_reci_id')
            ->latest()
            ->paginate(15);
    }
}
