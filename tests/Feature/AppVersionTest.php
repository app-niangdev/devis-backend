<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppVersionTest extends TestCase
{
    public function test_versions_are_public(): void
    {
        config([
            'mobile.latest_version' => '1.2.0',
            'mobile.min_version' => '1.1.0',
            'mobile.android_url' => 'https://app-devis.niangdev.com/devis.apk',
        ]);

        $this->getJson('/api/app-version')
            ->assertOk()
            ->assertJsonPath('payload.latest_version', '1.2.0')
            ->assertJsonPath('payload.min_version', '1.1.0')
            ->assertJsonPath('payload.android_url', 'https://app-devis.niangdev.com/devis.apk');
    }
}
