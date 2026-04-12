<?php

namespace App\Contracts\Services;

use App\Models\Calidad\CalidadOrganigrama;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CalidadOrganigramaServiceInterface
{
    public function getAll(array $filters = []): LengthAwarePaginator|Collection;
    public function getDependencias(array $filters = []): LengthAwarePaginator|Collection;
    public function getOficinas(array $filters = []): LengthAwarePaginator|Collection;
    public function getStats(): array;
    public function create(array $data): CalidadOrganigrama;
    public function update(int $id, array $data): CalidadOrganigrama;
    public function delete(int $id): bool;
}