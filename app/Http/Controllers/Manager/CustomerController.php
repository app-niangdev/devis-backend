<?php

namespace App\Http\Controllers\Manager;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Quote;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clients de l'entreprise du gestionnaire.
 */
class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:name,recent,quotes,accepted'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $customers = $this->customers->list($request->user()->tenant_id, $filters);
        $customers->getCollection()->transform(fn (Customer $c) => $this->present($c));

        return ApiResponse::paginated($customers, 'Liste des clients récupérée avec succès');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $customer = $this->customers->find($request->user()->tenant_id, $id);

        $quotes = $customer->quotes()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Quote $q) => [
                'id' => $q->id,
                'quote_number' => $q->quote_number,
                'title' => $q->title,
                'status' => $q->status,
                'is_expired' => $q->isExpired(),
                'total_amount' => $q->total_amount,
                'created_at' => $q->created_at?->toIso8601String(),
            ]);

        return ApiResponse::success($this->present($customer) + ['quotes' => $quotes]);
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->create($request->user()->tenant_id, $request->validated());

        return ApiResponse::success($this->present($customer), 'Client enregistré avec succès.', 201);
    }

    public function update(CustomerRequest $request, string $id): JsonResponse
    {
        $customer = $this->customers->find($request->user()->tenant_id, $id);
        $customer = $this->customers->update($customer, $request->validated());

        return ApiResponse::success($this->present($customer), 'Client modifié avec succès.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $customer = $this->customers->find($request->user()->tenant_id, $id);
        $this->customers->delete($customer);

        return ApiResponse::success(null, 'Client supprimé avec succès.');
    }

    private function present(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'phone_display' => $customer->phone_display,
            'address' => $customer->address,
            'email' => $customer->email,
            'notes' => $customer->notes,
            'quotes_count' => (int) ($customer->quotes_count ?? 0),
            'accepted_amount' => (int) ($customer->accepted_amount ?? 0),
            'last_quote_at' => $customer->last_quote_at ? \Illuminate\Support\Carbon::parse($customer->last_quote_at)->toIso8601String() : null,
            'created_at' => $customer->created_at?->toIso8601String(),
        ];
    }
}
