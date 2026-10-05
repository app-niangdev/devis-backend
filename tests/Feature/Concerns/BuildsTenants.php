<?php

namespace Tests\Feature\Concerns;

use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Support\Facades\Http;

trait BuildsTenants
{
    protected function setUpRoles(): void
    {
        Role::firstOrCreate(['name' => 'ADMIN'], ['label' => 'Administrateur']);
        Role::firstOrCreate(['name' => 'MANAGER'], ['label' => 'Gestionnaire']);
    }

    /** Entreprise couverte par un abonnement en cours. */
    protected function tenant(array $attributes = []): Tenant
    {
        $tenant = Tenant::create($attributes + [
            'name' => 'Entreprise ' . uniqid(),
            'code_website' => 'code-' . uniqid(),
        ])->fresh();

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan' => 'Mensuel',
            'amount' => 5000,
            'currency' => 'XOF',
            'starts_at' => today()->subDays(10),
            'ends_at' => today()->addDays(20),
        ]);

        return $tenant;
    }

    protected function manager(Tenant $tenant, array $attributes = []): User
    {
        return User::factory()->create($attributes + ['tenant_id' => $tenant->id]);
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function tokenFor(User $user): string
    {
        return app(TokenService::class)->issue($user->fresh())['access_token'];
    }

    protected function asUser(User $user): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($user));
    }

    /** WAHA simulé : les messages envoyés sont enregistrés par Http::fake. */
    protected function fakeWaha(): void
    {
        config([
            'services.waha.base_url' => 'https://waha.test',
            'services.waha.api_key' => 'test-key',
        ]);

        Http::fake([
            'waha.test/api/contacts/check-exists*' => Http::response(['numberExists' => true]),
            'waha.test/*' => Http::response(['id' => 'msg'], 201),
        ]);
    }

    /** Dernier code OTP envoyé sur WhatsApp. */
    protected function lastOtp(): string
    {
        $sent = Http::recorded(fn ($request) => str_ends_with($request->url(), '/api/sendText'))->last();
        $this->assertNotNull($sent, 'Aucun code envoyé sur WhatsApp.');
        preg_match('/\*(\d{6})\*/', $sent[0]['text'], $matches);

        return $matches[1];
    }
}
