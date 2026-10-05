<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Devis : création, modification, copie, changements de statut et acompte.
 * Montants en FCFA entiers, sans TVA. Les produits du catalogue ne sont que lus
 * (pour pré-remplir les lignes).
 *
 * @phpstan-type Line array{kind?: ?string, product_id?: ?int, designation?: ?string, unit_name?: ?string, quantity: float|int|string, unit_price: int|string}
 */
class QuoteService
{
    /**
     * @param array{customer: array, items: list<Line>, title?: ?string, discount?: ?int, deposit_type?: ?string,
     *              deposit_value?: ?int, valid_until?: ?string, notes?: ?string} $data
     */
    public function create(Tenant $tenant, User $user, array $data): Quote
    {
        return DB::transaction(function () use ($tenant, $user, $data) {
            $quote = new Quote([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'status' => Quote::DRAFT,
                // Acompte habituel de l'entreprise, sauf choix contraire sur ce devis
                'deposit_type' => $tenant->default_deposit_type ?: Quote::DEPOSIT_NONE,
                'deposit_value' => (int) $tenant->default_deposit_value,
            ]);
            $quote->quote_number = $this->nextNumber($tenant->id);
            $data['valid_until'] ??= now()->addDays($tenant->quote_validity_days ?: 30)->toDateString();

            return $this->fill($quote, $data);
        });
    }

    /** Modification du contenu : seulement tant que le devis est en brouillon ou envoyé. */
    public function update(Quote $quote, array $data): Quote
    {
        $this->ensureEditable($quote);

        return DB::transaction(fn () => $this->fill($quote, $data));
    }

    /** Copie en brouillon, avec un nouveau numéro et une nouvelle validité. */
    public function duplicate(Quote $source, User $user): Quote
    {
        return DB::transaction(function () use ($source, $user) {
            $source->loadMissing(['tenant', 'items']);
            $copy = $source->replicate([
                'quote_number', 'status', 'sent_at', 'decided_at', 'created_at', 'updated_at', 'deleted_at',
                'deposit_received_amount', 'deposit_received_at', 'deposit_payment_method', 'deposit_reference',
            ]);
            $copy->fill([
                'user_id' => $user->id,
                'source_quote_id' => $source->id,
                'status' => Quote::DRAFT,
                'valid_until' => now()->addDays($source->tenant->quote_validity_days ?: 30)->toDateString(),
            ]);
            $copy->quote_number = $this->nextNumber($source->tenant_id);
            $copy->save();

            foreach ($source->items as $item) {
                $copy->items()->create($item->only([
                    'product_id', 'kind', 'designation', 'unit_name', 'quantity', 'unit_price', 'subtotal', 'position',
                ]));
            }

            return $copy->load('items');
        });
    }

    /** Envoi au client (WhatsApp ou autre moyen) : un brouillon passe à « envoyé ». */
    public function markSent(Quote $quote): Quote
    {
        $quote->sent_at = now();
        if ($quote->status === Quote::DRAFT) {
            $quote->status = Quote::SENT;
        }
        $quote->save();

        return $quote;
    }

    /** Réponse du client, enregistrée par le gestionnaire : définitive. */
    public function decide(Quote $quote, string $decision): Quote
    {
        if ($quote->status !== Quote::SENT) {
            $this->fail('status', $quote->status === Quote::DRAFT
                ? 'Envoyez d\'abord le devis au client avant d\'enregistrer sa réponse.'
                : 'La réponse du client est déjà enregistrée pour ce devis.');
        }

        $quote->update(['status' => $decision, 'decided_at' => now()]);

        return $quote;
    }

    /**
     * Acompte encaissé sur un devis accepté (une seule saisie, corrigeable).
     *
     * @param array{amount: int, received_at: string, payment_method: string, reference?: ?string} $data
     */
    public function recordDeposit(Quote $quote, array $data): Quote
    {
        if ($quote->status !== Quote::ACCEPTED) {
            $this->fail('status', 'L\'acompte s\'enregistre sur un devis accepté par le client.');
        }

        if ($quote->deposit_amount <= 0) {
            $this->fail('deposit_type', 'Ce devis ne prévoit pas d\'acompte.');
        }

        $amount = (int) $data['amount'];
        if ($amount > $quote->total_amount) {
            $this->fail('amount', 'Le montant reçu ne peut pas dépasser le total du devis (' . $this->money($quote->total_amount) . ').');
        }

        $quote->update([
            'deposit_received_amount' => $amount,
            'deposit_received_at' => $data['received_at'],
            'deposit_payment_method' => $data['payment_method'],
            'deposit_reference' => isset($data['reference']) && trim((string) $data['reference']) !== '' ? trim((string) $data['reference']) : null,
        ]);

        return $quote;
    }

    public function cancelDeposit(Quote $quote): Quote
    {
        $quote->update([
            'deposit_received_amount' => null,
            'deposit_received_at' => null,
            'deposit_payment_method' => null,
            'deposit_reference' => null,
        ]);

        return $quote;
    }

    /** Un devis accepté engage le client : il n'est pas supprimable. */
    public function delete(Quote $quote): void
    {
        if ($quote->status === Quote::ACCEPTED) {
            $this->fail('status', 'Un devis accepté ne peut pas être supprimé.');
        }

        $quote->delete();
    }

    public function ensureEditable(Quote $quote): void
    {
        if (!$quote->isEditable()) {
            $this->fail('status', 'Ce devis est ' . ($quote->status === Quote::ACCEPTED ? 'accepté' : 'refusé')
                . ' : il ne peut plus être modifié. Dupliquez-le pour en faire une nouvelle version.');
        }
    }

    private function fill(Quote $quote, array $data): Quote
    {
        $customer = $this->resolveCustomer($quote->tenant_id, $data['customer']);
        $lines = $this->prepareLines($quote->tenant_id, $data['items']);

        $supplies = (int) $lines->where('kind', QuoteItem::SUPPLY)->sum('subtotal');
        $labor = (int) $lines->where('kind', QuoteItem::LABOR)->sum('subtotal');
        $gross = $supplies + $labor;
        $discount = (int) ($data['discount'] ?? 0);
        if ($discount > $gross) {
            $this->fail('discount', 'La remise ne peut pas dépasser le sous-total (' . $this->money($gross) . ').');
        }
        $total = $gross - $discount;

        $depositType = $data['deposit_type'] ?? $quote->deposit_type ?? Quote::DEPOSIT_NONE;
        $depositValue = (int) ($data['deposit_value'] ?? $quote->deposit_value ?? 0);
        [$depositValue, $depositAmount] = $this->deposit($depositType, $depositValue, $total);

        $quote->fill([
            'customer_id' => $customer->id,
            'title' => array_key_exists('title', $data) ? $this->text($data['title'], 255) : $quote->title,
            'valid_until' => array_key_exists('valid_until', $data) ? $data['valid_until'] : $quote->valid_until,
            'notes' => array_key_exists('notes', $data) ? $this->text($data['notes'], 2000) : $quote->notes,
            'supplies_amount' => $supplies,
            'labor_amount' => $labor,
            'gross_amount' => $gross,
            'discount' => $discount,
            'total_amount' => $total,
            'deposit_type' => $depositType,
            'deposit_value' => $depositValue,
            'deposit_amount' => $depositAmount,
        ]);
        $quote->save();

        $quote->items()->delete();
        $lines->each(fn (array $line, int $i) => $quote->items()->create($line + ['position' => $i]));

        return $quote->load('items');
    }

    /**
     * Acompte demandé : pourcentage du total (arrondi au franc) ou montant fixe.
     *
     * @return array{0: int, 1: int} valeur retenue, montant
     */
    private function deposit(string $type, int $value, int $total): array
    {
        return match ($type) {
            Quote::DEPOSIT_PERCENT => $value >= 1 && $value <= 100
                ? [$value, (int) round($total * $value / 100)]
                : $this->fail('deposit_value', 'Le pourcentage d\'acompte doit être compris entre 1 et 100.'),
            Quote::DEPOSIT_AMOUNT => match (true) {
                $value <= 0 => $this->fail('deposit_value', 'Saisissez le montant de l\'acompte.'),
                $value > $total => $this->fail('deposit_value', 'L\'acompte ne peut pas dépasser le total du devis (' . $this->money($total) . ').'),
                default => [$value, $value],
            },
            default => [0, 0],
        };
    }

    /**
     * Lignes du devis : un produit du catalogue (désignation reprise s'il n'y en a pas de saisie)
     * ou une ligne libre. Le prix saisi fait foi.
     *
     * @param list<Line> $lines
     */
    private function prepareLines(int $tenantId, array $lines): Collection
    {
        $productIds = collect($lines)->pluck('product_id')->filter()->unique();
        $products = Product::where('tenant_id', $tenantId)->whereIn('id', $productIds)->get()->keyBy('id');

        return collect($lines)->values()->map(function (array $line, int $i) use ($products) {
            $label = 'Ligne ' . ($i + 1) . ' : ';
            $product = null;

            if (!empty($line['product_id'])) {
                $product = $products->get((int) $line['product_id'])
                    ?? $this->fail("items.$i.product_id", $label . 'produit introuvable dans votre catalogue.');
            }

            $kind = in_array($line['kind'] ?? null, QuoteItem::KINDS, true) ? $line['kind'] : QuoteItem::SUPPLY;

            // Main-d'œuvre : seul le montant est demandé, la désignation est facultative
            $designation = trim((string) ($line['designation'] ?? '')) ?: $product?->name
                ?: ($kind === QuoteItem::LABOR ? "Main-d'œuvre" : null);
            if (!$designation) {
                $this->fail("items.$i.designation", $label . 'saisissez une désignation.');
            }

            $quantity = round((float) $line['quantity'], 3);
            $unitPrice = (int) $line['unit_price'];

            return [
                'product_id' => $product?->id,
                'kind' => $kind,
                'designation' => mb_substr($designation, 0, 255),
                'unit_name' => $this->text($line['unit_name'] ?? null, 30),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => (int) round($quantity * $unitPrice),
            ];
        });
    }

    /** Client existant de l'entreprise, ou nouveau (réutilisé si le téléphone est déjà connu). */
    private function resolveCustomer(int $tenantId, array $customer): Customer
    {
        if (($customer['mode'] ?? null) === 'new') {
            $phone = (string) $customer['phone'];

            return Customer::where('tenant_id', $tenantId)->where('phone', $phone)->first()
                ?? Customer::create([
                    'tenant_id' => $tenantId,
                    'name' => trim((string) $customer['name']),
                    'phone' => $phone,
                ]);
        }

        return Customer::where('tenant_id', $tenantId)->find($customer['id'] ?? 0)
            ?? $this->fail('customer.id', 'Client introuvable.');
    }

    /** DEV-AAAA-NNNN, séquence annuelle propre à l'entreprise (devis supprimés compris). */
    private function nextNumber(int $tenantId): string
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ['quote_number_' . $tenantId]);
        }

        $prefix = 'DEV-' . now()->format('Y') . '-';
        $last = Quote::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('quote_number', 'LIKE', $prefix . '%')
            ->orderByRaw('LENGTH(quote_number) DESC, quote_number DESC')
            ->value('quote_number');

        $sequence = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' FCFA';
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
