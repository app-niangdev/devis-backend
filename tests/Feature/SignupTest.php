<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
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

    private function register(array $overrides = []): TestResponse
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

    private function confirm(string $challengeToken): TestResponse
    {
        return $this->postJson('/api/auth/otp/verify', ['challenge_token' => $challengeToken, 'code' => $this->lastOtp()]);
    }

    private function login(): TestResponse
    {
        return $this->postJson('/api/auth/login', ['phone' => '775554433', 'password' => 'Secret2026']);
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
        // L'essai ne démarre qu'avec la confirmation du numéro
        $this->assertSame(0, Subscription::where('tenant_id', $tenant->id)->count());
    }

    public function test_confirmed_code_activates_the_account_and_signs_in(): void
    {
        $token = $this->register()->json('payload.challenge_token');

        $this->confirm($token)
            ->assertOk()
            ->assertJsonPath('payload.step', 'authenticated')
            ->assertJsonPath('payload.user.subscription.state', 'active')
            ->assertJsonStructure(['payload' => ['access_token', 'refresh_token']]);

        $tenant = Tenant::sole();
        $this->assertSame(Tenant::APPROVED, $tenant->approval_status);
        $this->assertSame(today()->toDateString(), Subscription::where('tenant_id', $tenant->id)->sole()->starts_at->toDateString());

        $this->login()->assertOk()->assertJsonPath('payload.step', 'authenticated');
    }

    public function test_login_before_confirmation_sends_a_new_signup_code(): void
    {
        $this->register()->assertCreated();

        $token = $this->login()
            ->assertOk()
            ->assertJsonPath('payload.step', 'otp_required')
            ->assertJsonPath('payload.purpose', 'signup')
            ->json('payload.challenge_token');

        $this->confirm($token)->assertOk()->assertJsonPath('payload.step', 'authenticated');
    }

    public function test_signup_confirmed_under_the_old_flow_is_activated_at_login(): void
    {
        $this->confirm($this->register()->json('payload.challenge_token'));
        // Numéro confirmé mais entreprise restée en attente (validation par l'administrateur, avant cette version)
        Tenant::sole()->update(['approval_status' => Tenant::PENDING]);
        Subscription::query()->delete();

        $this->login()->assertOk()->assertJsonPath('payload.step', 'authenticated');
        $this->assertSame(Tenant::APPROVED, Tenant::sole()->approval_status);
        $this->assertSame(1, Subscription::count());
    }

    public function test_abandoned_signup_can_start_over_but_a_used_number_cannot(): void
    {
        $this->register()->assertCreated();
        // Code jamais saisi : la nouvelle inscription remplace l'ancienne
        $token = $this->register(['company_name' => 'Atelier Diop'])->assertCreated()->json('payload.challenge_token');
        $this->assertSame(['Atelier Diop'], Tenant::pluck('name')->all());

        $this->confirm($token)->assertOk();

        $this->register()
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'Ce numéro est déjà inscrit. Connectez-vous ou utilisez « Mot de passe oublié ».');
    }

    public function test_unconfirmed_signups_stay_out_of_the_administrator_statistics(): void
    {
        $this->register()->assertCreated();
        $this->tenant(['name' => 'Déjà cliente']);
        $admin = $this->admin();

        $this->asUser($admin)
            ->getJson('/api/tenants/list?approval_status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'payload')
            ->assertJsonPath('payload.0.managers.0.phone_verified', false);

        $this->asUser($admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('payload.tenants.total', 1);

        Http::assertSent(fn ($request) => str_contains($request['text'] ?? '', 'inscription'));
    }
}
