<?php

namespace App\Repositories;

use App\Interfaces\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function paginate(int $tenantId, int $perPage, string $search): LengthAwarePaginator
    {
        return Category::query()
            ->where('tenant_id', $tenantId)
            ->when($search, function ($query) use ($search) {
                $query->whereLike('name', "%{$search}%");
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findById(int $tenantId, int|string $id): Category
    {
        return Category::where('tenant_id', $tenantId)->findOrFail($id);
    }

    public function create(array $data): Category
    {
        return Category::create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->fresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
