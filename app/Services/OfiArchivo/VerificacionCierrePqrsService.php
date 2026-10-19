<?php

namespace App\Services\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaHistorialNotificacion;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;

/**
 * Diseño de expedientes §6.2 [Acuerdo AGN 001/2024, A.4.2.10]: al responder una PQRS, la
 * petición, la respuesta y la constancia de notificación deben estar en un expediente.
 *
 * Solo informa; no bloquea la respuesta, porque el plazo de la PQRS es legal y no debe
 * depender del archivo. Basta con que cada documento esté en algún expediente; no se revela
 * en cuál, porque puede ser de otra dependencia.
 */
class VerificacionCierrePqrsService
{
    public function para(VentanillaPqrs $pqrs): array
    {
        $radicado = $pqrs->ventanilla_radica_reci_id ? VentanillaRadicaReci::find($pqrs->ventanilla_radica_reci_id) : null;
        $respuestas = $radicado ? $radicado->enviados()->get(['ventanilla_radica_enviados.id', 'ventanilla_radica_enviados.num_radicado']) : collect();

        $peticionArchivada = $radicado && $this->archivados(VentanillaRadicaReci::class, [$radicado->id]) !== [];
        $respuestasArchivadas = $this->archivados(VentanillaRadicaEnviados::class, $respuestas->pluck('id'));

        $faltantes = [];
        if (! $peticionArchivada) {
            $faltantes[] = 'La petición no está en ningún expediente.';
        }
        if ($respuestas->isEmpty()) {
            $faltantes[] = 'La PQRS no tiene un radicado enviado de respuesta.';
        } elseif (count($respuestasArchivadas) < $respuestas->count()) {
            $faltantes[] = 'La respuesta no está en ningún expediente.';
        }

        return [
            'completo' => $faltantes === [],
            'peticion' => ['num_radicado' => $radicado?->num_radicado, 'en_expediente' => $peticionArchivada],
            'respuestas' => $respuestas->map(fn ($r) => [
                'id' => $r->id,
                'num_radicado' => $r->num_radicado,
                'en_expediente' => in_array((int) $r->id, $respuestasArchivadas, true),
            ])->values()->all(),
            // El historial de correos no es un documento del expediente: se informa, pero el acuse
            // o la constancia de notificación hay que incorporarlos aparte
            'constancia_notificacion' => [
                'verificable' => false,
                'correos_registrados' => $radicado ? VentanillaRadicaHistorialNotificacion::where('radicado_id', $radicado->id)->count() : 0,
            ],
            'faltantes' => $faltantes,
        ];
    }

    /** Ids de los documentos dados que están activos en algún expediente. */
    private function archivados(string $clase, iterable $ids): array
    {
        return OfiArchivoExpedienteDocumento::where('documentable_type', $clase)->whereIn('documentable_id', $ids)
            ->where('activo', true)->distinct()->pluck('documentable_id')->map(fn ($id) => (int) $id)->all();
    }
}
