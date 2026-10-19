<?php

namespace App\Services\Gestion;

use App\Models\Gestion\GestionTercero;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteTercero;
use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\Workflows\WorkflowInstancia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * B24 (diseño de expedientes §2.1 y §6.4): "Todo lo del tercero". Consulta de solo lectura que
 * reúne sus radicados, PQRS, expedientes e instancias de workflow. No es una unidad archivística.
 *
 * Cada fuente conserva su propio control de acceso: radicados y PQRS con su ABAC jerárquico,
 * expedientes con su alcance por dependencia, e instancias solo si su radicado de origen es
 * visible. Sin el permiso de identidad reservada, las PQRS anónimas o de denuncia y sus
 * radicados no aparecen: la consulta por tercero no debe revelar al denunciante.
 */
class ConsultaIntegralTerceroService
{
    /** Máximo de filas por sección; el total real va en "totales". */
    public const LIMITE = 100;

    public function para(GestionTercero $tercero, User $user): array
    {
        $recibidos = $this->recibidos($tercero, $user);
        $enviados = VentanillaRadicaEnviados::conPermisoJerarquico($user)->where('tercero_id', $tercero->id);
        $pqrs = $this->pqrs($tercero, $user);
        $expedientes = $this->expedientes($tercero, $user);
        $instancias = $this->instancias($recibidos);

        $roles = $this->rolesPorExpediente($tercero);

        return [
            'tercero' => $tercero->only(['id', 'num_docu_nit', 'nom_razo_soci', 'tipo']),
            'radicados_recibidos' => (clone $recibidos)->latest()->limit(self::LIMITE)
                ->get(['id', 'num_radicado', 'asunto', 'created_at', 'estado_trabajo']),
            'radicados_enviados' => (clone $enviados)->latest()->limit(self::LIMITE)
                ->get(['id', 'num_radicado', 'asunto', 'created_at', 'estado_trabajo']),
            'pqrs' => (clone $pqrs)->with(['radicado:id,num_radicado', 'tipoPqrs:id,nombre'])->latest()->limit(self::LIMITE)
                ->get(['id', 'ventanilla_radica_reci_id', 'tipo_pqrs_id', 'estado_tramite', 'fecha_vencimiento', 'created_at']),
            'expedientes' => (clone $expedientes)->latest('fecha_apertura')->limit(self::LIMITE)
                ->get(array_merge(
                    ['id', 'numero_expediente', 'nombre_expediente', 'estado', 'fecha_apertura', 'fecha_cierre'],
                    // B06: hasta correr su migración no existe el nivel de acceso
                    Schema::hasColumn('ofi_archivo_expedientes', 'nivel_acceso') ? ['nivel_acceso'] : []
                ))
                ->each(fn ($e) => $e->setAttribute('roles', $roles[$e->id] ?? [])),
            'instancias' => $instancias
                ? (clone $instancias)->with(['workflow:id,nombre', 'radicado:id,num_radicado'])->latest()->limit(self::LIMITE)
                    ->get(['id', 'workflow_id', 'radicado_id', 'origen_tipo', 'origen_id', 'estado', 'fecha_inicio', 'fecha_fin', 'created_at'])
                : [],
            'totales' => [
                'radicados_recibidos' => (clone $recibidos)->count(),
                'radicados_enviados' => (clone $enviados)->count(),
                'pqrs' => (clone $pqrs)->count(),
                'expedientes' => (clone $expedientes)->count(),
                'instancias' => $instancias ? (clone $instancias)->count() : 0,
            ],
        ];
    }

    private function recibidos(GestionTercero $tercero, User $user): Builder
    {
        $query = VentanillaRadicaReci::conPermisoJerarquico($user)->where('tercero_id', $tercero->id);
        if (! VentanillaPqrs::puedeVerIdentidadReservada($user)) {
            $query->whereNotIn('id', $this->radicadosConIdentidadReservada());
        }

        return $query;
    }

    private function pqrs(GestionTercero $tercero, User $user): Builder
    {
        $query = VentanillaPqrs::conPermisoJerarquico($user)->where(function ($q) use ($tercero) {
            $q->where('gestion_tercero_id', $tercero->id)
                ->orWhereIn('ventanilla_radica_reci_id', VentanillaRadicaReci::where('tercero_id', $tercero->id)->select('id'));
        });
        VentanillaPqrs::restringirBusquedaIdentidad($query, $user);

        return $query;
    }

    /**
     * Expedientes vinculados al tercero o que contienen sus radicados, dentro del alcance del usuario.
     */
    private function expedientes(GestionTercero $tercero, User $user): Builder
    {
        $reservada = ! VentanillaPqrs::puedeVerIdentidadReservada($user);

        return OfiArchivoExpediente::visiblesPara($user)->where(function ($q) use ($tercero, $reservada) {
            if (Schema::hasTable('ofi_archivo_expediente_terceros')) {
                $q->whereIn('id', OfiArchivoExpedienteTercero::where('tercero_id', $tercero->id)->select('expediente_id'));
            }
            $q->orWhereIn('id', function ($docs) use ($tercero, $reservada) {
                $docs->select('expediente_id')->from('ofi_archivo_expedientes_documentos')
                    ->where(function ($d) use ($tercero, $reservada) {
                        $d->where('documentable_type', VentanillaRadicaReci::class)
                            ->whereIn('documentable_id', function ($r) use ($tercero, $reservada) {
                                $r->select('id')->from('ventanilla_radica_reci')->where('tercero_id', $tercero->id);
                                if ($reservada) {
                                    $r->whereNotIn('id', $this->radicadosConIdentidadReservada());
                                }
                            });
                    })
                    ->orWhere(function ($d) use ($tercero) {
                        $d->where('documentable_type', VentanillaRadicaEnviados::class)
                            ->whereIn('documentable_id', VentanillaRadicaEnviados::where('tercero_id', $tercero->id)->select('id'));
                    });
            });
        });
    }

    /** Instancias cuyo radicado de origen (el de la PQRS si nació de una) es visible. */
    private function instancias(Builder $recibidos): ?Builder
    {
        if (! Schema::hasColumn('workflow_instancias', 'radicado_id')) {
            return null;
        }

        return WorkflowInstancia::whereIn('radicado_id', (clone $recibidos)->select('id'));
    }

    /** @return array<int, list<string>> */
    private function rolesPorExpediente(GestionTercero $tercero): array
    {
        if (! Schema::hasTable('ofi_archivo_expediente_terceros')) {
            return [];
        }

        return OfiArchivoExpedienteTercero::where('tercero_id', $tercero->id)->get(['expediente_id', 'rol'])
            ->groupBy('expediente_id')->map(fn ($v) => $v->pluck('rol')->values()->all())->all();
    }

    /** Radicados de origen de PQRS anónimas o de denuncia. */
    private function radicadosConIdentidadReservada(): \Closure
    {
        return fn ($q) => $q->select('vp.ventanilla_radica_reci_id')->from('ventanilla_pqrs as vp')
            ->leftJoin('config_listas_detalles as cld', 'cld.id', '=', 'vp.tipo_pqrs_id')
            ->whereNotNull('vp.ventanilla_radica_reci_id')
            ->where(fn ($w) => $w->where('vp.es_anonimo', true)->orWhere('cld.nombre', 'like', 'denuncia%'));
    }
}
