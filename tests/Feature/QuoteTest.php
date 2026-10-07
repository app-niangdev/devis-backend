<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();
        $this->tenant = $this->tenant(['default_deposit_type' => 'percent', 'default_deposit_value' => 30, 'quote_validity_days' => 15]);
        $this->manager = $this->manager($this->tenant);
        $this->customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Awa Diop', 'phone' => '765554433']);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'customer' => ['mode' => 'existing', 'id' => $this->customer->id],
            'title' => 'Salle de bain',
            'items' => [
                ['kind' => 'supply', 'designation' => 'Carrelage', 'unit_name' => 'm²', 'quantity' => 12.5, 'unit_price' => 7500],
                ['kind' => 'labor', 'designation' => 'Pose', 'unit_name' => 'forfait', 'quantity' => 1, 'unit_price' => 60000],
            ],
            'discount' => 3750,
        ];
    }

    private function createQuote(array $overrides = []): array
    {
        return $this->asUser($this->manager)
            ->postJson('/api/manager/quotes', $this->payload($overrides))
            ->assertCreated()
            ->json('payload');
    }

    public function test_totals_and_default_deposit_are_computed(): void
    {
        $quote = $this->createQuote();

        $this->assertSame('DEV-' . now()->format('Y') . '-0001', $quote['quote_number']);
        $this->assertSame(93750, $quote['supplies_amount']);
        $this->assertSame(60000, $quote['labor_amount']);
        $this->assertSame(153750, $quote['gross_amount']);
        $this->assertSame(150000, $quote['total_amount']);
        $this->assertSame('percent', $quote['deposit_type']);
        $this->assertSame(45000, $quote['deposit_amount']);
        $this->assertSame(105000, $quote['balance_amount']);
        $this->assertSame(today()->addDays(15)->toDateString(), $quote['valid_until']);
    }

    public function test_numbers_follow_each_other_per_tenant(): void
    {
        $this->createQuote();
        $second = $this->createQuote();
        $this->assertStringEndsWith('-0002', $second['quote_number']);

        $other = $this->tenant();
        $otherManager = $this->manager($other);
        $otherCustomer = Customer::create(['tenant_id' => $other->id, 'name' => 'X', 'phone' => '771112233']);

        $first = $this->asUser($otherManager)
            ->postJson('/api/manager/quotes', $this->payload(['customer' => ['mode' => 'existing', 'id' => $otherCustomer->id]]))
            ->json('payload.quote_number');
        $this->assertStringEndsWith('-0001', $first);
    }

    public function test_deposit_rules(): void
    {
        $this->asUser($this->manager)
            ->postJson('/api/manager/quotes', $this->payload(['deposit_type' => 'amount', 'deposit_value' => 200000]))
            ->assertStatus(422);

        $this->asUser($this->manager)
            ->postJson('/api/manager/quotes', $this->payload(['deposit_type' => 'percent', 'deposit_value' => 120]))
            ->assertStatus(422);

        $quote = $this->createQuote(['deposit_type' => 'amount', 'deposit_value' => 50000]);
        $this->assertSame(50000, $quote['deposit_amount']);

        $none = $this->createQuote(['deposit_type' => 'none']);
        $this->assertSame(0, $none['deposit_amount']);
        $this->assertSame('none', $none['deposit_status']);
    }

    public function test_discount_cannot_exceed_subtotal(): void
    {
        $this->asUser($this->manager)
            ->postJson('/api/manager/quotes', $this->payload(['discount' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('discount');
    }

    public function test_new_customer_inline_reuses_known_phone(): void
    {
        $quote = $this->createQuote(['customer' => ['mode' => 'new', 'name' => 'Autre nom', 'phone' => '76 555 44 33']]);

        $this->assertSame($this->customer->id, $quote['customer']['id']);
        $this->assertSame(1, Customer::count());
    }

    public function test_lifecycle_and_deposit_tracking(): void
    {
        $id = $this->createQuote()['id'];
        $api = $this->asUser($this->manager);

        $api->postJson("/api/manager/quotes/{$id}/decision", ['decision' => 'accepted'])->assertStatus(422);
        $api->putJson("/api/manager/quotes/{$id}/deposit", ['amount' => 45000, 'received_at' => today()->toDateString(), 'payment_method' => 'wave'])->assertStatus(422);

        $api->postJson("/api/manager/quotes/{$id}/mark-sent")->assertOk()->assertJsonPath('payload.status', 'sent');
        $api->postJson("/api/manager/quotes/{$id}/decision", ['decision' => 'accepted'])->assertOk()->assertJsonPath('payload.is_editable', false);
        $api->postJson("/api/manager/quotes/{$id}/decision", ['decision' => 'refused'])->assertStatus(422);

        $api->putJson("/api/manager/quotes/{$id}", $this->payload())->assertStatus(422);
        $api->deleteJson("/api/manager/quotes/{$id}")->assertStatus(422);

        $api->putJson("/api/manager/quotes/{$id}/deposit", ['amount' => 20000, 'received_at' => today()->toDateString(), 'payment_method' => 'orange_money'])
            ->assertOk()
            ->assertJsonPath('payload.deposit_status', 'partial');
        $api->putJson("/api/manager/quotes/{$id}/deposit", ['amount' => 45000, 'received_at' => today()->toDateString(), 'payment_method' => 'cash'])
            ->assertOk()
            ->assertJsonPath('payload.deposit_status', 'received');
        $api->putJson("/api/manager/quotes/{$id}/deposit", ['amount' => 45000, 'received_at' => today()->addDay()->toDateString(), 'payment_method' => 'cash'])
            ->assertStatus(422);
        $api->deleteJson("/api/manager/quotes/{$id}/deposit")->assertOk()->assertJsonPath('payload.deposit_status', 'pending');

        $copy = $api->postJson("/api/manager/quotes/{$id}/duplicate")->assertCreated()->json('payload');
        $this->assertSame('draft', $copy['status']);
        $this->assertNull($copy['deposit_received']);
        $this->assertCount(2, $copy['items']);
    }

    public function test_draft_can_be_updated_and_deleted(): void
    {
        $id = $this->createQuote()['id'];
        $api = $this->asUser($this->manager);

        $api->putJson("/api/manager/quotes/{$id}", $this->payload([
            'items' => [['kind' => 'labor', 'designation' => 'Réparation', 'quantity' => 2, 'unit_price' => 10000]],
            'discount' => 0,
        ]))->assertOk()->assertJsonPath('payload.total_amount', 20000)->assertJsonPath('payload.deposit_amount', 6000);

        $api->deleteJson("/api/manager/quotes/{$id}")->assertOk();
        $this->assertSoftDeleted('quotes', ['id' => $id]);
    }

    public function test_expired_filter(): void
    {
        $this->createQuote(['valid_until' => today()->subDay()->toDateString()]);
        $this->createQuote();

        $this->asUser($this->manager)
            ->getJson('/api/manager/quotes?status=expired')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('payload.0.is_expired', true);
    }

    public function test_pdf_is_generated(): void
    {
        $id = $this->createQuote()['id'];

        $response = $this->asUser($this->manager)->get("/api/manager/quotes/{$id}/pdf");

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_pdf_is_generated_with_stamp(): void
    {
        $this->tenant->update(['stamp_enabled' => true]);
        $id = $this->createQuote()['id'];

        $response = $this->asUser($this->manager)->get("/api/manager/quotes/{$id}/pdf");

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_quotes_are_isolated_between_tenants(): void
    {
        $id = $this->createQuote()['id'];
        $intruder = $this->manager($this->tenant());

        $this->asUser($intruder)->getJson("/api/manager/quotes/{$id}")->assertNotFound();
        $this->asUser($intruder)->getJson('/api/manager/quotes')->assertJsonPath('meta.total', 0);
        $this->asUser($intruder)
            ->postJson('/api/manager/quotes', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('customer.id');
    }

    public function test_dashboard(): void
    {
        $id = $this->createQuote()['id'];
        $api = $this->asUser($this->manager);
        $api->postJson("/api/manager/quotes/{$id}/mark-sent");
        $api->postJson("/api/manager/quotes/{$id}/decision", ['decision' => 'accepted']);
        $this->createQuote();

        $api->getJson('/api/manager/dashboard')
            ->assertOk()
            ->assertJsonPath('payload.quotes.accepted.count', 1)
            ->assertJsonPath('payload.quotes.draft.count', 1)
            ->assertJsonPath('payload.acceptance_rate', 100)
            ->assertJsonPath('payload.deposits.expected', 45000)
            ->assertJsonPath('payload.deposits.pending_count', 1);
    }

    public function test_dashboard_counts_quotes_awaiting_answer(): void
    {
        $api = $this->asUser($this->manager);
        $valid = $this->createQuote()['id'];
        $old = $this->createQuote()['id'];
        $api->postJson("/api/manager/quotes/{$valid}/mark-sent")->assertOk();
        $api->postJson("/api/manager/quotes/{$old}/mark-sent")->assertOk();
        Quote::whereKey($old)->update(['valid_until' => today()->subDay()]);

        $api->getJson('/api/manager/dashboard')
            ->assertOk()
            ->assertJsonPath('payload.quotes.sent.count', 2)
            ->assertJsonPath('payload.quotes.sent_expired.count', 1)
            ->assertJsonPath('payload.quotes.expired.count', 1)
            ->assertJsonCount(2, 'payload.awaiting_answer');
    }

    public function test_units_are_ranked_by_use_for_the_tenant(): void
    {
        $line = fn (string $unit, string $kind = 'supply') => ['kind' => $kind, 'designation' => 'X', 'unit_name' => $unit, 'quantity' => 1, 'unit_price' => 100];

        $this->createQuote(['discount' => 0, 'items' => [$line('sac'), $line('Kg'), $line('jour', 'labor')]]);
        $this->createQuote(['discount' => 0, 'items' => [$line('sac'), $line('kg'), $line('kg')]]);
        $this->createQuote(['discount' => 0, 'items' => [$line('sac'), $line('litre')]]);

        // Une autre entreprise n'influence pas les suggestions
        $other = $this->tenant();
        $otherCustomer = Customer::create(['tenant_id' => $other->id, 'name' => 'X', 'phone' => '771112233']);
        $this->asUser($this->manager($other))->postJson('/api/manager/quotes', $this->payload([
            'customer' => ['mode' => 'existing', 'id' => $otherCustomer->id],
            'discount' => 0,
            'items' => [$line('paire'), $line('paire'), $line('paire'), $line('paire')],
        ]))->assertCreated();

        $units = $this->asUser($this->manager)->getJson('/api/manager/quotes/units')->assertOk()->json('payload');

        $this->assertSame([
            ['unit' => 'sac', 'uses' => 3],
            ['unit' => 'kg', 'uses' => 3],
            ['unit' => 'litre', 'uses' => 1],
        ], $units);
    }

    public function test_dashboard_can_be_filtered_by_year_month_or_date(): void
    {
        $at = fn (string $date) => Quote::whereKey($this->createQuote(['discount' => 0])['id'])->update(['created_at' => $date]);
        $at('2024-03-10 09:00:00');
        $at('2025-06-02 10:00:00');
        $at('2025-06-20 15:00:00');
        $at('2025-11-05 08:00:00');

        $dashboard = fn (string $query = '') => $this->asUser($this->manager)->getJson('/api/manager/dashboard' . $query)->assertOk();

        // Sans filtre : tout l'historique, et les années depuis le premier devis
        $dashboard()
            ->assertJsonPath('payload.quotes.draft.count', 4)
            ->assertJsonPath('payload.period', null)
            ->assertJsonPath('payload.years', range((int) now()->year, 2024));

        $dashboard('?year=2025')
            ->assertJsonPath('payload.quotes.draft.count', 3)
            ->assertJsonPath('payload.period', ['from' => '2025-01-01', 'to' => '2025-12-31']);
        $dashboard('?year=2025&month=6')
            ->assertJsonPath('payload.quotes.draft.count', 2)
            ->assertJsonPath('payload.month.created', 2);
        $dashboard('?date=2025-06-20')->assertJsonPath('payload.quotes.draft.count', 1);

        // Mois hors bornes
        $this->asUser($this->manager)->getJson('/api/manager/dashboard?month=13')->assertStatus(422);
    }
}
