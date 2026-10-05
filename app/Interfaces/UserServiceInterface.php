<?php

namespace App\Interfaces;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserServiceInterface
{
    public function list(int $perPage, string $search, string $searchRole): LengthAwarePaginator;

    public function find(int|string $id): User;

    public function create(array $data, User $authUser): User;

    public function update(int|string $id, array $data, User $authUser): User;

    public function resetAccess(int|string $id, string $temporaryPassword): User;

    public function disable(int|string $id, User $authUser): User;

    public function toggleStatus(int|string $id, User $authUser): User;

    public function restore(int|string $id, User $authUser): User;

    public function forceDelete(int|string $id): void;
}
