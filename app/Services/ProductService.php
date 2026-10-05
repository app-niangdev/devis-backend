<?php

namespace App\Services;

use App\Interfaces\ProductRepositoryInterface;
use App\Interfaces\ProductServiceInterface;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductService implements ProductServiceInterface
{
    private const IMAGE_DISK = 'public';
    private const IMAGE_DIRECTORY = 'products/images';

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {}

    public function list(int $tenantId, int $perPage, string $search): LengthAwarePaginator
    {
        return $this->productRepository->paginate($tenantId, $perPage, $search);
    }

    public function find(int $tenantId, int|string $id): Product
    {
        return $this->productRepository->findById($tenantId, $id);
    }

    public function create(int $tenantId, array $data): Product
    {
        $data['tenant_id'] = $tenantId;

        $image = $data['image'] ?? null;
        unset($data['image']);

        if ($image instanceof UploadedFile) {
            $data['image_url'] = $this->storeImage($image);
        }

        return $this->productRepository->create($data);
    }

    public function update(int $tenantId, int|string $id, array $data): Product
    {
        $product = $this->productRepository->findById($tenantId, $id);

        $image = $data['image'] ?? null;
        $removeImage = (bool) ($data['remove_image'] ?? false);
        unset($data['image'], $data['remove_image']);

        if ($image instanceof UploadedFile) {
            $this->deleteImage($product->image_url);
            $data['image_url'] = $this->storeImage($image);
        } elseif ($removeImage) {
            $this->deleteImage($product->image_url);
            $data['image_url'] = null;
        }

        return $this->productRepository->update($product, $data);
    }

    public function delete(int $tenantId, int|string $id): void
    {
        $product = $this->productRepository->findById($tenantId, $id);

        $this->productRepository->delete($product);
    }

    public function toggleAvailability(int $tenantId, int|string $id): Product
    {
        $product = $this->productRepository->findById($tenantId, $id);

        return $this->productRepository->update($product, [
            'available' => ! $product->available,
        ]);
    }

    private function storeImage(UploadedFile $image): string
    {
        $path = $image->store(self::IMAGE_DIRECTORY, self::IMAGE_DISK);

        return Storage::disk(self::IMAGE_DISK)->url($path);
    }

    private function deleteImage(?string $imageUrl): void
    {
        if (! $imageUrl) {
            return;
        }

        $path = self::IMAGE_DIRECTORY . '/' . basename(parse_url($imageUrl, PHP_URL_PATH) ?? '');

        if (Storage::disk(self::IMAGE_DISK)->exists($path)) {
            Storage::disk(self::IMAGE_DISK)->delete($path);
        }
    }
}
