<?php

namespace App\Services;

use App\Interfaces\CategoryRepositoryInterface;
use App\Interfaces\CategoryServiceInterface;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryService implements CategoryServiceInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {}

    public function list(int $tenantId, int $perPage, string $search): LengthAwarePaginator
    {
        return $this->categoryRepository->paginate($tenantId, $perPage, $search);
    }

    public function find(int $tenantId, int|string $id): Category
    {
        return $this->categoryRepository->findById($tenantId, $id);
    }

    public function create(int $tenantId, array $data): Category
    {
        $data['tenant_id'] = $tenantId;

        return $this->categoryRepository->create($data);
    }

    public function update(int $tenantId, int|string $id, array $data): Category
    {
        $category = $this->categoryRepository->findById($tenantId, $id);

        return $this->categoryRepository->update($category, $data);
    }

    public function delete(int $tenantId, int|string $id): void
    {
        $category = $this->categoryRepository->findById($tenantId, $id);

        $this->categoryRepository->delete($category);
    }
}
