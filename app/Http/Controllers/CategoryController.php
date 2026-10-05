<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Interfaces\CategoryServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryServiceInterface $categoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->list(
            tenantId: $this->tenantId($request),
            perPage:  (int) $request->input('per_page', 10),
            search:   trim($request->input('search', '')),
        );

        return ApiResponse::paginated($categories, 'Liste des catégories récupérée avec succès');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $category = $this->categoryService->find($this->tenantId($request), $id);

        return ApiResponse::success($category);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create(
            $this->tenantId($request),
            $request->validated()
        );

        return ApiResponse::success($category, 'Catégorie créée avec succès', 201);
    }

    public function update(UpdateCategoryRequest $request, string $id): JsonResponse
    {
        $category = $this->categoryService->update(
            $this->tenantId($request),
            $id,
            $request->validated()
        );

        return ApiResponse::success($category, 'Catégorie modifiée avec succès');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->categoryService->delete($this->tenantId($request), $id);

        return ApiResponse::success(null, 'Catégorie supprimée avec succès');
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
