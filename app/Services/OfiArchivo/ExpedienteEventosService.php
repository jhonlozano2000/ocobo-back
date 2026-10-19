<?php

namespace App\Services\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteEvento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B12 (diseño de expedientes): historial encadenado del expediente.
 *
 * Cada evento guarda la huella del anterior del mismo expediente; su propia huella cubre
 * expediente, secuencia, acción, detalle, usuario, IP y fecha. Alterar o borrar un evento
 * rompe la cadena desde ese punto y verificar() lo detecta (archivo:verificar-cadena).
 */
class ExpedienteEventosService
{
    private const CAMPOS_DERIVADOS = ['updated_at', 'total_folios_elec', 'hash_indice', 'dependencia_cod', 'dependencia_nom'];

    private const ACCION_POR_ESTADO = [
        'abierto' => 'reapertura',
        'cerrado' => 'cierre',
        'transferido' => 'transferencia',
        'eliminado' => 'eliminacion',
    ];

    private static ?bool $disponible = null;

    /** La tabla llega con la migración 2026_10_19_100000; sin ella no hay dónde registrar. */
    public static function disponible(): bool
    {
        return self::$disponible ??= Schema::hasTable('ofi_archivo_expediente_eventos');
    }

    public function registrar(int $expedienteId, string $accion, array $detalle = []): ?OfiArchivoExpedienteEvento
    {
        if (! self::disponible()) {
            return null;
        }

        return DB::transaction(function () use ($expedienteId, $accion, $detalle) {
            // El bloqueo del expediente serializa los eventos: dos no pueden tomar la misma secuencia
            OfiArchivoExpediente::whereKey($expedienteId)->lockForUpdate()->value('id');
            $ultimo = OfiArchivoExpedienteEvento::where('expediente_id', $expedienteId)
                ->orderByDesc('secuencia')
                ->first(['secuencia', 'hash']);

            $evento = new OfiArchivoExpedienteEvento([
                'expediente_id' => $expedienteId,
                'secuencia' => ($ultimo->secuencia ?? 0) + 1,
                'accion' => $accion,
                'detalle' => $detalle ?: null,
                'usuario_id' => Auth::id(),
                'ip' => app()->runningInConsole() ? null : request()->ip(),
                'ocurrido_en' => now(),
                'hash_anterior' => $ultimo?->hash,
            ]);
            $evento->hash = self::huella($evento);
            $evento->save();

            return $evento;
        });
    }

    public function apertura(OfiArchivoExpediente $expediente): void
    {
        $this->registrar($expediente->id, 'apertura', [
            'numero_expediente' => $expediente->numero_expediente,
            'nombre_expediente' => $expediente->nombre_expediente,
            'dependencia_cod' => $expediente->dependencia_cod,
            'serie_trd_id' => $expediente->serie_trd_id,
            'subserie_trd_id' => $expediente->subserie_trd_id,
            'estado' => $expediente->estado,
        ]);
    }

    /**
     * Un cambio de estado se registra con su acción (cierre, transferencia...); el resto de cambios
     * como edición con el valor anterior y el nuevo. Los contadores y la huella del índice no son
     * ediciones del usuario: los cubren los eventos de incorporación, retiro y cierre.
     */
    public function cambio(OfiArchivoExpediente $expediente): void
    {
        $campos = array_diff(array_keys($expediente->getChanges()), self::CAMPOS_DERIVADOS);
        if ($campos === []) {
            return;
        }

        $cambios = [];
        foreach ($campos as $campo) {
            $cambios[$campo] = ['antes' => $expediente->getRawOriginal($campo), 'despues' => $expediente->getAttributes()[$campo] ?? null];
        }

        if (! $expediente->wasChanged('estado') && ! $expediente->wasChanged('fase')) {
            $this->registrar($expediente->id, 'edicion', ['cambios' => $cambios]);

            return;
        }

        // B16: la transferencia primaria cambia la fase y deja el estado en cerrado
        $accion = $expediente->wasChanged('fase') ? 'transferencia' : (self::ACCION_POR_ESTADO[$expediente->estado] ?? 'cambio_estado');
        $this->registrar($expediente->id, $accion, array_filter([
            'cambios' => $cambios,
            'hash_indice' => $expediente->wasChanged('hash_indice') ? $expediente->hash_indice : null,
        ]));
    }

    /**
     * Huella del evento sobre los valores tal como quedan en la BD (detalle JSON y fecha en texto),
     * para que al verificar se recalcule exactamente igual.
     */
    public static function huella(OfiArchivoExpedienteEvento $evento): string
    {
        $a = $evento->getAttributes();

        return hash('sha256', ($a['hash_anterior'] ?? '').'|'.json_encode([
            (int) $a['expediente_id'],
            (int) $a['secuencia'],
            (string) $a['accion'],
            $a['detalle'] ?? null,
            isset($a['usuario_id']) ? (int) $a['usuario_id'] : null,
            $a['ip'] ?? null,
            (string) $a['ocurrido_en'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Recorre la cadena de uno o de todos los expedientes.
     *
     * @return list<array{expediente_id: int, secuencia: int, problema: string}>
     */
    public function verificar(?int $expedienteId = null): array
    {
        $rupturas = [];
        $actual = null;
        $esperada = 1;
        $anterior = null;

        $eventos = OfiArchivoExpedienteEvento::query()
            ->when($expedienteId, fn ($q) => $q->where('expediente_id', $expedienteId))
            ->orderBy('expediente_id')->orderBy('secuencia')
            ->cursor();

        foreach ($eventos as $evento) {
            if ($evento->expediente_id !== $actual) {
                $actual = $evento->expediente_id;
                $esperada = 1;
                $anterior = null;
            }

            $problemas = [];
            if ($evento->secuencia !== $esperada) {
                $problemas[] = "Falta el evento {$esperada}: la secuencia salta a {$evento->secuencia}";
            }
            if ($evento->hash_anterior !== $anterior) {
                $problemas[] = 'El evento no apunta a la huella del anterior';
            }
            if (! hash_equals($evento->hash, self::huella($evento))) {
                $problemas[] = 'El contenido del evento no coincide con su huella';
            }
            foreach ($problemas as $problema) {
                $rupturas[] = ['expediente_id' => $actual, 'secuencia' => $evento->secuencia, 'problema' => $problema];
            }

            $esperada = $evento->secuencia + 1;
            $anterior = $evento->hash;
        }

        return $rupturas;
    }
}
