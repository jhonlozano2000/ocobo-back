<?php

namespace App\Contracts\Services;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use Illuminate\Pagination\LengthAwarePaginator;

interface TRDServiceInterface
{
    public function getAll(array $filters = []): LengthAwarePaginator;
    public function getStatsByDependencia(int $id): array;
    public function getTotalStats(): array;
    public function create(array $data): ClasificacionDocumentalTRD;
    public function update(int $id, array $data): ?ClasificacionDocumentalTRD;
    public function delete(int $id): bool;
}