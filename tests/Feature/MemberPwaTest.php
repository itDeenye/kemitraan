<?php

namespace Tests\Feature;

use Tests\TestCase;

class MemberPwaTest extends TestCase
{
    public function test_member_app_exposes_android_install_metadata(): void
    {
        $this->get('/member/login')
            ->assertOk()
            ->assertSee('/manifest.webmanifest', false)
            ->assertSee('/pwa/icon-192.png', false)
            ->assertSee('#A01526', false);

        $manifest = json_decode(
            file_get_contents(public_path('manifest.webmanifest')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('DNY Skincare Kemitraan', $manifest['name']);
        $this->assertSame('/member/login', $manifest['start_url']);
        $this->assertSame('/member/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertFileExists(public_path('pwa/icon-192.png'));
        $this->assertFileExists(public_path('pwa/icon-512.png'));
        $this->assertFileExists(public_path('service-worker.js'));
        $this->assertStringContainsString(
            'const CACHE_NAME = `${CACHE_PREFIX}v2`;',
            file_get_contents(public_path('service-worker.js')),
        );
        $this->assertStringContainsString(
            "! contentType.includes('text/html')",
            file_get_contents(public_path('service-worker.js')),
        );
    }
}
