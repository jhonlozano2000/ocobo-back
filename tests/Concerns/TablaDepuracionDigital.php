<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M08-02: crea la bitácora de depuración como tabla de sesión (sin commit implícito)
 * mientras la BD de pruebas no tenga la migración.
 */
trait TablaDepuracionDigital
{
    protected function asegurarTablaDepuracionDigital(): void
    {
        if (Schema::hasTable('ofi_archivo_depuraciones_digitales')) {
            return;
        }

        DB::statement('CREATE TEMPORARY TABLE ofi_archivo_depuraciones_digitales (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, disco VARCHAR(60), accion VARCHAR(20),
            ruta_original VARCHAR(500), ruta_cuarentena VARCHAR(500) NULL, hash_sha256 VARCHAR(64) NULL,
            tamano_bytes BIGINT UNSIGNED NULL, origen VARCHAR(100), user_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL)');
    }
}
