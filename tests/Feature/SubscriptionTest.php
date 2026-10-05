<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();
    }

    public function test_new_tenant_receives_a_trial(): void
    {
        $id = $this->asUser($this->admin())
            ->postJson('/api/v1/tenants/add', ['name' => 'Sow Électricité', 'code_website' => 'sow', 'primary_color' => '#dc2626'])
            ->assertCreated()
            ->assertJsonPath('payload.primary_color', '#DC2626')
            ->assertJsonPath('payload.secondary_color', '#0F172A')
            ->json('payload.id');

        $this->asUser($this->admin())
            ->getJson("/api/v1/subscriptions/tenant/{$id}")
            ->assertJsonPath('payload.status.state', 'active')
            ->assertJsonPath('payload.status.plan', 'Essai')
            ->assertJsonPath('payload.status.ends_at', today()->addDays(29)->toDateString());
    }

    public function test_overlaps_are_refused_and_renewals_are_chained(): void
    {
        $tenant = $this->tenant();
        $admin = $this->admin();
        $current = Subscription::where('tenant_id', $tenant->id)->first();

        $this->asUser($admin)->postJson('/api/v1/subscriptions/add', [
            'tenant_id' => $tenant->id, 'plan' => 'Mensuel', 'amount' => 5000,
            'starts_at' => $current->ends_at->toDateString(), 'ends_at' => $current->ends_at->addMonth()->toDateString(),
        ])->assertStatus(422)->assertJsonPath('error_code', 'SUBSCRIPTION_OVERLAP');

        $this->asUser($admin)->postJson('/api/v1/subscriptions/add', [
            'tenant_id' => $tenant->id, 'plan' => 'Mensuel', 'amount' => 5000,
            'starts_at' => $current->ends_at->addDay()->toDateString(), 'ends_at' => $current->ends_at->addDays(30)->toDateString(),
        ])->assertCreated();

        $status = app(SubscriptionService::class)->statusForTenant($tenant->fresh());
        $this->assertSame($current->ends_at->addDays(30)->toDateString(), $status['ends_at']);
    }

    public function test_states(): void
    {
        $service = app(SubscriptionService::class);
        $tenant = $this->tenant();
        $subscription = Subscription::where('tenant_id', $tenant->id)->first();

        $this->assertSame('active', $service->statusForTenant($tenant->fresh())['state']);

        $subscription->update(['ends_at' => today()->addDays(3)]);
        $this->assertSame('expiring', $service->statusForTenant($tenant->fresh())['state']);

        $subscription->update(['ends_at' => today()->subDay()]);
        $this->assertSame('expired', $service->statusForTenant($tenant->fresh())['state']);

        $subscription->delete();
        $this->assertSame('none', $service->statusForTenant($tenant->fresh())['state']);
    }

    public function test_disabled_tenant_blocks_manager(): void
    {
        $tenant = $this->tenant();
        $manager = $this->manager($tenant);
        $tenant->update(['state' => false]);

        $this->postJson('/api/v1/auth/login', ['phone' => $manager->phone_one, 'password' => 'Secret2026'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'TENANT_DISABLED');
    }

    public function test_manager_cannot_manage_subscriptions(): void
    {
        $this->asUser($this->manager($this->tenant()))
            ->getJson('/api/v1/subscriptions/overview')
            ->assertStatus(403);
    }

    public function test_admin_dashboard(): void
    {
        $this->manager($this->tenant());
        $expired = $this->tenant();
        Subscription::where('tenant_id', $expired->id)->update(['starts_at' => today()->subDays(40), 'ends_at' => today()->subDay()]);

        $this->asUser($this->admin())
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('payload.tenants.total', 2)
            ->assertJsonPath('payload.managers', 1)
            ->assertJsonPath('payload.subscriptions.active', 1)
            ->assertJsonPath('payload.subscriptions.expired', 1)
            ->assertJsonPath('payload.attention.0.tenant_id', $expired->id);
    }
}
