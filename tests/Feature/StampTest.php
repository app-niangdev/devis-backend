<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class StampTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();
    }

    public function test_preview_is_a_png_in_the_requested_color(): void
    {
        $tenant = $this->tenant(['name' => 'Camara & Fils', 'trade' => 'Électricien bâtiment', 'phone_call' => '777777687']);

        $response = $this->asUser($this->manager($tenant))
            ->getJson('/api/manager/company/stamp?color=%23b91c1c')
            ->assertOk()
            ->assertJsonPath('payload.enabled', false)
            ->assertJsonPath('payload.color', '#B91C1C');

        $png = base64_decode($response->json('payload.image'));
        $this->assertSame("\x89PNG", substr($png, 0, 4));
        $this->assertSame([600, 600], array_slice(getimagesizefromstring($png), 0, 2));
    }

    public function test_stamp_is_enabled_with_its_color(): void
    {
        $tenant = $this->tenant();
        $api = $this->asUser($this->manager($tenant));

        $api->putJson('/api/manager/company/stamp', ['enabled' => true, 'color' => '#047857'])
            ->assertOk()
            ->assertJsonPath('payload.enabled', true)
            ->assertJsonPath('payload.color', '#047857');

        $api->getJson('/api/manager/company')
            ->assertJsonPath('payload.stamp_enabled', true)
            ->assertJsonPath('payload.stamp_color', '#047857');

        // Désactivation : la couleur choisie est conservée
        $api->putJson('/api/manager/company/stamp', ['enabled' => false])
            ->assertJsonPath('payload.enabled', false)
            ->assertJsonPath('payload.color', '#047857');
    }

    public function test_invalid_color_is_rejected(): void
    {
        $this->asUser($this->manager($this->tenant()))
            ->putJson('/api/manager/company/stamp', ['enabled' => true, 'color' => 'bleu'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('color');
    }
}
