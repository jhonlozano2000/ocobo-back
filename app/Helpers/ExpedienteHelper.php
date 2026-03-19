<?php

namespace App\Helpers;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use Illuminate\Support\Facades\DB;

class ExpedienteHelper
{
    /**
     * Genera un número único de expediente basado en la normativa AGN.
     * Formato: YYYY-DEP-SER-CONSECUTIVO
     *
     * @param int $dependenciaId
     * @param int $serieTrdId
     * @return string
     */
    public static function generarNumeroExpediente(int $dependenciaId, int $serieTrdId): string
    {
        $year = date('Y');

        // 1. Obtener códigos de dependencia y serie
        $dependencia = CalidadOrganigrama::find($dependenciaId);
        $serie = ClasificacionDocumentalTRD::find($serieTrdId);

        $codDep = $dependencia ? ($dependencia->cod_organico ?? $dependencia->id) : '000';
        $codSer = $serie ? ($serie->cod ?? $serie->id) : '000';

        // 2. Obtener el último consecutivo para esta combinación en el año actual
        // Usamos lockForUpdate para evitar condiciones de carrera en aperturas simultáneas (ISO 27001)
        $ultimoConsecutivo = OfiArchivoExpediente::where('dependencia_id', $dependenciaId)
            ->where('serie_trd_id', $serieTrdId)
            ->whereYear('fecha_apertura', $year)
            ->lockForUpdate()
            ->count();

        $nuevoConsecutivo = str_pad($ultimoConsecutivo + 1, 4, '0', STR_PAD_LEFT);

        // 3. Retornar el código formateado
        return "{$year}-{$codDep}-{$codSer}-{$nuevoConsecutivo}";
    }
}
