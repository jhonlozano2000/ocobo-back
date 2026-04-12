<?php

namespace App\Contracts\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserServiceInterface
{
    public function getAll(array $filters = []): Collection;
    public function getById(string $id): ?array;
    public function create(array $data): User;
    public function update(string $id, array $data): ?User;
    public function delete(string $id): bool;
    public function getStats(): array;
}