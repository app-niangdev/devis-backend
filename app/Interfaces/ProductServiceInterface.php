<?php

namespace App\Interfaces;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductServiceInterface
{
    public function list(int $tenantId, int $perPage, string $search): LengthAwarePaginator;

    public function find(int $tenantId, int|string $id): Product;

    public function create(int $tenantId, array $data): Product;

    public function update(int $tenantId, int|string $id, array $data): Product;

    public function delete(int $tenantId, int|string $id): void;

    public function toggleAvailability(int $tenantId, int|string $id): Product;
}
