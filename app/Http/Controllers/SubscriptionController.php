<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\SubscriptionRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des abonnements des entreprises (administrateur). Le paiement se fait hors de
 * l'application (Wave, Orange Money…) : l'administrateur enregistre la période payée.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /**
     * Entreprises avec leur état d'abonnement (vue d'ensemble).
     */
    public function overview(Request $request): JsonResponse
    {
        $query = Tenant::with('subscriptions')->orderBy('name');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(fn ($q) => $q->whereLike('name', "%{$search}%")->orWhereLike('code_website', "%{$search}%"));
        }

        $all = $query->get()->map(fn (Tenant $tenant) => $this->subscriptions->statusForTenant($tenant) + [
            'tenant_active' => $tenant->state,
        ]);

        // Filtre sur l'état calculé (active, expiring, expired, none)
        $statuses = ($state = $request->input('state')) ? $all->where('state', $state) : $all;

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $page = max(1, (int) $request->input('page', 1));
        $total = $statuses->count();

        return response()->json([
            'status' => 200,
            'message' => 'Succès',
            // Les plus urgents d'abord ; sans abonnement en tête
            'payload' => $statuses->sortBy(fn ($s) => $s['days_left'] ?? PHP_INT_MIN)->values()->forPage($page, $perPage)->values(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'counts' => [
                SubscriptionService::ACTIVE => $all->where('state', SubscriptionService::ACTIVE)->count(),
                SubscriptionService::EXPIRING => $all->where('state', SubscriptionService::EXPIRING)->count(),
                SubscriptionService::EXPIRED => $all->where('state', SubscriptionService::EXPIRED)->count(),
                SubscriptionService::NONE => $all->where('state', SubscriptionService::NONE)->count(),
            ],
        ]);
    }

    /**
     * Historique des abonnements d'une entreprise.
     */
    public function index(string $tenantId): JsonResponse
    {
        $tenant = Tenant::with(['subscriptions.creator'])->findOrFail($tenantId);

        return ApiResponse::success([
            'status' => $this->subscriptions->statusForTenant($tenant),
            'subscriptions' => $tenant->subscriptions->sortByDesc('starts_at')->values(),
        ]);
    }

    public function store(SubscriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($conflict = $this->subscriptions->overlapping($validated['tenant_id'], $validated['starts_at'], $validated['ends_at'])) {
            return $this->overlapResponse($conflict);
        }

        $subscription = Subscription::create($validated + ['created_by' => $request->user()->id]);

        return ApiResponse::success($subscription, 'Abonnement enregistré avec succès.', 201);
    }

    public function update(SubscriptionRequest $request, string $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        $validated = $request->validated();

        if ($conflict = $this->subscriptions->overlapping($validated['tenant_id'], $validated['starts_at'], $validated['ends_at'], $subscription->id)) {
            return $this->overlapResponse($conflict);
        }

        $subscription->update($validated);

        return ApiResponse::success($subscription->fresh(), 'Abonnement modifié avec succès.');
    }

    public function destroy(string $id): JsonResponse
    {
        Subscription::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Abonnement supprimé avec succès.');
    }

    private function overlapResponse(Subscription $conflict): JsonResponse
    {
        return ApiResponse::error(
            sprintf(
                'Cette période chevauche l\'abonnement « %s » du %s au %s.',
                $conflict->plan,
                $conflict->starts_at->format('d/m/Y'),
                $conflict->ends_at->format('d/m/Y')
            ),
            422,
            null,
            'SUBSCRIPTION_OVERLAP',
        );
    }
}
