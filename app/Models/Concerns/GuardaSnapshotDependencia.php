<?php

namespace App\Models\Concerns;

use App\Models\Calidad\CalidadOrganigrama;
use Illuminate\Support\Facades\Schema;

/**
 * M09-06: copia el código y el nombre de la dependencia (dependencia_cod / dependencia_nom)
 * para que el registro conserve la estructura orgánica vigente cuando se creó o aprobó,
 * aunque el organigrama cambie después.
 */
trait GuardaSnapshotDependencia
{
    /** @var array<string, bool> */
    private static array $tablaTieneSnapshot = [];

    /**
     * Rellena el snapshot desde el organigrama actual.
     */
    protected function capturarSnapshotDependencia(): void
    {
        $tabla = $this->getTable();

        // Si aún no se migró la columna, no se rompe el guardado
        self::$tablaTieneSnapshot[$tabla] ??= Schema::hasColumn($tabla, 'dependencia_nom');
        if (! self::$tablaTieneSnapshot[$tabla]) {
            return;
        }

        $dependencia = $this->dependencia_id ? CalidadOrganigrama::find($this->dependencia_id) : null;

        $this->dependencia_cod = $dependencia?->cod_organico;
        $this->dependencia_nom = $dependencia?->nom_organico;
    }
}
