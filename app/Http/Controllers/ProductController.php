<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Interfaces\ProductServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductServiceInterface $productService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->productService->list(
            tenantId: $this->tenantId($request),
            perPage:  (int) $request->input('per_page', 10),
            search:   trim($request->input('search', '')),
        );

        return ApiResponse::paginated($products, 'Liste des produits récupérée avec succès');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $product = $this->productService->find($this->tenantId($request), $id);

        return ApiResponse::success($product);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create(
            $this->tenantId($request),
            $request->validated()
        );

        return ApiResponse::success($product, 'Produit créé avec succès', 201);
    }

    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        $product = $this->productService->update(
            $this->tenantId($request),
            $id,
            $request->validated()
        );

        return ApiResponse::success($product, 'Produit modifié avec succès');
    }

    public function toggleAvailability(Request $request, string $id): JsonResponse
    {
        $product = $this->productService->toggleAvailability($this->tenantId($request), $id);

        $message = $product->available
            ? 'Produit marqué comme disponible.'
            : 'Produit marqué comme indisponible.';

        return ApiResponse::success($product, $message);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->productService->delete($this->tenantId($request), $id);

        return ApiResponse::success(null, 'Produit supprimé avec succès');
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        if (! $tenantId) {
            abort(422, "Aucun tenant n'est associé à cet utilisateur.");
        }

        return $tenantId;
    }
}
