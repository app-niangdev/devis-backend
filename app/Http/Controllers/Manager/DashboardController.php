<?php

namespace App\Http\Controllers\Manager;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Quote;
use App\Services\SubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Indicateurs de l'écran d'accueil du gestionnaire.
 *
 * Période facultative : `year` (+ `month`) ou `date` précise. Les chiffres portent alors sur
 * les devis créés pendant la période (acceptés : décidés pendant la période).
 * Sans période (anciennes versions de l'application) : chiffres globaux et bloc « mois en cours ».
 * La liste des devis en attente de réponse reste globale : c'est le travail à faire.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'year.*' => 'Année invalide.',
            'month.*' => 'Mois invalide.',
            'date.*' => 'Date invalide (format attendu : AAAA-MM-JJ).',
        ]);

        $tenant = $request->user()->tenant;
        [$from, $to] = $this->period($filters);
        $within = fn (Builder $q, string $column) => $from ? $q->whereBetween($column, [$from, $to]) : $q;

        $all = fn () => Quote::where('tenant_id', $tenant->id);
        $quotes = fn () => $within($all(), 'created_at');
        // Bloc « période » : la période choisie, sinon le mois en cours
        [$periodStart, $periodEnd] = $from ? [$from, $to] : [now()->startOfMonth(), now()->endOfMonth()];
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
                'created' => $all()->whereBetween('created_at', [$periodStart, $periodEnd])->count(),
                'accepted_count' => $all()->where('status', Quote::ACCEPTED)->whereBetween('decided_at', [$periodStart, $periodEnd])->count(),
                'accepted_amount' => (int) $all()->where('status', Quote::ACCEPTED)->whereBetween('decided_at', [$periodStart, $periodEnd])->sum('total_amount'),
            ],
            'period' => $from ? ['from' => $from->toDateString(), 'to' => $to->toDateString()] : null,
            // Années proposées dans le filtre : du premier devis à aujourd'hui
            'years' => $this->years($tenant->id),
            'deposits' => [
                'expected' => $depositsExpected,
                'received' => $depositsReceived,
                'pending_count' => (clone $accepted)->where('deposit_amount', '>', 0)->whereNull('deposit_received_amount')->count(),
            ],
            'customers_count' => Customer::where('tenant_id', $tenant->id)->count(),
            'awaiting_answer' => $all()
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

    /**
     * Bornes de la période demandée, ou [null, null] pour tout l'historique.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function period(array $filters): array
    {
        if (!empty($filters['date'])) {
            $day = CarbonImmutable::parse($filters['date']);

            return [$day->startOfDay(), $day->endOfDay()];
        }

        if (!empty($filters['year'])) {
            $start = CarbonImmutable::create((int) $filters['year'], (int) ($filters['month'] ?? 1), 1);

            return empty($filters['month'])
                ? [$start->startOfYear(), $start->endOfYear()]
                : [$start->startOfMonth(), $start->endOfMonth()];
        }

        return [null, null];
    }

    /** @return list<int> du plus récent au plus ancien */
    private function years(int $tenantId): array
    {
        $first = Quote::where('tenant_id', $tenantId)->min('created_at');
        $current = (int) now()->year;
        $oldest = $first ? min((int) CarbonImmutable::parse($first)->year, $current) : $current;

        return range($current, $oldest);
    }
}
