<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Quote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Clients d'une entreprise, avec leurs indicateurs de devis.
 */
class CustomerService
{
    /**
     * @param array{search?: ?string, sort?: ?string, per_page?: ?int} $filters
     */
    public function list(int $tenantId, array $filters): LengthAwarePaginator
    {
        $query = $this->withStats(Customer::where('tenant_id', $tenantId))
            ->when(trim((string) ($filters['search'] ?? '')), function (Builder $q, string $search) {
                $digits = preg_replace('/\D+/', '', $search);
                $q->where(fn ($sub) => $sub
                    ->whereLike('name', "%{$search}%")
                    ->when($digits !== '', fn ($p) => $p->orWhereLike('phone', "%{$digits}%")));
            });

        match ($filters['sort'] ?? 'name') {
            // Sans devis (NULL) en dernier, sous PostgreSQL comme SQLite
            'recent' => $query->orderByRaw('last_quote_at DESC NULLS LAST')->orderBy('name'),
            'quotes' => $query->orderByDesc('quotes_count')->orderBy('name'),
            'accepted' => $query->orderByRaw('accepted_amount DESC NULLS LAST')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        return $query->paginate(max(1, min(100, (int) ($filters['per_page'] ?? 20))))->withQueryString();
    }

    public function find(int $tenantId, int|string $id): Customer
    {
        return $this->withStats(Customer::where('tenant_id', $tenantId))->findOrFail($id);
    }

    public function create(int $tenantId, array $data): Customer
    {
        $customer = Customer::create($data + ['tenant_id' => $tenantId]);

        return $this->find($tenantId, $customer->id);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $this->find($customer->tenant_id, $customer->id);
    }

    /** Suppression douce : le client reste visible sur ses devis. */
    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    /** Indicateurs : nombre de devis, montant accepté, date du dernier devis. */
    private function withStats(Builder $query): Builder
    {
        return $query
            ->withCount('quotes')
            ->withSum(['quotes as accepted_amount' => fn ($q) => $q->where('status', Quote::ACCEPTED)], 'total_amount')
            ->withMax('quotes as last_quote_at', 'created_at');
    }
}
