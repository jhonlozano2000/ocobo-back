<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\ConfigVarias;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ConfigVariasService
{
    /**
     * Obtiene todas las configuraciones con filtros.
     */
    public function getAll(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = ConfigVarias::query();

        if (!empty($filters['search'])) {
            $query->where(fn($q) => $q
                ->where('clave', 'like', "%{$filters['search']}%")
                ->orWhere('valor', 'like', "%{$filters['search']}%")
            );
        }

        if (!empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }

        if (isset($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        $query->orderBy('clave', 'asc');

        $result = !empty($filters['per_page']) 
            ? $query->paginate($filters['per_page']) 
            : $query->get();

        return $this->injectLogoUrl($result);
    }

    /**
     * Obtiene una configuración por clave.
     */
    public function getByClave(string $clave): ?ConfigVarias
    {
        return ConfigVarias::where('clave', $clave)->first();
    }

    /**
     * Crea una nueva configuración.
     */
    public function create(array $data): ConfigVarias
    {
        return ConfigVarias::create($data);
    }

    /**
     * Actualiza una configuración.
     */
    public function update(string $clave, array $data): ?ConfigVarias
    {
        $config = ConfigVarias::where('clave', $clave)->first();

        if (!$config) {
            return null;
        }

        $config->fill($data)->save();
        return $config;
    }

    /**
     * Actualiza múltiples configuraciones en una sola operación.
     */
    public function updateBatch(array $configs): array
    {
        $results = [];

        foreach ($configs as $configData) {
            $clave = $configData['clave'];
            $valor = $configData['valor'];

            $config = ConfigVarias::where('clave', $clave)->first();

            if ($config) {
                $config->update(['valor' => $valor]);
                $results[] = $config->fresh();
            } else {
                $config = ConfigVarias::create([
                    'clave' => $clave,
                    'valor' => $valor,
                    'descripcion' => $configData['descripcion'] ?? 'Configuración batch',
                    'tipo' => $configData['tipo'] ?? 'text',
                    'estado' => $configData['estado'] ?? true,
                ]);
                $results[] = $config;
            }
        }

        return $results;
    }

    /**
     * Obtiene la numeración unificada.
     */
    public function getNumeracionUnificada(): bool
    {
        return ConfigVarias::getNumeracionUnificada();
    }

    /**
     * Actualiza la numeración unificada.
     */
    public function setNumeracionUnificada(bool $value): void
    {
        ConfigVarias::setNumeracionUnificada($value);
    }

    /**
     * Inyecta URL del logo si aplica.
     */
    private function injectLogoUrl($configs)
    {
        $callback = fn(ConfigVarias $config) => $config->clave === 'logo_empresa'
            ? $config->setAttribute('logo_url', $config->getArchivoUrl('valor', 'otros_archivos'))
            : $config;

        if ($configs instanceof \Illuminate\Pagination\AbstractPaginator) {
            $configs->getCollection()->transform($callback);
        } else {
            $configs->transform($callback);
        }

        return $configs;
    }
}