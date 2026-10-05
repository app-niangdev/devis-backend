<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;

/**
 * Tableau de bord de l'administrateur de la plateforme.
 */
class AdminDashboardController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function __invoke(): JsonResponse
    {
        $statuses = Tenant::with('subscriptions')->get()
            ->map(fn (Tenant $tenant) => $this->subscriptions->statusForTenant($tenant) + ['tenant_active' => $tenant->state]);

        $monthStart = now()->startOfMonth();
        $managerRoleId = Role::where('name', 'MANAGER')->value('id');

        return ApiResponse::success([
            'tenants' => [
                'total' => $statuses->count(),
                'active' => $statuses->where('tenant_active', true)->count(),
            ],
            'managers' => User::where('role_id', $managerRoleId)->count(),
            'subscriptions' => collect([
                SubscriptionService::ACTIVE, SubscriptionService::EXPIRING, SubscriptionService::EXPIRED, SubscriptionService::NONE,
            ])->mapWithKeys(fn ($state) => [$state => $statuses->where('state', $state)->count()]),
            // Encaissements d'abonnements saisis ce mois-ci
            'revenue_month' => (float) Subscription::where('created_at', '>=', $monthStart)->sum('amount'),
            'quotes_month' => Quote::where('created_at', '>=', $monthStart)->count(),
            // À relancer : échéance proche ou dépassée
            'attention' => $statuses
                ->whereIn('state', [SubscriptionService::EXPIRING, SubscriptionService::EXPIRED, SubscriptionService::NONE])
                ->sortBy(fn ($s) => $s['days_left'] ?? PHP_INT_MIN)
                ->take(8)
                ->values(),
        ]);
    }
}
