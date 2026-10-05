<?php

namespace App\Interfaces;

use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TenantServiceInterface
{
    public function list(int $perPage, string $search): LengthAwarePaginator;

    public function find(int|string $id): Tenant;

    public function create(array $data): Tenant;

    public function update(int|string $id, array $data): Tenant;

    public function updateProfile(Tenant $tenant, array $data): Tenant;

    public function disable(int|string $id): Tenant;

    public function toggleStatus(int|string $id): Tenant;

    public function restore(int|string $id): Tenant;

    public function forceDelete(int|string $id): void;
}
