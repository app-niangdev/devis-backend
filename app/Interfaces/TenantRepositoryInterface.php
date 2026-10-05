<?php

namespace App\Interfaces;

use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TenantRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator;

    public function findById(int|string $id): Tenant;

    public function findTrashedById(int|string $id): Tenant;

    public function create(array $data): Tenant;

    public function update(Tenant $tenant, array $data): Tenant;

    public function delete(Tenant $tenant): void;

    public function restore(Tenant $tenant): void;

    public function forceDelete(int|string $id): void;
}
