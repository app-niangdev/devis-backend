<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Interfaces\TenantServiceInterface;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantServiceInterface $tenantService,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('admin');

        $request->validate([
            'approval_status' => ['nullable', Rule::in([Tenant::APPROVED, Tenant::PENDING])],
        ]);

        $tenants = $this->tenantService->list(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
            approvalStatus: $request->input('approval_status'),
        );

        $tenants->getCollection()->transform(fn (Tenant $tenant) => $this->present($tenant));

        return ApiResponse::paginated($tenants, 'Liste des entreprises récupérée avec succès');
    }

    public function show(string $id): JsonResponse
    {
        Gate::authorize('admin');

        $tenant = $this->tenantService->find($id);

        return ApiResponse::success($this->present($tenant));
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantService->create($request->validated());

        return ApiResponse::success($tenant, 'Entreprise créée avec succès', 201);
    }

    public function update(UpdateTenantRequest $request, string $id): JsonResponse
    {
        $tenant = $this->tenantService->update($id, $request->validated());

        return ApiResponse::success($tenant, 'Entreprise modifiée avec succès');
    }

    public function disable(string $id): JsonResponse
    {
        Gate::authorize('admin');

        $tenant = $this->tenantService->disable($id);

        return ApiResponse::success($tenant, 'Entreprise désactivée avec succès');
    }

    public function toggleStatus(string $id): JsonResponse
    {
        Gate::authorize('admin');

        $tenant = $this->tenantService->toggleStatus($id);

        $message = $tenant->state
            ? 'Entreprise activée avec succès.'
            : 'Entreprise désactivée avec succès.';

        return ApiResponse::success($tenant, $message);
    }

    public function restore(string $id): JsonResponse
    {
        Gate::authorize('admin');

        $tenant = $this->tenantService->restore($id);

        return ApiResponse::success($tenant, 'Entreprise réactivée avec succès.');
    }

    /** Entreprise, son gestionnaire et l'état de son abonnement. */
    private function present(Tenant $tenant): array
    {
        $tenant->loadMissing(['subscriptions', 'users' => fn ($q) => $q->with('role:id,name')]);

        return $tenant->attributesToArray() + [
            'managers' => $tenant->users
                ->filter(fn ($user) => $user->isManager())
                ->map(fn ($user) => $user->only(['id', 'first_name', 'last_name', 'full_name', 'phone_one', 'email', 'status']) + [
                    'phone_verified' => $user->phone_verified_at !== null,
                ])
                ->values(),
            'subscription' => $this->subscriptions->statusForTenant($tenant),
            // Date d'inscription (inscriptions faites depuis l'application)
            'registered_at' => $tenant->created_at?->toIso8601String(),
        ];
    }

    public function destroy(string $id): JsonResponse
    {
        Gate::authorize('admin');

        $this->tenantService->forceDelete($id);

        return ApiResponse::success(null, 'Entreprise supprimée définitivement.');
    }
}
