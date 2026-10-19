<?php

namespace App\Services\OfiArchivo;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * B07 (diseño de expedientes): el catálogo de tipos documentales de un expediente son los nodos
 * TipoDocumento de su subserie en la TRD (o de su serie, si no tiene subseries). Un folio solo
 * se tipifica con uno de ellos.
 */
class TiposDocumentalesExpediente
{
    private static ?bool $disponible = null;

    /** La columna llega con la migración 2026_10_19_120000. */
    public static function disponible(): bool
    {
        return self::$disponible ??= Schema::hasColumn('ofi_archivo_expedientes_documentos', 'tipo_documental_id');
    }

    public function catalogo(OfiArchivoExpediente $expediente): Collection
    {
        return ClasificacionDocumentalTRD::where('parent', $expediente->subserie_trd_id ?: $expediente->serie_trd_id)
            ->where('tipo', 'TipoDocumento')
            ->orderBy('cod')
            ->get(['id', 'cod', 'nom']);
    }

    /**
     * Busca el tipo por id o, si no llega, por nombre (los clientes anteriores a B07 envían el
     * nombre). Devuelve el nodo o el mensaje de por qué no sirve.
     */
    public function resolver(OfiArchivoExpediente $expediente, ?int $id, ?string $nombre = null): ClasificacionDocumentalTRD|string
    {
        $catalogo = $this->catalogo($expediente);

        $tipo = $id
            ? $catalogo->firstWhere('id', $id)
            : $catalogo->first(fn ($t) => mb_strtolower(trim($t->nom)) === mb_strtolower(trim((string) $nombre)));

        if ($tipo) {
            return $tipo;
        }

        $clasificacion = $expediente->subserie_trd_id ? 'la subserie' : 'la serie';

        return $catalogo->isEmpty()
            ? "La TRD no define tipos documentales para {$clasificacion} del expediente."
            : 'El tipo documental '.($id ? "#{$id}" : "\"{$nombre}\"")." no pertenece a {$clasificacion} del expediente.";
    }
}
