<?php

namespace App\Repositories;

use App\Interfaces\ProductRepositoryInterface;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository implements ProductRepositoryInterface
{
    public function paginate(int $tenantId, int $perPage, string $search): LengthAwarePaginator
    {
        return Product::query()
            ->where('tenant_id', $tenantId)
            ->when($search, function ($query) use ($search) {
                $query->whereLike('name', "%{$search}%");
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findById(int $tenantId, int|string $id): Product
    {
        return Product::where('tenant_id', $tenantId)->findOrFail($id);
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
