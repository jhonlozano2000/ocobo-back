<?php

namespace App\Services\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoUbicacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * B20 (diseño de expedientes §5): catálogo topográfico y ubicación de expedientes y tomos en él.
 *
 * Los expedientes y tomos se ubican en una caja con un número de carpeta; los textos
 * `ubicacion_fisica` (ruta hasta el entrepaño) y `caja` se derivan de la caja para que el FUID, la
 * hoja de control y el cierre sigan leyéndolos. Con el interruptor de la entidad apagado la
 * ubicación también puede escribirse a mano, como antes.
 */
class UbicacionTopograficaService
{
    /** Campos de ubicación del expediente y del tomo. */
    public const CAMPOS = ['ubicacion_fisica', 'ubicacion_id', 'caja', 'carpeta'];

    private const ARCHIVOS = ['gestion' => 'de gestión', 'central' => 'central', 'historico' => 'histórico'];

    private static ?bool $disponible = null;

    /** La tabla llega con la migración 2026_10_19_220000. */
    public static function disponible(): bool
    {
        return self::$disponible ??= Schema::hasTable('ofi_archivo_ubicaciones');
    }

    /**
     * CAMPOS que existen en la base: sin la migración aún no hay `ubicacion_id`.
     *
     * @return list<string>
     */
    public static function campos(): array
    {
        return self::disponible() ? self::CAMPOS : ['ubicacion_fisica', 'caja', 'carpeta'];
    }

    /**
     * Catálogo plano ordenado por ruta. `vigente` exige que el nodo y sus ancestros estén activos;
     * las cajas traen cuántas carpetas ocupan.
     *
     * @param  array{archivo?: string, tipo?: string, vigente?: bool}  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    public function catalogo(array $filtros = []): Collection
    {
        $nodos = OfiArchivoUbicacion::orderBy('ruta')->get()->keyBy('id');
        $ocupadas = $this->usos()->whereNotNull('carpeta')
            ->selectRaw('ubicacion_id, count(distinct carpeta) as n')->groupBy('ubicacion_id')->pluck('n', 'ubicacion_id');

        return $nodos
            ->map(fn (OfiArchivoUbicacion $nodo) => $nodo->only(['id', 'parent_id', 'tipo', 'archivo', 'codigo', 'nombre', 'capacidad', 'activo', 'ruta']) + [
                'vigente' => $this->vigente($nodo, $nodos),
                'ocupadas' => $nodo->tipo === 'caja' ? (int) ($ocupadas[$nodo->id] ?? 0) : null,
            ])
            ->filter(fn ($n) => (! isset($filtros['archivo']) || $n['archivo'] === $filtros['archivo'])
                && (! isset($filtros['tipo']) || $n['tipo'] === $filtros['tipo'])
                && (! isset($filtros['vigente']) || $n['vigente'] === (bool) $filtros['vigente']))
            ->values();
    }

    /**
     * @param  array{parent_id?: ?int, archivo?: ?string, codigo: string, nombre?: ?string, capacidad?: ?int}  $datos
     */
    public function crear(array $datos): OfiArchivoUbicacion
    {
        $padre = filled($datos['parent_id'] ?? null) ? OfiArchivoUbicacion::lockForUpdate()->findOrFail($datos['parent_id']) : null;
        if ($padre && ! $padre->nivelHijo()) {
            throw ValidationException::withMessages(['parent_id' => 'Una caja no admite ubicaciones dentro; la carpeta se registra en el expediente.']);
        }
        if (! $padre && ! isset(self::ARCHIVOS[$datos['archivo'] ?? ''])) {
            throw ValidationException::withMessages(['archivo' => 'Indique a qué archivo pertenece el depósito.']);
        }

        $tipo = $padre?->nivelHijo() ?? 'deposito';
        $codigo = trim($datos['codigo']);
        $this->exigirCodigoLibre($padre?->id, $codigo);
        $capacidad = $datos['capacidad'] ?? null;
        if ($tipo !== 'caja' && filled($capacidad)) {
            throw ValidationException::withMessages(['capacidad' => 'Solo las cajas tienen capacidad.']);
        }

        return OfiArchivoUbicacion::create([
            'parent_id' => $padre?->id,
            'tipo' => $tipo,
            'archivo' => $padre?->archivo ?? $datos['archivo'],
            'codigo' => $codigo,
            'nombre' => filled($datos['nombre'] ?? null) ? trim($datos['nombre']) : null,
            'capacidad' => filled($capacidad) ? (int) $capacidad : null,
            'ruta' => self::ruta($padre, $tipo, $codigo),
        ]);
    }

    /**
     * El archivo y el nivel no cambian. Al cambiar el código se recalculan las rutas del subárbol
     * y los textos de los expedientes y tomos ubicados en él.
     *
     * @param  array{codigo?: string, nombre?: ?string, capacidad?: ?int, activo?: bool}  $datos
     */
    public function actualizar(OfiArchivoUbicacion $nodo, array $datos): OfiArchivoUbicacion
    {
        if (array_key_exists('capacidad', $datos)) {
            $capacidad = filled($datos['capacidad']) ? (int) $datos['capacidad'] : null;
            if ($nodo->tipo !== 'caja' && $capacidad !== null) {
                throw ValidationException::withMessages(['capacidad' => 'Solo las cajas tienen capacidad.']);
            }
            $ocupadas = $this->carpetas($nodo->id)->count();
            if ($capacidad !== null && $capacidad < $ocupadas) {
                throw ValidationException::withMessages(['capacidad' => "La caja ya tiene {$ocupadas} carpetas; la capacidad no puede ser menor."]);
            }
            $nodo->capacidad = $capacidad;
        }
        if (array_key_exists('nombre', $datos)) {
            $nodo->nombre = filled($datos['nombre']) ? trim($datos['nombre']) : null;
        }
        if (array_key_exists('activo', $datos)) {
            $nodo->activo = (bool) $datos['activo'];
        }
        $recalcular = false;
        if (filled($datos['codigo'] ?? null) && trim($datos['codigo']) !== $nodo->codigo) {
            $this->exigirCodigoLibre($nodo->parent_id, trim($datos['codigo']), $nodo->id);
            $nodo->codigo = trim($datos['codigo']);
            $recalcular = true;
        }
        $nodo->save();

        if ($recalcular) {
            $this->recalcularRutas($nodo);
        }

        return $nodo;
    }

    /** Solo se elimina un nodo sin hijos y sin expedientes; si no, se desactiva. */
    public function eliminar(OfiArchivoUbicacion $nodo): void
    {
        if ($nodo->hijos()->exists()) {
            throw ValidationException::withMessages(['ubicacion' => 'Tiene ubicaciones dentro; elimínelas primero o desactívela.']);
        }
        if ($this->usos()->where('ubicacion_id', $nodo->id)->exists()) {
            throw ValidationException::withMessages(['ubicacion' => 'Hay expedientes o tomos en esta caja; desactívela en lugar de eliminarla.']);
        }

        $nodo->delete();
    }

    /**
     * Campos de ubicación que cambian al aplicar $datos sobre la ubicación $actual del expediente
     * o de uno de sus tomos.
     *
     * @param  array<string, mixed>  $datos  claves de CAMPOS que llegaron en la petición
     * @param  array<string, mixed>  $actual  ubicación vigente del registro (vacío si es nuevo)
     * @param  int|null  $tomoId  null si se ubica el expediente; 0 si es un tomo nuevo
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function resolver(array $datos, array $actual, OfiArchivoExpediente $expediente, ?int $tomoId = null): array
    {
        $datos = array_intersect_key($datos, array_flip(self::CAMPOS));
        if (! self::disponible()) {
            return array_diff_key($datos, ['ubicacion_id' => true]);
        }

        $catalogo = ConfiguracionArchivo::ubicacionConCatalogo();
        $textos = array_intersect_key($datos, ['ubicacion_fisica' => true, 'caja' => true]);
        $cambiaTexto = collect($textos)->contains(fn ($valor, $campo) => (string) $valor !== (string) ($actual[$campo] ?? ''));

        if (array_key_exists('ubicacion_id', $datos)) {
            $cajaId = filled($datos['ubicacion_id']) ? (int) $datos['ubicacion_id'] : null;
            $cambios = ['ubicacion_id' => $cajaId] + match (true) {
                $cajaId !== null => [],
                $catalogo => ['ubicacion_fisica' => null, 'caja' => null],
                default => $textos,
            };
        } elseif ($cambiaTexto) {
            if ($catalogo) {
                throw ValidationException::withMessages(['ubicacion_id' => 'Elija la caja del catálogo de ubicaciones; la ubicación no se escribe a mano.']);
            }
            // La ubicación escrita a mano deja de corresponder a la caja del catálogo
            $cambios = $textos + ['ubicacion_id' => null];
        } else {
            $cambios = [];
        }
        if (array_key_exists('carpeta', $datos)) {
            $cambios['carpeta'] = filled($datos['carpeta']) ? trim((string) $datos['carpeta']) : null;
        }

        $cajaId = $cambios['ubicacion_id'] ?? (array_key_exists('ubicacion_id', $cambios) ? null : ($actual['ubicacion_id'] ?? null));
        $cambiaCaja = (int) $cajaId !== (int) ($actual['ubicacion_id'] ?? 0);
        $carpeta = array_key_exists('carpeta', $cambios) ? $cambios['carpeta'] : ($actual['carpeta'] ?? null);
        if ($cajaId && ($cambiaCaja || (string) $carpeta !== (string) ($actual['carpeta'] ?? ''))) {
            $caja = OfiArchivoUbicacion::lockForUpdate()->find($cajaId);
            $this->validarCaja($caja, $carpeta, $expediente, $tomoId, $cambiaCaja);
            $cambios['ubicacion_fisica'] = $caja->parent?->ruta;
            $cambios['caja'] = $caja->codigo;
        }

        return $cambios;
    }

    /**
     * Expedientes físicos o híbridos sin caja del catálogo, o con el expediente o algún tomo en una
     * caja de un archivo distinto al de su fase (p. ej. recibidos en el central y aún en la caja de
     * gestión).
     */
    public function pendientes(Builder $expedientes): Builder
    {
        $fuera = fn ($q) => $q->whereColumn('ofi_archivo_ubicaciones.archivo', '<>', 'ofi_archivo_expedientes.fase');

        return $expedientes->whereIn('soporte', ['fisico', 'hibrido'])->where('estado', '<>', 'eliminado')
            ->where(fn ($q) => $q->whereNull('ubicacion_id')
                ->orWhereHas('ubicacion', $fuera)
                ->orWhereHas('tomos', fn ($t) => $t->where(fn ($t) => $t->whereNull('ofi_archivo_expediente_tomos.ubicacion_id')
                    ->orWhereHas('ubicacion', $fuera))));
    }

    /**
     * Motivo por el que la ubicación del expediente no está en el catálogo o no corresponde a su
     * fase; null si está bien ubicado. Lo exige la transferencia con el catálogo activo.
     *
     * @param  Collection<int, array<string, mixed>>  $tomos  los de HojaControlService::tomos()
     */
    public function motivoSinUbicar(OfiArchivoExpediente $expediente, Collection $tomos): ?string
    {
        if (! $expediente->ubicacion_id) {
            return 'Le falta la caja del catálogo de ubicaciones';
        }
        $sinCaja = $tomos->filter(fn ($t) => blank($t['ubicacion_id'] ?? null))->pluck('numero');
        if ($sinCaja->isNotEmpty()) {
            return 'Le falta la caja del catálogo de ubicaciones del tomo '.$sinCaja->implode(', ');
        }

        $cajas = $tomos->pluck('ubicacion_id')->push($expediente->ubicacion_id)->unique();
        if (OfiArchivoUbicacion::whereIn('id', $cajas)->where('archivo', '<>', $expediente->fase)->exists()) {
            return 'Está en una caja fuera del archivo '.self::ARCHIVOS[$expediente->fase].'; reubíquelo primero';
        }

        return null;
    }

    private function validarCaja(?OfiArchivoUbicacion $caja, ?string $carpeta, OfiArchivoExpediente $expediente, ?int $tomoId, bool $cambiaCaja): void
    {
        if (! $caja || $caja->tipo !== 'caja') {
            throw ValidationException::withMessages(['ubicacion_id' => 'Elija una caja del catálogo de ubicaciones.']);
        }
        if ($cambiaCaja) {
            if (! $this->vigente($caja)) {
                throw ValidationException::withMessages(['ubicacion_id' => "La {$caja->ruta} está inactiva."]);
            }
            $fase = $expediente->fase ?? 'gestion';
            if ($caja->archivo !== $fase) {
                throw ValidationException::withMessages(['ubicacion_id' => 'La caja es del archivo '.self::ARCHIVOS[$caja->archivo]
                    .' y el expediente está en el archivo '.self::ARCHIVOS[$fase].'.']);
            }
        }
        if (blank($carpeta)) {
            throw ValidationException::withMessages(['carpeta' => 'Indique el número de carpeta dentro de la caja.']);
        }

        $ocupante = $this->usos()->where('ubicacion_id', $caja->id)->where('carpeta', $carpeta)
            ->when($expediente->id, fn ($q) => $q->where('expediente_id', '<>', $expediente->id))
            ->value('expediente_id');
        if ($ocupante) {
            $numero = OfiArchivoExpediente::whereKey($ocupante)->value('numero_expediente');
            throw ValidationException::withMessages(['carpeta' => "La carpeta {$carpeta} de la caja {$caja->codigo} ya es del expediente {$numero}."]);
        }

        if ($caja->capacidad !== null) {
            $carpetas = $this->carpetas($caja->id, $expediente->id, $tomoId);
            if (! $carpetas->contains($carpeta) && $carpetas->count() >= $caja->capacidad) {
                throw ValidationException::withMessages(['ubicacion_id' => "La caja {$caja->codigo} está llena ({$carpetas->count()} de {$caja->capacidad} carpetas)."]);
            }
        }
    }

    /**
     * Carpetas distintas ocupadas en la caja, sin contar el registro que se está ubicando.
     *
     * @return Collection<int, string>
     */
    private function carpetas(int $cajaId, ?int $expedienteId = null, ?int $tomoId = null): Collection
    {
        return $this->usos()->where('ubicacion_id', $cajaId)->whereNotNull('carpeta')
            ->when($expedienteId && $tomoId === null, fn ($q) => $q->whereNot(fn ($q) => $q->where('expediente_id', $expedienteId)->whereNull('tomo_id')))
            ->when($tomoId, fn ($q) => $q->where(fn ($q) => $q->whereNull('tomo_id')->orWhere('tomo_id', '<>', $tomoId)))
            ->distinct()->pluck('carpeta')->map(fn ($c) => (string) $c);
    }

    /** Expedientes y tomos ubicados en cajas, sin los expedientes eliminados. */
    private function usos(): QueryBuilder
    {
        $expedientes = DB::table('ofi_archivo_expedientes as e')
            ->selectRaw('e.ubicacion_id, e.carpeta, e.id as expediente_id, null as tomo_id')
            ->whereNotNull('e.ubicacion_id')->whereNull('e.deleted_at')->where('e.estado', '<>', 'eliminado');
        $tomos = DB::table('ofi_archivo_expediente_tomos as t')->join('ofi_archivo_expedientes as e', 'e.id', '=', 't.expediente_id')
            ->selectRaw('t.ubicacion_id, t.carpeta, t.expediente_id, t.id as tomo_id')
            ->whereNotNull('t.ubicacion_id')->whereNull('e.deleted_at')->where('e.estado', '<>', 'eliminado');

        return DB::query()->fromSub($expedientes->unionAll($tomos), 'usos');
    }

    /**
     * @param  Collection<int, OfiArchivoUbicacion>|null  $nodos  catálogo por id, para no consultar cada ancestro
     */
    private function vigente(OfiArchivoUbicacion $nodo, ?Collection $nodos = null): bool
    {
        for ($actual = $nodo; $actual; $actual = $actual->parent_id ? ($nodos ? $nodos->get($actual->parent_id) : $actual->parent) : null) {
            if (! $actual->activo) {
                return false;
            }
        }

        return true;
    }

    private function exigirCodigoLibre(?int $padreId, string $codigo, ?int $excepto = null): void
    {
        $existe = OfiArchivoUbicacion::where('parent_id', $padreId)->where('codigo', $codigo)
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto))->exists();
        if ($existe) {
            throw ValidationException::withMessages(['codigo' => "Ya existe el código {$codigo} en ese nivel."]);
        }
    }

    private static function ruta(?OfiArchivoUbicacion $padre, string $tipo, string $codigo): string
    {
        return ($padre ? $padre->ruta.' / ' : '').OfiArchivoUbicacion::ETIQUETAS[$tipo].' '.$codigo;
    }

    /**
     * Recalcula la ruta del subárbol y los textos derivados de sus cajas. Renombrar el catálogo no
     * mueve los expedientes; por eso no se registra en su historial.
     */
    private function recalcularRutas(OfiArchivoUbicacion $nodo): void
    {
        $nodo->update(['ruta' => self::ruta($nodo->parent, $nodo->tipo, $nodo->codigo)]);

        if ($nodo->tipo === 'caja') {
            $textos = ['ubicacion_fisica' => $nodo->parent?->ruta, 'caja' => $nodo->codigo];
            DB::table('ofi_archivo_expedientes')->where('ubicacion_id', $nodo->id)->update($textos);
            DB::table('ofi_archivo_expediente_tomos')->where('ubicacion_id', $nodo->id)->update($textos);

            return;
        }

        foreach ($nodo->hijos as $hijo) {
            $hijo->setRelation('parent', $nodo);
            $this->recalcularRutas($hijo);
        }
    }
}
