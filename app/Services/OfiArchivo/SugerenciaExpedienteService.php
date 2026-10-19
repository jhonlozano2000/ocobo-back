<?php

namespace App\Services\OfiArchivo;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoExpedienteTercero;
use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * B09 (diseño de expedientes §3.1 y §6.1, decisión D1): el expediente se sugiere, no se abre
 * solo. Para un radicado recibido, una PQRS o un radicado enviado devuelve en qué expedientes
 * está, los expedientes abiertos que el usuario puede ver y los datos para abrir uno nuevo.
 *
 * Orden de los sugeridos: primero los que contienen el recibido al que responde el enviado
 * (la respuesta va en el mismo expediente, §6.1), luego los del mismo tercero y luego el resto
 * de la serie o subserie.
 *
 * Con una PQRS anónima o de denuncia, y sin el permiso de identidad reservada, no se usa el
 * tercero para ordenar: la sugerencia no debe revelar quién la presentó.
 */
class SugerenciaExpedienteService
{
    /** Máximo de expedientes sugeridos. */
    public const LIMITE = 20;

    /** Tipo de origen => tipo de documento con que se incorpora al expediente. */
    public const ORIGENES = [
        'radicado_recibido' => 'radicado_recibido',
        'pqrs' => 'radicado_recibido',
        'radicado_enviado' => 'radicado_enviado',
    ];

    /**
     * @return array|null null si el origen no existe o el usuario no tiene acceso
     */
    public function para(string $origenTipo, int $origenId, User $user): ?array
    {
        [$radicado, $pqrs] = $this->origen($origenTipo, $origenId, $user);
        if (! $radicado) {
            return null;
        }

        if ($radicado instanceof VentanillaRadicaReci) {
            $pqrs ??= VentanillaPqrs::where('ventanilla_radica_reci_id', $radicado->id)->first();
        }
        $terceroId = $pqrs?->ocultaIdentidadPara($user) ? null : ($pqrs?->gestion_tercero_id ?? $radicado->tercero_id);

        $nodo = ClasificacionDocumentalTRD::find($pqrs?->clasificacion_documental_trd_id ?? $radicado->clasifica_documen_id);
        [$serie, $subserie, $tipoDocumental] = $this->clasificacion($nodo);

        // Expedientes que ya contienen los recibidos a los que responde el enviado
        $deLaPeticion = $radicado instanceof VentanillaRadicaEnviados
            ? $this->expedientesCon(VentanillaRadicaReci::class, $radicado->recibidos()->pluck('ventanilla_radica_reci.id'))
            : [];

        $respuesta = [
            'radicado' => $radicado->only(['id', 'num_radicado', 'asunto', 'estado_trabajo']) + ['documentable_type' => self::ORIGENES[$origenTipo]],
            'clasificacion' => [
                'serie' => $serie?->only(['id', 'cod', 'nom']),
                'subserie' => $subserie?->only(['id', 'cod', 'nom']),
                'tipo_documental' => $tipoDocumental?->only(['id', 'cod', 'nom']),
            ],
            'expedientes_actuales' => $this->actuales($radicado, $user),
            'sugeridos' => $serie || $deLaPeticion ? $this->sugeridos($serie, $subserie, $deLaPeticion, $terceroId, $radicado, $user) : [],
            'apertura' => $this->apertura($serie, $subserie, $radicado, $user),
        ];

        // §6.2 [A.4.2.10]: qué falta archivar para cerrar la PQRS
        if ($origenTipo === 'pqrs') {
            $respuesta['verificacion_cierre'] = app(VerificacionCierrePqrsService::class)->para($pqrs);
        }

        return $respuesta;
    }

    /**
     * @return array{0: ?Model, 1: ?VentanillaPqrs}
     */
    private function origen(string $tipo, int $id, User $user): array
    {
        if ($tipo === 'pqrs') {
            $pqrs = VentanillaPqrs::conPermisoJerarquico($user)->find($id);

            return [$pqrs?->ventanilla_radica_reci_id ? VentanillaRadicaReci::find($pqrs->ventanilla_radica_reci_id) : null, $pqrs];
        }
        if ($tipo === 'radicado_enviado') {
            return [VentanillaRadicaEnviados::conPermisoJerarquico($user)->find($id), null];
        }

        return [VentanillaRadicaReci::conPermisoJerarquico($user)->find($id), null];
    }

    /**
     * El radicado puede estar clasificado en una serie, una subserie o un tipo documental.
     *
     * @return array{0: ?ClasificacionDocumentalTRD, 1: ?ClasificacionDocumentalTRD, 2: ?ClasificacionDocumentalTRD}
     */
    private function clasificacion(?ClasificacionDocumentalTRD $nodo): array
    {
        $tipoDocumental = $nodo?->tipo === 'TipoDocumento' ? $nodo : null;
        $nodo = $tipoDocumental ? ClasificacionDocumentalTRD::find($tipoDocumental->getAttribute('parent')) : $nodo;
        $subserie = $nodo?->tipo === 'SubSerie' ? $nodo : null;
        $serie = $subserie ? ClasificacionDocumentalTRD::find($subserie->getAttribute('parent')) : ($nodo?->tipo === 'Serie' ? $nodo : null);

        return [$serie, $subserie, $tipoDocumental];
    }

    /** Ids de los expedientes donde están activos los documentos dados. */
    private function expedientesCon(string $clase, iterable $ids): array
    {
        return OfiArchivoExpedienteDocumento::where('documentable_type', $clase)->whereIn('documentable_id', $ids)
            ->where('activo', true)->pluck('expediente_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function actuales(Model $radicado, User $user): array
    {
        $ids = $this->expedientesCon($radicado::class, [$radicado->id]);
        $visibles = OfiArchivoExpediente::visiblesPara($user)->whereIn('id', $ids)
            ->get(['id', 'numero_expediente', 'nombre_expediente', 'estado']);

        // Los que no puede ver se cuentan sin revelarlos, para no sugerir duplicar el radicado
        return ['visibles' => $visibles, 'otros' => count($ids) - $visibles->count()];
    }

    private function sugeridos(?ClasificacionDocumentalTRD $serie, ?ClasificacionDocumentalTRD $subserie, array $deLaPeticion, ?int $terceroId, Model $radicado, User $user): array
    {
        $query = OfiArchivoExpediente::visiblesPara($user)
            ->where('estado', 'abierto')
            ->where(function ($q) use ($serie, $subserie, $deLaPeticion) {
                $q->whereIn('id', $deLaPeticion);
                if ($serie) {
                    $q->orWhere(fn ($c) => $c->where('serie_trd_id', $serie->id)
                        ->when($subserie, fn ($s) => $s->where('subserie_trd_id', $subserie->id)));
                }
            })
            ->whereNotIn('id', $this->expedientesCon($radicado::class, [$radicado->id]));

        $delTercero = $terceroId ? $this->expedientesDelTercero($terceroId) : [];

        return $query->latest('fecha_apertura')->latest('id')->limit(200)
            ->get(['id', 'numero_expediente', 'nombre_expediente', 'dependencia_id', 'fecha_apertura'])
            ->map(fn ($e) => $e->setAttribute('contiene_peticion', in_array($e->id, $deLaPeticion, true))
                ->setAttribute('mismo_tercero', in_array($e->id, $delTercero, true)))
            ->sortBy(fn ($e) => [! $e->contiene_peticion, ! $e->mismo_tercero])
            ->take(self::LIMITE)->values()->all();
    }

    /** Expedientes vinculados al tercero o que ya contienen otro de sus radicados recibidos. */
    private function expedientesDelTercero(int $terceroId): array
    {
        $porDocumento = $this->expedientesCon(VentanillaRadicaReci::class, VentanillaRadicaReci::where('tercero_id', $terceroId)->pluck('id'));
        $vinculados = Schema::hasTable('ofi_archivo_expediente_terceros')
            ? OfiArchivoExpedienteTercero::where('tercero_id', $terceroId)->pluck('expediente_id')->map(fn ($id) => (int) $id)->all()
            : [];

        return array_values(array_unique(array_merge($porDocumento, $vinculados)));
    }

    private function apertura(?ClasificacionDocumentalTRD $serie, ?ClasificacionDocumentalTRD $subserie, Model $radicado, User $user): array
    {
        if (! $serie) {
            return ['permitida' => false, 'motivo' => 'El radicado no tiene clasificación TRD; clasifíquelo para abrir su expediente.'];
        }
        if (! OfiArchivoExpediente::puedeUsarDependencia($user, (int) $serie->dependencia_id)) {
            return ['permitida' => false, 'motivo' => 'La serie del radicado es de otra dependencia; solo esa dependencia puede abrir su expediente.'];
        }

        return [
            'permitida' => true,
            'dependencia_id' => (int) $serie->dependencia_id,
            'serie_trd_id' => $serie->id,
            'subserie_trd_id' => $subserie?->id,
            'nombre_expediente' => mb_substr((string) $radicado->asunto, 0, 300),
        ];
    }
}
