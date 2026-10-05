<?php

namespace App\Interfaces;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CategoryServiceInterface
{
    public function list(int $tenantId, int $perPage, string $search): LengthAwarePaginator;

    public function find(int $tenantId, int|string $id): Category;

    public function create(int $tenantId, array $data): Category;

    public function update(int $tenantId, int|string $id, array $data): Category;

    public function delete(int $tenantId, int|string $id): void;
}
