<?php

namespace App\Services\ClasificacionDocumental;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRDVersion;
use App\Models\Calidad\CalidadOrganigrama;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TRDService
{
    /**
     * Obtiene TRD con filtros.
     */
    public function getAll(array $filters = []): LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
    {
        $query = ClasificacionDocumentalTRD::whereIn('tipo', ['Serie', 'SubSerie'])
            ->whereNull('parent')
            ->with(['children', 'dependencia']);

        if (!empty($filters['dependencia_id'])) {
            $query->where('dependencia_id', $filters['dependencia_id']);
        }

        if (!empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }

        $query->orderBy('cod', 'asc');

        return $query->get();
    }

    /**
     * Obtiene estadísticas por dependencia.
     */
    public function getStatsByDependencia(int $id): array
    {
        $stats = ClasificacionDocumentalTRD::where('dependencia_id', $id)
            ->selectRaw('
                COUNT(*) as total_elementos,
                SUM(CASE WHEN tipo = "Serie" THEN 1 ELSE 0 END) as series,
                SUM(CASE WHEN tipo = "SubSerie" THEN 1 ELSE 0 END) as subseries,
                SUM(CASE WHEN tipo = "TipoDocumento" THEN 1 ELSE 0 END) as tipos_documento
            ')
            ->first();

        return [
            'total_elementos' => $stats->total_elementos,
            'series' => $stats->series,
            'subseries' => $stats->subseries,
            'tipos_documento' => $stats->tipos_documento,
        ];
    }

    /**
     * Obtiene estadísticas totales.
     */
    public function getTotalStats(): array
    {
        return [
            'total_elementos' => ClasificacionDocumentalTRD::count(),
            'total_series' => ClasificacionDocumentalTRD::where('tipo', 'Serie')->count(),
            'total_subseries' => ClasificacionDocumentalTRD::where('tipo', 'SubSerie')->count(),
            'total_tipos_documento' => ClasificacionDocumentalTRD::where('tipo', 'TipoDocumento')->count(),
            'total_dependencias' => ClasificacionDocumentalTRD::distinct('dependencia_id')->count(),
        ];
    }

    /**
     * Crea un elemento TRD.
     */
    public function create(array $data): ClasificacionDocumentalTRD
    {
        $data['user_register'] = auth()->id();
        return ClasificacionDocumentalTRD::create($data);
    }

    /**
     * Actualiza un elemento TRD.
     */
    public function update(int $id, array $data): ?ClasificacionDocumentalTRD
    {
        $trd = ClasificacionDocumentalTRD::find($id);
        
        if (!$trd) {
            return null;
        }

        $trd->update($data);
        return $trd;
    }

    /**
     * Elimina un elemento TRD si no tiene hijos.
     */
    public function delete(int $id): bool
    {
        $trd = ClasificacionDocumentalTRD::find($id);
        
        if (!$trd || $trd->children()->count() > 0) {
            return false;
        }

        return $trd->delete();
    }

    /**
     * Verifica si tiene versión pendiente.
     */
    public function hasPendingVersion(int $dependenciaId): bool
    {
        return ClasificacionDocumentalTRDVersion::where('dependencia_id', $dependenciaId)
            ->where('estado_version', 'TEMP')
            ->exists();
    }

    /**
     * Crea nueva versión temporal.
     */
    public function createVersion(int $dependenciaId): ClasificacionDocumentalTRDVersion
    {
        $lastVersion = ClasificacionDocumentalTRDVersion::where('dependencia_id', $dependenciaId)
            ->max('version');

        return ClasificacionDocumentalTRDVersion::create([
            'dependencia_id' => $dependenciaId,
            'version' => ($lastVersion ?? 0) + 1,
            'estado_version' => 'TEMP',
            'user_register' => auth()->id()
        ]);
    }

    /**
     * Importa datos TRD desde Excel (método completo para el controlador).
     */
    public function importFromExcel(array $requestData, $archivoFile): array
    {
        // Este método debería ser llamado desde el controlador con la lógica de importación completa
        // Por ahora retornamos los datos procesables
        return [
            'status' => 'ready_to_import',
            'message' => 'Archivo recibido, listo para procesar'
        ];
    }

    /**
     * Importa datos TRD desde Excel (versión interna).
     */
    private function importFromExcelInternal(string $filePath, int $dependenciaId, int $versionId): array
    {
        $idSerie = null;
        $idSubSerie = null;
        $inserted = 0;
        $errors = [];

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        foreach ($data as $index => $row) {
            if ($index < 6) continue;

            $colA = trim($row[0] ?? '');
            $colB = trim($row[1] ?? '');
            $colC = trim($row[2] ?? '');
            $nombre = trim($row[3] ?? '');

            if (empty($nombre)) continue;

            $hasA = !empty($colA);
            $hasB = !empty($colB);
            $hasC = !empty($colC);

            $tipo = null;
            $codigo = null;
            $parent = null;

            if ($hasA && $hasB && !$hasC) {
                $tipo = 'Serie';
                $codigo = $colB;
                $parent = null;
                $idSerie = null;
                $idSubSerie = null;
            } elseif ($hasA && $hasB && $hasC) {
                $tipo = 'SubSerie';
                $codigo = $colC;
                if ($idSerie === null) {
                    $errors[] = "Fila " . ($index + 1) . ": SubSerie sin Serie padre";
                    continue;
                }
                $parent = $idSerie;
                $idSubSerie = null;
            } elseif (!$hasA && !$hasB && !$hasC) {
                $tipo = 'TipoDocumento';
                $codigo = null;
                $parent = $idSubSerie ?? $idSerie;
                if ($parent === null) {
                    $errors[] = "Fila " . ($index + 1) . ": TipoDocumento sin padre";
                    continue;
                }
            } else {
                continue;
            }

            try {
                $elemento = ClasificacionDocumentalTRD::create([
                    'tipo' => $tipo,
                    'cod' => $codigo,
                    'nom' => $nombre,
                    'parent' => $parent,
                    'dependencia_id' => $dependenciaId,
                    'a_g' => $tipo === 'SubSerie' ? trim(mb_substr($row[4] ?? '', 0, 5)) : null,
                    'a_c' => $tipo === 'SubSerie' ? trim(mb_substr($row[5] ?? '', 0, 5)) : null,
                    'ct' => in_array(strtolower(trim($row[6] ?? '')), ['si', 'x']),
                    'e' => in_array(strtolower(trim($row[7] ?? '')), ['si', 'x']),
                    'm_d' => in_array(strtolower(trim($row[8] ?? '')), ['si', 'x']),
                    's' => in_array(strtolower(trim($row[9] ?? '')), ['si', 'x']),
                    'procedimiento' => $row[13] ?? null,
                    'estado' => true,
                    'user_register' => auth()->id(),
                    'version_id' => $versionId
                ]);

                $inserted++;

                if ($tipo === 'Serie') {
                    $idSerie = $elemento->id;
                } elseif ($tipo === 'SubSerie') {
                    $idSubSerie = $elemento->id;
                }
            } catch (\Exception $e) {
                $errors[] = "Fila " . ($index + 1) . ": Error al insertar - {$e->getMessage()}";
            }
        }

        return [
            'inserted' => $inserted,
            'errors' => $errors
        ];
    }
}