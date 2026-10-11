<?php

namespace App\Helpers;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\OfiArchivo\OfiArchivoExpediente;

class ExpedienteHelper
{
    /**
     * Genera un número único de expediente basado en la normativa AGN.
     * Formato: YYYY-DEP-SER-CONSECUTIVO. Debe llamarse dentro de una transacción.
     *
     * M08-08: el consecutivo sale del mayor número ya emitido con el mismo prefijo, incluidos
     * los expedientes borrados lógicamente (antes `count()` los omitía y el siguiente número
     * chocaba con el índice único). El año es el de Colombia, no el de UTC (M13-01).
     */
    public static function generarNumeroExpediente(int $dependenciaId, int $serieTrdId): string
    {
        $year = CalendarioHelper::hoy()->format('Y');

        // 1. Obtener códigos de dependencia y serie
        $dependencia = CalidadOrganigrama::find($dependenciaId);
        $serie = ClasificacionDocumentalTRD::find($serieTrdId);

        $codDep = $dependencia ? ($dependencia->cod_organico ?? $dependencia->id) : '000';
        $codSer = $serie ? ($serie->cod ?? $serie->id) : '000';

        $prefijo = "{$year}-{$codDep}-{$codSer}-";

        // 2. Serializar aperturas simultáneas de la dependencia: el bloqueo sobre las filas de
        // expedientes no sirve cuando aún no hay ninguna con este prefijo
        CalidadOrganigrama::whereKey($dependenciaId)->lockForUpdate()->first();

        $ultimoConsecutivo = 0;
        $numeros = OfiArchivoExpediente::withTrashed()
            ->where('numero_expediente', 'like', $prefijo.'%')
            ->pluck('numero_expediente');
        foreach ($numeros as $numero) {
            // LIKE trata '_' y '%' de los códigos como comodines; se confirma el prefijo exacto
            $resto = substr($numero, strlen($prefijo));
            if (str_starts_with($numero, $prefijo) && ctype_digit($resto)) {
                $ultimoConsecutivo = max($ultimoConsecutivo, (int) $resto);
            }
        }

        // 3. Retornar el código formateado
        return $prefijo.str_pad((string) ($ultimoConsecutivo + 1), 4, '0', STR_PAD_LEFT);
    }
}
