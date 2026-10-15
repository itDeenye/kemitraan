<?php

namespace Tests\Feature;

use Tests\TestCase;

class VersionPageTest extends TestCase
{
    public function test_version_page_is_publicly_accessible(): void
    {
        $this->withoutVite()
            ->get('/version')
            ->assertOk()
            ->assertViewIs('version');
    }
}
