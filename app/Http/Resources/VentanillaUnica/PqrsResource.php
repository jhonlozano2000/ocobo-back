<?php

namespace App\Http\Resources\VentanillaUnica;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PqrsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ventanilla_radica_reci_id' => $this->ventanilla_radica_reci_id,
            'num_radicado' => $this->radicado?->num_radicado,
            'estado_tramite' => $this->estado_tramite,
            'prioridad' => $this->prioridad,
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'),
            'fecha_vencimiento_original' => $this->fecha_vencimiento_original?->format('Y-m-d'),
            'tiene_prorroga' => $this->tiene_prorroga,
            'dias_habiles_restantes' => $this->dias_habiles_restantes ?? $this->getDiasHabilesRestantes(),
            'estado_color' => $this->getEstadoColor(),
            'vuexy_badges' => [
                'estado_tramite' => $this->getVuexyEstadoBadge(),
                'prioridad' => $this->getVuexyPrioridadBadge(),
                'vencimiento' => $this->getVuexyVencimientoBadge(),
            ],
            'tipo_pqrs' => $this->tipoPqrs ? [
                'id' => $this->tipoPqrs->id,
                'nombre' => $this->tipoPqrs->nombre,
            ] : null,
            'afectado' => [
                'id' => $this->gestion_tercero_id,
                'nombre' => $this->nom_afectado,
                'documento' => $this->num_docu_afectado,
                'direccion' => $this->dir_afectado,
                'telefono' => $this->tel_afectado,
                'movil' => $this->movil_afectado,
            ],
            'clasificacion' => $this->clasificacionDocumental ? [
                'id' => $this->clasificacionDocumental->id,
                'nombre' => $this->clasificacionDocumental->getNombreCompleto(),
                'codigo' => $this->clasificacionDocumental->getCodigoCompleto(),
            ] : null,
            'fallo_judicial' => $this->fallo_judicial,
            'fechor_tramite' => $this->fechor_tramite?->format('Y-m-d H:i:s'),
            'detalle_solicitud' => $this->detalle_solicitud,
            'observaciones' => $this->observaciones,
            'radicado' => $this->radicado ? [
                'id' => $this->radicado->id,
                'num_radicado' => $this->radicado->num_radicado,
                'asunto' => $this->radicado->asunto,
                'fec_venci' => $this->radicado->fec_venci?->format('Y-m-d'),
                'clasificacion' => $this->radicado->clasificacionDocumental ? [
                    'id' => $this->radicado->clasificacionDocumental->id,
                    'nombre' => $this->radicado->clasificacionDocumental->nom,
                ] : null,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Obtiene la clase de badge Vuexy para el estado de trámite.
     */
    protected function getVuexyEstadoBadge(): array
    {
        $map = [
            'Pendiente' => ['class' => 'badge-light-warning', 'color' => 'warning', 'label' => 'Pendiente'],
            'En Tramite' => ['class' => 'badge-light-info', 'color' => 'info', 'label' => 'En Trámite'],
            'Respondida' => ['class' => 'badge-light-success', 'color' => 'success', 'label' => 'Respondida'],
            'Vencida' => ['class' => 'badge-light-danger', 'color' => 'danger', 'label' => 'Vencida'],
        ];

        return $map[$this->estado_tramite] ?? ['class' => 'badge-light-secondary', 'color' => 'secondary', 'label' => $this->estado_tramite];
    }

    /**
     * Obtiene la clase de badge Vuexy para la prioridad.
     */
    protected function getVuexyPrioridadBadge(): array
    {
        $map = [
            'Normal' => ['class' => 'badge-light-success', 'color' => 'success', 'label' => 'Normal'],
            'Urgente' => ['class' => 'badge-light-warning', 'color' => 'warning', 'label' => 'Urgente'],
            'Tutela' => ['class' => 'badge-light-danger', 'color' => 'danger', 'label' => 'Tutela'],
        ];

        return $map[$this->prioridad] ?? ['class' => 'badge-light-secondary', 'color' => 'secondary', 'label' => $this->prioridad];
    }

    /**
     * Obtiene la clase de badge Vuexy para el estado de vencimiento.
     */
    protected function getVuexyVencimientoBadge(): array
    {
        $dias = $this->getDiasHabilesRestantes();

        if ($this->estado_tramite === 'Respondida') {
            return ['class' => 'badge-light-success', 'color' => 'success', 'label' => 'Respondida'];
        }
        if ($this->estado_tramite === 'Vencida' || $dias < 0) {
            return ['class' => 'badge-light-danger', 'color' => 'danger', 'label' => 'Vencida'];
        }
        if ($dias <= 2) {
            return ['class' => 'badge-light-danger', 'color' => 'danger', 'label' => 'Crítico'];
        }
        if ($dias <= 5) {
            return ['class' => 'badge-light-warning', 'color' => 'warning', 'label' => 'Urgente'];
        }
        return ['class' => 'badge-light-info', 'color' => 'info', 'label' => 'En término'];
    }
}