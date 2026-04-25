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
        \Log::info('TRD Import started', ['requestData' => $requestData, 'hasFile' => !empty($archivoFile)]);
        
        // La dependencia se obtiene de la celda B4 del archivo Excel, no del request
        $dependenciaId = null; // Se obtendrás de B4 en importFromExcelInternal
        $versionId = $requestData['version_id'] ?? null;

        // Guardar archivo temporal
        $fileName = $archivoFile->getClientOriginalName();
        $tempPath = storage_path('app/temp/' . $fileName);
        
        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }
        
        $archivoFile->move(storage_path('app/temp'), $fileName);

        \Log::info('TRD Import file saved', ['tempPath' => $tempPath, 'exists' => file_exists($tempPath)]);

        // Procesar importacion (dependencia se obtiene de B4, version es opcional)
        $result = $this->importFromExcelInternal($tempPath, $dependenciaId, $versionId);

        \Log::info('TRD Import result', $result);

        // Limpiar archivo temporal
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        return $result;
    }

    /**
     * Importa datos TRD desde Excel (versión interna).
     */
    private function importFromExcelInternal(string $filePath, ?int $dependenciaId, ?int $versionId = null): array
    {
        $idSerie = null;
        $idSubSerie = null;
        $inserted = 0;
        $errors = [];

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        // Leer celda B4 para obtener el código de la dependencia
        $codigoDependencia = trim($sheet->getCell('B4')->getValue() ?? '');
        \Log::info('TRD Import - Codigo dependencia from B4', ['codigo' => $codigoDependencia]);

        // Buscar la dependencia por código y tipo
        $dependencia = \App\Models\Calidad\CalidadOrganigrama::where('cod_organico', $codigoDependencia)
            ->where('tipo', 'Dependencia')
            ->first();
        
        if (!$dependencia) {
            return [
                'inserted' => 0,
                'errors' => ['No se encontró la dependencia con código: ' . $codigoDependencia]
            ];
        }

        $dependenciaId = $dependencia->id;
        \Log::info('TRD Import - Dependencia encontrada', ['id' => $dependenciaId, 'nombre' => $dependencia->nom_organico]);

        // Obtener o crear versión para esta dependencia
        $ultimaVersion = \App\Models\ClasificacionDocumental\ClasificacionDocumentalTRDVersion::where('dependencia_id', $dependenciaId)->max('version') ?? 0;
        $nuevaVersion = $ultimaVersion + 1;

        $version = \App\Models\ClasificacionDocumental\ClasificacionDocumentalTRDVersion::create([
            'dependencia_id' => $dependenciaId,
            'version' => $nuevaVersion,
            'estado_version' => 'TEMP',
            'user_register' => auth()->id(),
            'observaciones' => 'Importación masiva desde Excel'
        ]);

        $versionId = $version->id;
        \Log::info('TRD Import - Nueva versión creada', ['version_id' => $versionId, 'version' => $nuevaVersion]);

        foreach ($data as $index => $row) {
            if ($index < 6) continue;

            $colA = trim($row[0] ?? '');
            $colB = trim($row[1] ?? '');
            $colC = trim($row[2] ?? '');
            $diasVencimiento = trim($row[3] ?? '');
            $nombre = trim($row[4] ?? '');

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
                $data = [
                    'tipo' => $tipo,
                    'cod' => $codigo,
                    'nom' => $nombre,
                    'dias_vencimiento' => !empty($diasVencimiento) && is_numeric($diasVencimiento) ? (int) $diasVencimiento : null,
                    'parent' => $parent,
                    'dependencia_id' => $dependenciaId,
                    'a_g' => $tipo === 'SubSerie' ? trim(mb_substr($row[7] ?? '', 0, 5)) : null, // Columna H: Archivo Gestión
                    'a_c' => $tipo === 'SubSerie' ? trim(mb_substr($row[8] ?? '', 0, 5)) : null, // Columna I: Archivo Central
                    'ct' => in_array(strtolower(trim($row[9] ?? '')), ['si', 'x']), // Columna J: CT
                    'e' => in_array(strtolower(trim($row[10] ?? '')), ['si', 'x']), // Columna K: E
                    'm_d' => in_array(strtolower(trim($row[11] ?? '')), ['si', 'x']), // Columna L: M/D
                    's' => in_array(strtolower(trim($row[12] ?? '')), ['si', 'x']), // Columna M: S
                    'papel' => in_array(strtolower(trim($row[13] ?? '')), ['si', 'x']), // Columna N: Papel
                    'electronico' => in_array(strtolower(trim($row[14] ?? '')), ['si', 'x']), // Columna O: Electronico
                    'mixto' => in_array(strtolower(trim($row[15] ?? '')), ['si', 'x']), // Columna P: Mixto
                    'procedimiento' => $row[16] ?? null, // Columna Q: PROCEDIMIENTO
                    'estado' => true,
                    'user_register' => auth()->id(),
                ];

                // Parse PDF/A columns (F=PDF/A, G=Nivel)
                $pdfAValue = strtolower(trim($row[5] ?? '')); // Columna F
                $pdfANivel = strtolower(trim($row[6] ?? '')); // Columna G: Nivel PDF/A
                $requierePdfA = in_array($pdfAValue, ['sí', 'si', 'x']);
                if ($requierePdfA || !empty($pdfANivel) && $pdfANivel !== 'null') {
                    $data['requiere_pdf_a'] = true;
                    // Usar nivel de columna G si existe, si no default "1b"
                    if (in_array($pdfANivel, ['1a', '1b', '2a', '2b', '3'])) {
                        $data['pdf_a_nivel'] = $pdfANivel;
                    } else {
                        $data['pdf_a_nivel'] = '1b'; // Default
                    }
                    $data['convierte_a_pdf_a'] = true;
                }

                // Solo agregar version_id si es válido
                if ($versionId && $versionId > 0) {
                    $data['version_id'] = $versionId;
                }

                $elemento = ClasificacionDocumentalTRD::create($data);

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