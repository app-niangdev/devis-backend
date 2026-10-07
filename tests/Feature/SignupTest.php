<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class SignupTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();
        $this->fakeWaha();
    }

    private function register(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/register', $overrides + [
            'first_name' => 'Moussa',
            'last_name' => 'Diop',
            'company_name' => 'Menuiserie Diop',
            'trade' => 'Menuisier',
            'phone' => '77 555 44 33',
            'password' => 'Secret2026',
            'password_confirmation' => 'Secret2026',
        ]);
    }

    /** Inscription complète jusqu'à la confirmation du numéro. */
    private function registerAndConfirm(): Tenant
    {
        $token = $this->register()->assertCreated()->json('payload.challenge_token');
        $this->postJson('/api/auth/otp/verify', ['challenge_token' => $token, 'code' => $this->lastOtp()])->assertOk();

        return Tenant::where('name', 'Menuiserie Diop')->firstOrFail();
    }

    private function login(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/login', ['phone' => '775554433', 'password' => 'Secret2026']);
    }

    private function sentTexts(): array
    {
        return Http::recorded(fn ($request) => str_ends_with($request->url(), '/api/sendText'))
            ->map(fn ($pair) => $pair[0]['text'])
            ->values()
            ->all();
    }

    public function test_registration_creates_a_pending_company_and_sends_a_code(): void
    {
        $this->register()
            ->assertCreated()
            ->assertJsonPath('payload.step', 'otp_required')
            ->assertJsonPath('payload.purpose', 'signup');

        $tenant = Tenant::where('name', 'Menuiserie Diop')->firstOrFail();
        $user = User::where('phone_one', '775554433')->firstOrFail();

        $this->assertSame(Tenant::PENDING, $tenant->approval_status);
        $this->assertSame('Menuisier', $tenant->trade);
        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertTrue($user->isManager());
        $this->assertNull($user->phone_verified_at);
        $this->assertFalse($user->must_change_password);
        // L'essai ne démarre qu'à l'activation
        $this->assertSame(0, Subscription::where('tenant_id', $tenant->id)->count());
        $this->assertStringContainsString('inscription', $this->sentTexts()[0]);
    }

    public function test_confirmed_number_waits_for_the_administrator(): void
    {
        $token = $this->register()->json('payload.challenge_token');

        $this->postJson('/api/auth/otp/verify', ['challenge_token' => $token, 'code' => $this->lastOtp()])
            ->assertOk()
            ->assertJsonPath('payload.step', 'pending_approval')
            ->assertJsonMissingPath('payload.access_token');

        $this->assertNotNull(User::where('phone_one', '775554433')->value('phone_verified_at'));

        $this->login()->assertStatus(403)->assertJsonPath('error_code', 'ACCOUNT_PENDING');
    }

    public function test_login_before_confirmation_sends_a_new_signup_code(): void
    {
        $this->register()->assertCreated();

        $this->login()
            ->assertOk()
            ->assertJsonPath('payload.step', 'otp_required')
            ->assertJsonPath('payload.purpose', 'signup');
    }

    public function test_abandoned_signup_can_start_over_but_a_used_number_cannot(): void
    {
        $this->register()->assertCreated();
        // Code jamais saisi : la nouvelle inscription remplace l'ancienne
        $token = $this->register(['company_name' => 'Atelier Diop'])->assertCreated()->json('payload.challenge_token');
        $this->assertSame(['Atelier Diop'], Tenant::pluck('name')->all());

        $this->postJson('/api/auth/otp/verify', ['challenge_token' => $token, 'code' => $this->lastOtp()])->assertOk();

        $this->register()
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'Une demande d\'inscription est déjà en cours de validation pour ce numéro.');

        $this->manager($this->tenant(), ['phone_one' => '781112233']);
        $this->register(['phone' => '781112233'])
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'Ce numéro est déjà inscrit. Connectez-vous ou utilisez « Mot de passe oublié ».');
    }

    public function test_administrator_approves_and_the_trial_starts_today(): void
    {
        $tenant = $this->registerAndConfirm();

        $this->asUser($this->admin())
            ->putJson("/api/tenants/approve/{$tenant->id}")
            ->assertOk()
            ->assertJsonPath('payload.approval_status', Tenant::APPROVED)
            ->assertJsonPath('payload.subscription.state', 'active');

        $trial = Subscription::where('tenant_id', $tenant->id)->sole();
        $this->assertSame(today()->toDateString(), $trial->starts_at->toDateString());
        $this->assertStringContainsString('est activé', last($this->sentTexts()));

        $this->login()->assertOk()->assertJsonPath('payload.step', 'authenticated');
    }

    public function test_unconfirmed_signup_cannot_be_approved(): void
    {
        $this->register()->assertCreated();
        $tenant = Tenant::sole();

        $this->asUser($this->admin())
            ->putJson("/api/tenants/approve/{$tenant->id}")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'PHONE_NOT_VERIFIED');
    }

    public function test_administrator_rejects_with_a_reason(): void
    {
        $tenant = $this->registerAndConfirm();
        $admin = $this->admin();

        $this->asUser($admin)
            ->putJson("/api/tenants/reject/{$tenant->id}", ['reason' => 'Numéro déjà client sous un autre nom.'])
            ->assertOk()
            ->assertJsonPath('payload.approval_status', Tenant::REJECTED);

        $this->assertStringContainsString('Motif : Numéro déjà client', last($this->sentTexts()));
        $this->login()->assertStatus(403)->assertJsonPath('error_code', 'ACCOUNT_REJECTED');

        // Décision déjà prise
        $this->asUser($admin)->putJson("/api/tenants/approve/{$tenant->id}")->assertStatus(422);
    }

    public function test_pending_signups_are_listed_and_counted_for_the_administrator(): void
    {
        $this->registerAndConfirm();
        $this->tenant(['name' => 'Déjà cliente']);
        $admin = $this->admin();

        $this->asUser($admin)
            ->getJson('/api/tenants/list?approval_status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'payload')
            ->assertJsonPath('payload.0.name', 'Menuiserie Diop')
            ->assertJsonPath('payload.0.managers.0.phone_verified', true);

        $this->asUser($admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('payload.pending_signups', 1)
            ->assertJsonPath('payload.tenants.total', 1);
    }

    public function test_only_the_administrator_can_review_signups(): void
    {
        $tenant = $this->registerAndConfirm();
        $manager = $this->manager($this->tenant());

        $this->asUser($manager)->putJson("/api/tenants/approve/{$tenant->id}")->assertStatus(403);
    }
}
