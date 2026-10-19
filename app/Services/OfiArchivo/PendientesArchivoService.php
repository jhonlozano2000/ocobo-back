<?php

namespace App\Services\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\Workflows\WorkflowInstancia;
use App\Services\VentanillaUnica\RadicadoEstadoTrabajoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Bandeja "Pendientes de archivar" (diseño de expedientes §3.4 y §6.1, Acuerdo AGN 001/2024
 * A.4.2.5 par.): lo que el usuario debe llevar a un expediente, calculado al vuelo.
 *
 * - Radicados recibidos ya respondidos que no están en ningún expediente.
 * - Instancias de workflow completadas cuyos archivos no se pudieron incorporar (M19-30).
 * - Expedientes abiertos sin incorporaciones en los últimos meses (decisión D4). No se cierran
 *   solos: el cierre exige que el trámite haya terminado.
 *
 * Cada sección conserva el acceso de su fuente: radicados con su ABAC jerárquico, instancias
 * solo si su radicado es visible y expedientes con su alcance por dependencia.
 */
class PendientesArchivoService
{
    /** Máximo de filas por sección; el total real va en "totales". */
    public const LIMITE = 100;

    /** Estados del radicado que indican que el trámite ya se respondió. */
    public const ESTADOS_RESPONDIDO = [
        RadicadoEstadoTrabajoService::ESTADO_FINALIZADO,
        RadicadoEstadoTrabajoService::ESTADO_TRAMITADO,
    ];

    /** Resultados del archivo de un flujo que requieren acción (ver ArchivoFlujoEnExpedienteService). */
    public const ARCHIVO_PENDIENTE = ['sin_expediente', 'error'];

    /** Última incorporación del expediente (incluidos folios retirados) o, sin documentos, su apertura. */
    private const ULTIMA_ACTIVIDAD = 'COALESCE((SELECT MAX(d.created_at) FROM ofi_archivo_expedientes_documentos d '
        .'WHERE d.expediente_id = ofi_archivo_expedientes.id), ofi_archivo_expedientes.fecha_apertura)';

    public static function mesesInactividad(): int
    {
        return max(1, (int) config('archivo.meses_inactividad', 6));
    }

    public function para(User $user): array
    {
        $radicados = $this->radicadosSinExpediente($user);
        $instancias = $this->instanciasSinArchivar($user);
        $inactivos = self::inactivos(OfiArchivoExpediente::visiblesPara($user));

        return [
            'meses_inactividad' => self::mesesInactividad(),
            'radicados' => (clone $radicados)->latest()->limit(self::LIMITE)
                ->get(['id', 'num_radicado', 'asunto', 'estado_trabajo', 'created_at']),
            'instancias' => $instancias
                ? (clone $instancias)->with(['workflow:id,nombre', 'radicado:id,num_radicado'])->latest('fecha_fin')->limit(self::LIMITE)
                    ->get(['id', 'workflow_id', 'radicado_id', 'estado', 'fecha_fin', 'datos_contexto'])
                    ->map(fn ($i) => [
                        'id' => $i->id,
                        'workflow' => $i->workflow?->nombre,
                        'radicado_id' => $i->radicado_id,
                        'num_radicado' => $i->radicado?->num_radicado,
                        'fecha_fin' => $i->fecha_fin,
                        'archivo' => $i->datos_contexto['expediente'] ?? null,
                    ])
                : [],
            'inactivos' => (clone $inactivos)->orderBy('ultima_actividad')->limit(self::LIMITE)->get(),
            'totales' => [
                'radicados' => (clone $radicados)->count(),
                'instancias' => $instancias ? (clone $instancias)->count() : 0,
                'inactivos' => (clone $inactivos)->count(),
            ],
        ];
    }

    /**
     * Expedientes abiertos cuya última incorporación (o su apertura, si no tiene documentos) es
     * anterior al umbral de inactividad. Agrega la columna "ultima_actividad".
     */
    public static function inactivos(Builder $query, ?int $meses = null): Builder
    {
        $umbral = now()->subMonths($meses ?? self::mesesInactividad())->toDateTimeString();

        return $query->where('ofi_archivo_expedientes.estado', 'abierto')
            ->select([
                'ofi_archivo_expedientes.id', 'numero_expediente', 'nombre_expediente', 'dependencia_id',
                'usuario_apertura_id', 'fecha_apertura',
            ])
            ->selectRaw(self::ULTIMA_ACTIVIDAD.' as ultima_actividad')
            ->whereRaw(self::ULTIMA_ACTIVIDAD.' < ?', [$umbral]);
    }

    private function radicadosSinExpediente(User $user): Builder
    {
        return VentanillaRadicaReci::conPermisoJerarquico($user)
            ->whereIn('estado_trabajo', self::ESTADOS_RESPONDIDO)
            ->whereNotIn('id', OfiArchivoExpedienteDocumento::where('documentable_type', VentanillaRadicaReci::class)
                ->where('activo', true)->whereNotNull('documentable_id')->select('documentable_id'));
    }

    private function instanciasSinArchivar(User $user): ?Builder
    {
        if (! Schema::hasColumn('workflow_instancias', 'radicado_id')) {
            return null;
        }

        return WorkflowInstancia::where('estado', 'completada')
            ->whereIn('datos_contexto->expediente->estado', self::ARCHIVO_PENDIENTE)
            ->whereIn('radicado_id', VentanillaRadicaReci::conPermisoJerarquico($user)->select('id'));
    }
}
