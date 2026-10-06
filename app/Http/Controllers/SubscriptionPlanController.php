<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\SubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Types d'abonnement (forfaits). L'administrateur les gère ; les gestionnaires voient les
 * forfaits actifs pour savoir combien payer et qui contacter.
 */
class SubscriptionPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = SubscriptionPlan::withCount('subscriptions')
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->ordered()
            ->get();

        return ApiResponse::success($plans);
    }

    public function store(SubscriptionPlanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['position'] ??= (int) SubscriptionPlan::max('position') + 1;
        $plan = SubscriptionPlan::create($data);

        return ApiResponse::success($plan->fresh(), 'Forfait créé avec succès.', 201);
    }

    /** Nom, description et ordre uniquement (voir SubscriptionPlanRequest). */
    public function update(SubscriptionPlanRequest $request, string $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $data = $request->validated();
        if (($data['position'] ?? null) === null) {
            unset($data['position']);
        }
        $plan->update($data);

        return ApiResponse::success($plan->fresh(), 'Forfait modifié avec succès.');
    }

    public function toggleStatus(string $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->update(['is_active' => !$plan->is_active]);

        return ApiResponse::success(
            $plan->fresh(),
            $plan->is_active ? 'Forfait activé.' : 'Forfait désactivé : il n\'est plus proposé.',
        );
    }

    /** Seul un forfait jamais utilisé peut être supprimé ; sinon on le désactive. */
    public function destroy(string $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);

        if ($plan->subscriptions()->withTrashed()->exists()) {
            return ApiResponse::error(
                'Ce forfait a déjà été utilisé pour des abonnements : désactivez-le plutôt que de le supprimer.',
                422,
                null,
                'PLAN_IN_USE',
            );
        }

        $plan->delete();

        return ApiResponse::success(null, 'Forfait supprimé avec succès.');
    }

    /**
     * Offres affichées aux gestionnaires (public : un gestionnaire bloqué n'a plus de session).
     */
    public function offers(): JsonResponse
    {
        return ApiResponse::success([
            'plans' => SubscriptionPlan::where('is_active', true)->ordered()
                ->get(['id', 'name', 'duration_months', 'price', 'currency', 'description']),
            'contact' => config('subscriptions.contact'),
        ]);
    }
}
