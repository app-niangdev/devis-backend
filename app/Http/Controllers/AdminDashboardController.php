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
use Illuminate\Support\Collection;

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
        $tenants = Tenant::with('subscriptions')->get();
        $statuses = $tenants
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
            'tenant_stats' => $this->tenantStats($tenants),
        ]);
    }

    /**
     * Devis acceptés, refusés, en attente (brouillon ou envoyé, sans réponse)
     * et acomptes encaissés, par entreprise.
     */
    private function tenantStats(Collection $tenants): Collection
    {
        $pending = [Quote::DRAFT, Quote::SENT];

        $rows = Quote::query()
            ->selectRaw('tenant_id, status, COUNT(*) AS n, COALESCE(SUM(total_amount), 0) AS total, COALESCE(SUM(deposit_received_amount), 0) AS collected')
            ->groupBy('tenant_id', 'status')
            ->toBase()
            ->get()
            ->groupBy('tenant_id');

        return $tenants
            ->map(function (Tenant $tenant) use ($rows, $pending) {
                $byStatus = ($rows[$tenant->id] ?? collect())->keyBy('status');
                $sum = fn (array $statuses, string $field) => (int) $byStatus->only($statuses)->sum($field);

                return [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'tenant_active' => (bool) $tenant->state,
                    'accepted' => ['count' => $sum([Quote::ACCEPTED], 'n'), 'total' => $sum([Quote::ACCEPTED], 'total')],
                    'refused' => ['count' => $sum([Quote::REFUSED], 'n'), 'total' => $sum([Quote::REFUSED], 'total')],
                    'pending' => ['count' => $sum($pending, 'n'), 'total' => $sum($pending, 'total')],
                    // Acomptes reçus sur les devis acceptés
                    'collected' => $sum([Quote::ACCEPTED], 'collected'),
                ];
            })
            ->sortBy([['collected', 'desc'], ['accepted.count', 'desc'], ['tenant_name', 'asc']])
            ->values();
    }
}
