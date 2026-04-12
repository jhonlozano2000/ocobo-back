<?php

namespace App\Contracts\Services;

use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\Collection;

interface RoleServiceInterface
{
    public function getAll(array $filters = []);
    public function getWithUsers(): Collection;
    public function getAllPermissions(array $filters = []): Collection;
    public function getStats(): array;
    public function create(array $data): Role;
    public function update(int $id, array $data): ?Role;
    public function delete(int $id): array;
}