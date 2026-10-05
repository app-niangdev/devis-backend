<?php

namespace App\Interfaces;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function paginate(int $perPage, string $search, string $searchRole): LengthAwarePaginator;

    public function findById(int|string $id): User;

    public function create(array $data): User;

    public function update(User $user, array $data): User;

    public function delete(User $user): void;

    public function findTrashedById(int|string $id, ?int $tenantId = null): User;

    public function restore(User $user): void;

    public function forceDelete(int|string $id): void;
}
