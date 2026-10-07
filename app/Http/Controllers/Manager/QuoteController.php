<?php

namespace App\Http\Controllers\Manager;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteRequest;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\QuoteDocumentService;
use App\Services\QuoteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Devis de l'entreprise du gestionnaire.
 */
class QuoteController extends Controller
{
    public function __construct(
        private readonly QuoteService $quotes,
        private readonly QuoteDocumentService $documents,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            // « expired » : envoyé ou brouillon dont la validité est dépassée (non stocké)
            'status' => ['nullable', Rule::in([...Quote::STATUSES, 'expired'])],
            'customer_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $base = fn () => $this->baseQuery($tenantId, $filters);

        $list = $base()
            ->with('customer:id,name,phone')
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $status === 'expired'
                ? $this->expired($q)
                : $q->where('status', $status))
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();

        $counts = $base()
            ->selectRaw('status, COUNT(*) AS n, COALESCE(SUM(total_amount), 0) AS total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $list->getCollection()->transform(fn (Quote $q) => $this->summary($q));

        return response()->json([
            'status' => 200,
            'message' => 'Liste des devis récupérée avec succès',
            'payload' => $list->items(),
            'meta' => [
                'current_page' => $list->currentPage(),
                'per_page' => $list->perPage(),
                'total' => $list->total(),
                'last_page' => $list->lastPage(),
            ],
            'summary' => collect(Quote::STATUSES)->mapWithKeys(fn ($s) => [$s => [
                'count' => (int) ($counts[$s]->n ?? 0),
                'total' => (int) ($counts[$s]->total ?? 0),
            ]])->put('expired', ['count' => $this->expired($base())->count()]),
        ]);
    }

    /**
     * Unités les plus utilisées sur les fournitures de l'entreprise (suggestions de la saisie mobile).
     * Les variantes de casse (« Kg », « kg ») sont regroupées sous l'écriture la plus fréquente.
     */
    public function units(Request $request): JsonResponse
    {
        $rows = QuoteItem::query()
            ->join('quotes', 'quotes.id', '=', 'quote_items.quote_id')
            ->where('quotes.tenant_id', $request->user()->tenant_id)
            ->whereNull('quotes.deleted_at')
            ->where('quote_items.kind', QuoteItem::SUPPLY)
            ->whereNotNull('quote_items.unit_name')
            ->where('quote_items.unit_name', '!=', '')
            ->selectRaw('quote_items.unit_name AS unit, COUNT(*) AS uses, MAX(quote_items.id) AS last_id')
            ->groupBy('quote_items.unit_name')
            ->get();

        $units = $rows
            ->groupBy(fn ($row) => mb_strtolower(trim($row->unit)))
            ->map(fn ($variants) => [
                'unit' => trim($variants->sortByDesc('uses')->first()->unit),
                'uses' => (int) $variants->sum('uses'),
                'last_id' => (int) $variants->max('last_id'),
            ])
            // À égalité, la plus récemment utilisée d'abord
            ->sortBy([['uses', 'desc'], ['last_id', 'desc']])
            ->take(12)
            ->map(fn ($u) => ['unit' => $u['unit'], 'uses' => $u['uses']])
            ->values();

        return ApiResponse::success($units);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return ApiResponse::success($this->detail($this->find($request, $id)));
    }

    public function store(QuoteRequest $request): JsonResponse
    {
        $user = $request->user();
        $quote = $this->quotes->create($user->tenant, $user, $request->validated());

        return ApiResponse::success($this->detail($quote), "Devis {$quote->quote_number} enregistré.", 201);
    }

    public function update(QuoteRequest $request, string $id): JsonResponse
    {
        $quote = $this->quotes->update($this->find($request, $id), $request->validated());

        return ApiResponse::success($this->detail($quote), "Devis {$quote->quote_number} mis à jour.");
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $quote = $this->find($request, $id);
        $this->quotes->delete($quote);

        return ApiResponse::success(null, "Devis {$quote->quote_number} supprimé.");
    }

    public function duplicate(Request $request, string $id): JsonResponse
    {
        $copy = $this->quotes->duplicate($this->find($request, $id), $request->user());

        return ApiResponse::success($this->detail($copy), "Copie créée en brouillon : devis {$copy->quote_number}.", 201);
    }

    /** Envoyé par un autre moyen (remis en main propre, partagé depuis le téléphone…). */
    public function markSent(Request $request, string $id): JsonResponse
    {
        $quote = $this->find($request, $id);
        if ($quote->status !== Quote::DRAFT) {
            return ApiResponse::error('Ce devis n\'est plus un brouillon.', 422, null, 'QUOTE_NOT_DRAFT');
        }

        $quote = $this->quotes->markSent($quote);

        return ApiResponse::success($this->detail($quote), "Devis {$quote->quote_number} marqué comme envoyé.");
    }

    public function decide(Request $request, string $id): JsonResponse
    {
        $decision = $request->validate(
            ['decision' => ['required', Rule::in([Quote::ACCEPTED, Quote::REFUSED])]],
            ['decision.*' => 'Indiquez si le client accepte ou refuse le devis.'],
        )['decision'];

        $quote = $this->quotes->decide($this->find($request, $id), $decision);

        return ApiResponse::success(
            $this->detail($quote),
            "Devis {$quote->quote_number} " . ($decision === Quote::ACCEPTED ? 'accepté.' : 'refusé.'),
        );
    }

    public function recordDeposit(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::in(Quote::PAYMENT_METHODS)],
            'reference' => ['nullable', 'string', 'max:100'],
        ], [
            'amount.required' => 'Le montant reçu est obligatoire.',
            'amount.integer' => 'Le montant doit être un nombre entier en FCFA.',
            'amount.min' => 'Le montant reçu doit être supérieur à 0.',
            'received_at.required' => 'La date de réception est obligatoire.',
            'received_at.before_or_equal' => 'La date de réception ne peut pas être dans le futur.',
            'payment_method.required' => 'Le moyen de paiement est obligatoire.',
            'payment_method.in' => 'Le moyen de paiement est invalide.',
        ]);

        $quote = $this->quotes->recordDeposit($this->find($request, $id), $data);

        return ApiResponse::success($this->detail($quote), 'Acompte enregistré.');
    }

    public function cancelDeposit(Request $request, string $id): JsonResponse
    {
        $quote = $this->quotes->cancelDeposit($this->find($request, $id));

        return ApiResponse::success($this->detail($quote), 'Saisie de l\'acompte annulée.');
    }

    public function pdf(Request $request, string $id): Response
    {
        $quote = $this->find($request, $id);

        return response($this->documents->pdf($quote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->documents->filename($quote) . '"',
        ]);
    }

    public function whatsapp(Request $request, string $id): JsonResponse
    {
        $quote = $this->find($request, $id);

        if (!$this->documents->whatsappAvailable($quote)) {
            return ApiResponse::error(
                'Envoi WhatsApp indisponible : service non configuré ou client sans numéro valide.',
                422,
                null,
                'WHATSAPP_UNAVAILABLE',
            );
        }

        try {
            $this->documents->sendWhatsapp($quote);
        } catch (\Throwable $e) {
            Log::warning('Envoi WhatsApp du devis impossible', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);

            return ApiResponse::error('L\'envoi WhatsApp a échoué. Réessayez dans un instant.', 502, null, 'WHATSAPP_FAILED');
        }

        $quote = $this->quotes->markSent($quote);

        return ApiResponse::success($this->detail($quote), 'Devis envoyé au client sur WhatsApp.');
    }

    private function find(Request $request, string $id): Quote
    {
        return Quote::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
    }

    private function baseQuery(int $tenantId, array $filters): Builder
    {
        return Quote::where('tenant_id', $tenantId)
            ->when($filters['customer_id'] ?? null, fn (Builder $q, $customerId) => $q->where('customer_id', $customerId))
            ->when(trim((string) ($filters['search'] ?? '')), function (Builder $q, string $search) {
                $digits = preg_replace('/\D+/', '', $search);
                $q->where(fn (Builder $sub) => $sub
                    ->whereLike('quote_number', "%{$search}%")
                    ->orWhereLike('title', "%{$search}%")
                    ->orWhereHas('customer', fn (Builder $c) => $c
                        ->whereLike('name', "%{$search}%")
                        ->when($digits !== '', fn ($p) => $p->orWhereLike('phone', "%{$digits}%"))));
            });
    }

    private function expired(Builder $query): Builder
    {
        return $query->whereIn('status', [Quote::DRAFT, Quote::SENT])->whereDate('valid_until', '<', today());
    }

    private function summary(Quote $quote): array
    {
        return [
            'id' => $quote->id,
            'quote_number' => $quote->quote_number,
            'title' => $quote->title,
            'status' => $quote->status,
            'is_expired' => $quote->isExpired(),
            'customer' => $quote->customer ? [
                'id' => $quote->customer->id,
                'name' => $quote->customer->name,
                'phone_display' => $quote->customer->phone_display,
            ] : null,
            'total_amount' => $quote->total_amount,
            'deposit_amount' => $quote->deposit_amount,
            'deposit_status' => $quote->depositStatus(),
            'valid_until' => $quote->valid_until?->toDateString(),
            'created_at' => $quote->created_at?->toIso8601String(),
        ];
    }

    private function detail(Quote $quote): array
    {
        $quote->loadMissing(['customer', 'items', 'source:id,quote_number', 'user:id,first_name,last_name']);

        return $this->summary($quote) + [
            'customer' => $quote->customer ? [
                'id' => $quote->customer->id,
                'name' => $quote->customer->name,
                'phone' => $quote->customer->phone,
                'phone_display' => $quote->customer->phone_display,
                'address' => $quote->customer->address,
            ] : null,
            'notes' => $quote->notes,
            'supplies_amount' => $quote->supplies_amount,
            'labor_amount' => $quote->labor_amount,
            'gross_amount' => $quote->gross_amount,
            'discount' => $quote->discount,
            'deposit_type' => $quote->deposit_type,
            'deposit_value' => $quote->deposit_value,
            'balance_amount' => $quote->total_amount - $quote->deposit_amount,
            'deposit_received' => $quote->deposit_received_amount ? [
                'amount' => $quote->deposit_received_amount,
                'received_at' => $quote->deposit_received_at?->toDateString(),
                'payment_method' => $quote->deposit_payment_method,
                'reference' => $quote->deposit_reference,
            ] : null,
            'is_editable' => $quote->isEditable(),
            'sent_at' => $quote->sent_at?->toIso8601String(),
            'decided_at' => $quote->decided_at?->toIso8601String(),
            'source_number' => $quote->source?->quote_number,
            'author' => $quote->user?->full_name,
            'items' => $quote->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'kind' => $item->kind,
                'designation' => $item->designation,
                'unit_name' => $item->unit_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ])->values(),
            'whatsapp_available' => $this->documents->whatsappAvailable($quote),
        ];
    }
}
