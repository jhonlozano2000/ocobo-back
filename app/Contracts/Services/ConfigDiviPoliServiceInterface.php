<?php

namespace App\Contracts\Services;

use App\Models\Configuracion\ConfigDiviPoli;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ConfigDiviPoliServiceInterface
{
    public function getAll(array $filters = []): LengthAwarePaginator|Collection;
    public function getHierarchy(): Collection;
    public function getPaises(): Collection;
    public function getDepartamentos(int $paisId): Collection;
    public function getMunicipios(int $departamentoId): Collection;
    public function getStats(): array;
    public function create(array $data): ConfigDiviPoli;
    public function update(int $id, array $data): ?ConfigDiviPoli;
    public function delete(int $id): bool;
    public function isTipoValido(string $tipo): bool;
}