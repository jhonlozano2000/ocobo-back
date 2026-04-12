<?php

namespace App\Contracts\Services;

use App\Models\Configuracion\ConfigLista;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ConfigListaServiceInterface
{
    public function getAll(array $filters = []): LengthAwarePaginator|Collection;
    public function getOnlyHeads(array $filters = []): LengthAwarePaginator|Collection;
    public function getWithActiveDetails(int $id): ?ConfigLista;
    public function create(array $data): ConfigLista;
    public function update(int $id, array $data): ?ConfigLista;
    public function delete(int $id): bool;
}