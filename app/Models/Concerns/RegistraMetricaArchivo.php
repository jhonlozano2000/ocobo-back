<?php

namespace App\Models\Concerns;

use App\Models\Metricas\ArchivoMetrica;
use App\Services\Metricas\RegistroMetricasArchivos;

/**
 * Registra en archivos_metricas cada archivo que se carga o se elimina en el modelo, sin tocar
 * los controladores. Cubre los tres casos de los módulos:
 * - una fila por archivo (adjuntos, Mi Disco, versiones): created / deleted;
 * - el archivo en una columna del registro (digital de un radicado): al cambiar la ruta se
 *   registra la eliminación de la anterior y la carga de la nueva;
 * - borrado lógico por bandera (folios de expediente con activo = false): deja de estar almacenado.
 *
 * Para rastrear un módulo nuevo: usar el trait, implementar datosMetricaArchivo() y agregar el
 * modelo a RegistroMetricasArchivos::MODELOS (para el backfill).
 */
trait RegistraMetricaArchivo
{
    /**
     * Datos del archivo del registro, normalizados.
     *
     * @return array{modulo: string, submodulo: string, ruta: ?string, bytes: int|float|null, extension: ?string, mime: ?string, user_id: ?int, fecha?: mixed}
     */
    abstract public function datosMetricaArchivo(): array;

    /**
     * Si el registro tiene un archivo almacenado que cuenta en las métricas.
     */
    public function archivoAlmacenadoMetrica(): bool
    {
        return filled($this->datosMetricaArchivo()['ruta'] ?? null);
    }

    public static function bootRegistraMetricaArchivo(): void
    {
        static::created(fn ($modelo) => $modelo->registrarMetricaArchivo(null, $modelo));

        static::updated(fn ($modelo) => $modelo->registrarMetricaArchivo($modelo->copiaOriginalMetrica(), $modelo));

        static::deleted(function ($modelo) {
            // forceDelete de un registro que ya estaba en la papelera: su eliminación ya se registró
            if (method_exists($modelo, 'isForceDeleting') && $modelo->isForceDeleting() && $modelo->trashed()) {
                return;
            }
            $modelo->registrarMetricaArchivo($modelo, null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($modelo) => $modelo->registrarMetricaArchivo(null, $modelo));
        }
    }

    /**
     * Compara el archivo antes y después del cambio y registra lo que corresponda.
     */
    protected function registrarMetricaArchivo(?self $antes, ?self $despues): void
    {
        $previo = $antes?->archivoAlmacenadoMetrica() ? $antes->datosMetricaArchivo() : null;
        $actual = $despues?->archivoAlmacenadoMetrica() ? $despues->datosMetricaArchivo() : null;

        if ($previo && $actual && $previo['ruta'] === $actual['ruta']) {
            return;
        }

        $registro = app(RegistroMetricasArchivos::class);
        if ($previo) {
            $registro->registrar($this, ArchivoMetrica::ELIMINADO, $previo);
        }
        if ($actual) {
            $registro->registrar($this, ArchivoMetrica::CARGADO, $actual);
        }
    }

    /**
     * El mismo registro con los valores que tenía antes de esta actualización.
     */
    protected function copiaOriginalMetrica(): static
    {
        $copia = clone $this;
        $copia->setRawAttributes($this->getRawOriginal());

        return $copia;
    }
}
