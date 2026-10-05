<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();
    }

    public function test_phone_is_normalized_and_unique_per_tenant(): void
    {
        $manager = $this->manager($this->tenant());

        $this->asUser($manager)
            ->postJson('/api/manager/customers', ['name' => 'Awa Diop', 'phone' => '+221 76-555-44-33'])
            ->assertCreated()
            ->assertJsonPath('payload.phone', '765554433')
            ->assertJsonPath('payload.phone_display', '76 555 44 33');

        $this->asUser($manager)
            ->postJson('/api/manager/customers', ['name' => 'Doublon', 'phone' => '765554433'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        $this->asUser($manager)
            ->postJson('/api/manager/customers', ['name' => 'Fixe', 'phone' => '338001122'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        // Une autre entreprise peut avoir le même client
        $this->asUser($this->manager($this->tenant()))
            ->postJson('/api/manager/customers', ['name' => 'Awa Diop', 'phone' => '765554433'])
            ->assertCreated();
    }

    public function test_crud_search_and_isolation(): void
    {
        $manager = $this->manager($this->tenant());
        $api = $this->asUser($manager);

        $id = $api->postJson('/api/manager/customers', ['name' => 'Awa Diop', 'phone' => '765554433'])->json('payload.id');
        $api->postJson('/api/manager/customers', ['name' => 'Ibou Fall', 'phone' => '780001122']);

        $api->getJson('/api/manager/customers?search=awa')->assertJsonPath('meta.total', 1);
        $api->getJson('/api/manager/customers?search=78 000')->assertJsonPath('payload.0.name', 'Ibou Fall');

        $api->putJson("/api/manager/customers/{$id}", ['name' => 'Awa Ndiaye', 'phone' => '765554433', 'address' => 'Thiès'])
            ->assertOk()
            ->assertJsonPath('payload.address', 'Thiès');

        $this->asUser($this->manager($this->tenant()))->getJson("/api/manager/customers/{$id}")->assertNotFound();

        $api = $this->asUser($manager);
        $api->deleteJson("/api/manager/customers/{$id}")->assertOk();
        $this->assertSoftDeleted('customers', ['id' => $id]);

        // Le numéro d'un client supprimé peut être réutilisé
        $api->postJson('/api/manager/customers', ['name' => 'Awa', 'phone' => '765554433'])->assertCreated();
    }
}
