<?php

namespace App\Repositories;

use App\Interfaces\TenantRepositoryInterface;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TenantRepository implements TenantRepositoryInterface
{
    public function paginate(int $perPage, string $search, ?string $approvalStatus = null): LengthAwarePaginator
    {
        return Tenant::query()
            ->when($approvalStatus, fn ($query) => $query->where('approval_status', $approvalStatus))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereLike('name', "%{$search}%")
                      ->orWhereLike('code_website', "%{$search}%")
                      ->orWhereLike('short_name', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findById(int|string $id): Tenant
    {
        return Tenant::findOrFail($id);
    }

    public function findTrashedById(int|string $id): Tenant
    {
        return Tenant::onlyTrashed()->findOrFail($id);
    }

    public function create(array $data): Tenant
    {
        return Tenant::create($data);
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        return $tenant->fresh();
    }

    public function delete(Tenant $tenant): void
    {
        $tenant->delete();
    }

    public function restore(Tenant $tenant): void
    {
        $tenant->restore();
    }

    public function forceDelete(int|string $id): void
    {
        Tenant::withTrashed()->findOrFail($id)->forceDelete();
    }
}
