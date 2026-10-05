<?php

namespace App\Http\Controllers\Manager;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Quote;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Indicateurs de l'écran d'accueil du gestionnaire.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $quotes = fn () => Quote::where('tenant_id', $tenant->id);
        $monthStart = now()->startOfMonth();
        $expired = fn () => $quotes()->whereIn('status', [Quote::DRAFT, Quote::SENT])->whereDate('valid_until', '<', today());

        $byStatus = $quotes()
            ->selectRaw('status, COUNT(*) AS n, COALESCE(SUM(total_amount), 0) AS total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = fn (string $s) => (int) ($byStatus[$s]->n ?? 0);
        $answered = $count(Quote::ACCEPTED) + $count(Quote::REFUSED);

        $accepted = $quotes()->where('status', Quote::ACCEPTED);
        $depositsExpected = (int) (clone $accepted)->where('deposit_amount', '>', 0)->sum('deposit_amount');
        $depositsReceived = (int) (clone $accepted)->sum('deposit_received_amount');

        return ApiResponse::success([
            'subscription' => $this->subscriptions->statusForTenant($tenant),
            'quotes' => collect(Quote::STATUSES)->mapWithKeys(fn ($s) => [$s => [
                'count' => $count($s),
                'total' => (int) ($byStatus[$s]->total ?? 0),
            ]])->put('expired', [
                // Même règle que le filtre « Expirés » de la liste : validité dépassée sans réponse
                'count' => $expired()->count(),
                'total' => (int) $expired()->sum('total_amount'),
            ])->put('sent_expired', [
                'count' => $expired()->where('status', Quote::SENT)->count(),
                'total' => (int) $expired()->where('status', Quote::SENT)->sum('total_amount'),
            ]),
            'acceptance_rate' => $answered > 0 ? round($count(Quote::ACCEPTED) * 100 / $answered, 1) : null,
            'month' => [
                'created' => $quotes()->where('created_at', '>=', $monthStart)->count(),
                'accepted_count' => $quotes()->where('status', Quote::ACCEPTED)->where('decided_at', '>=', $monthStart)->count(),
                'accepted_amount' => (int) $quotes()->where('status', Quote::ACCEPTED)->where('decided_at', '>=', $monthStart)->sum('total_amount'),
            ],
            'deposits' => [
                'expected' => $depositsExpected,
                'received' => $depositsReceived,
                'pending_count' => (clone $accepted)->where('deposit_amount', '>', 0)->whereNull('deposit_received_amount')->count(),
            ],
            'customers_count' => Customer::where('tenant_id', $tenant->id)->count(),
            'awaiting_answer' => $quotes()
                ->with('customer:id,name')
                ->where('status', Quote::SENT)
                ->orderBy('sent_at')
                ->limit(5)
                ->get()
                ->map(fn (Quote $q) => [
                    'id' => $q->id,
                    'quote_number' => $q->quote_number,
                    'customer_name' => $q->customer?->name,
                    'total_amount' => $q->total_amount,
                    'sent_at' => $q->sent_at?->toIso8601String(),
                    'is_expired' => $q->isExpired(),
                ]),
        ]);
    }
}
