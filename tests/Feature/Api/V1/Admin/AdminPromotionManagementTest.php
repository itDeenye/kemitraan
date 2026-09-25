<?php

namespace Tests\Feature\Api\V1\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPromotionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_promotion_endpoints_are_not_available(): void
    {
        $this->getJson('/api/v1/admin/product/promotions')->assertNotFound();
        $this->postJson('/api/v1/admin/product/promotions')->assertNotFound();
        $this->getJson('/api/v1/admin/product/promotions/1')->assertNotFound();
        $this->putJson('/api/v1/admin/product/promotions/1')->assertNotFound();
        $this->deleteJson('/api/v1/admin/product/promotions/1')->assertNotFound();
    }
}
