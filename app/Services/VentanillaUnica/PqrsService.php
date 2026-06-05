<?php

namespace App\Services\VentanillaUnica;

use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\Gestion\GestionTercero;
use App\Helpers\CalendarioHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PqrsService
{
    public function crearDesdeRadicado(int $radicadoId, array $datosPqrs): VentanillaPqrs
    {
        return DB::transaction(function () use ($radicadoId, $datosPqrs) {
            $radicado = VentanillaRadicaReci::with('tercero')->findOrFail($radicadoId);

            $tercero = $radicado->tercero;

            $tipoPqrs = \App\Models\Configuracion\ConfigListaDetalle::find($datosPqrs['tipo_pqrs_id']);
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
            $tipoPqrs = \App\Models\Configuracion\ConfigListaDetalle::find($datosPqrs['tipo_pqrs_id']);
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
            if (!empty($datosPqrs['gestion_tercero_id'])) {
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
}