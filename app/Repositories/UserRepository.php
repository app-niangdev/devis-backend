<?php

namespace App\Repositories;

use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(int $perPage, string $search, string $searchRole): LengthAwarePaginator
    {
        return User::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereLike('first_name', "%{$search}%")
                      ->orWhereLike('last_name', "%{$search}%")
                      ->orWhereLike('phone_one', "%{$search}%")
                      ->orWhereLike('email', "%{$search}%");
                });
            })
            ->when($searchRole, function ($query) use ($searchRole) {
                $query->whereHas('role', fn ($q) => $q->whereLike('name', "%{$searchRole}%"));
            })
            ->with(['role', 'tenant:id,name'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findById(int|string $id): User
    {
        return User::with('role')->findOrFail($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh('role');
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function findTrashedById(int|string $id, ?int $tenantId = null): User
    {
        return User::onlyTrashed()->findOrFail($id);
    }

    public function restore(User $user): void
    {
        $user->restore();
    }

    public function forceDelete(int|string $id): void
    {
        User::withTrashed()->findOrFail($id)->forceDelete();
    }
}
