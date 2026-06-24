<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempArchivoVersion;
use Illuminate\Support\Facades\Storage;

class MiBandejaTempVersionController extends Controller
{
    use ApiResponseTrait;

    private const DISK = 'mi_bandeja_temp';

    public function index($grupoId)
    {
        try {
            $grupo = MiBandejaTemp::find($grupoId);

            if (!$grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $versiones = $grupo->versiones()->with('subidoPor')->orderBy('id', 'desc')->get();

            return $this->successResponse($versiones, 'Versiones del grupo');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener versiones', $e->getMessage(), 500);
        }
    }

    public function descargarVersion($grupoId, $versionId)
    {
        try {
            $version = MiBandejaTempArchivoVersion::where('grupo_id', $grupoId)->find($versionId);

            if (!$version) {
                return $this->errorResponse('Versión no encontrada', null, 404);
            }

            if (!Storage::disk(self::DISK)->exists($version->ruta_completa)) {
                return $this->errorResponse('Archivo no encontrado en disco', null, 404);
            }

            return Storage::disk(self::DISK)->download($version->ruta_completa, $version->nombre_original);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al descargar versión', $e->getMessage(), 500);
        }
    }
}
