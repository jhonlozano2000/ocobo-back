<?php

namespace App\Services\VentanillaUnica;

use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Carbon\Carbon;

class RadicadoEstadoTrabajoService
{
    public const ESTADO_RECIBIDO = 'RECIBIDO';
    public const ESTADO_EN_PROCESO = 'EN_PROCESO';
    public const ESTADO_POR_VENCER = 'POR_VENCER';
    public const ESTADO_VENCIDO = 'VENCIDO';
    public const ESTADO_FINALIZADO = 'FINALIZADO';

    public const DIAS_PROXIMO_VENCER = 5;

    public function calcularEstadoAutomatico(VentanillaRadicaReci $radicado): string
    {
        if ($this->estaVencido($radicado)) {
            return self::ESTADO_VENCIDO;
        }

        if ($this->estaProximoVencer($radicado)) {
            return self::ESTADO_POR_VENCER;
        }

        if ($this->tieneResponsables($radicado)) {
            return self::ESTADO_EN_PROCESO;
        }

        return self::ESTADO_RECIBIDO;
    }

    public function actualizarEstadoSiCambia(VentanillaRadicaReci $radicado): bool
    {
        $nuevoEstado = $this->calcularEstadoAutomatico($radicado);

        if ($radicado->estado_trabajo !== $nuevoEstado) {
            $radicado->update(['estado_trabajo' => $nuevoEstado]);
            return true;
        }

        return false;
    }

    public function estaVencido(VentanillaRadicaReci $radicado): bool
    {
        if (!$radicado->fec_venci) {
            return false;
        }

        return Carbon::parse($radicado->fec_venci)->isBefore(Carbon::today());
    }

    public function estaProximoVencer(VentanillaRadicaReci $radicado): bool
    {
        if (!$radicado->fec_venci) {
            return false;
        }

        $fechaLimite = Carbon::today()->addDays(self::DIAS_PROXIMO_VENCER);

        return Carbon::parse($radicado->fec_venci)->between(Carbon::today(), $fechaLimite);
    }

    public function tieneResponsables(VentanillaRadicaReci $radicado): bool
    {
        return $radicado->responsables()->exists();
    }

    public function getEstadoInfo(string $estado): array
    {
        $estados = [
            self::ESTADO_RECIBIDO => [
                'label' => 'Recibido',
                'color' => 'info',
                'icon' => 'inbox',
                'description' => 'Radicado recibido sin responsables asignados',
            ],
            self::ESTADO_EN_PROCESO => [
                'label' => 'En Proceso',
                'color' => 'warning',
                'icon' => 'clock',
                'description' => 'Radicado con responsables asignados',
            ],
            self::ESTADO_POR_VENCER => [
                'label' => 'Por Vencer',
                'color' => 'orange',
                'icon' => 'alert-triangle',
                'description' => 'Radicado próximo a vencer',
            ],
            self::ESTADO_VENCIDO => [
                'label' => 'Vencido',
                'color' => 'danger',
                'icon' => 'x-circle',
                'description' => 'Radicado con fecha de vencimiento superada',
            ],
            self::ESTADO_FINALIZADO => [
                'label' => 'Finalizado',
                'color' => 'success',
                'icon' => 'check-circle',
                'description' => 'Radicado tramitado completamente',
            ],
        ];

        return $estados[$estado] ?? [
            'label' => $estado,
            'color' => 'secondary',
            'icon' => 'circle',
            'description' => '',
        ];
    }

    public static function getEstados(): array
    {
        return [
            self::ESTADO_RECIBIDO,
            self::ESTADO_EN_PROCESO,
            self::ESTADO_POR_VENCER,
            self::ESTADO_VENCIDO,
            self::ESTADO_FINALIZADO,
        ];
    }

    public static function getColoresPorEstado(): array
    {
        return [
            self::ESTADO_RECIBIDO => '#00B5D8',
            self::ESTADO_EN_PROCESO => '#FFA500',
            self::ESTADO_POR_VENCER => '#FF6B35',
            self::ESTADO_VENCIDO => '#E53E3E',
            self::ESTADO_FINALIZADO => '#38A169',
        ];
    }
}
