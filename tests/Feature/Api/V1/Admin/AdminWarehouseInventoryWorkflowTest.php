<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminWarehouseInventoryWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_goods_receive_routes_are_not_available(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $this->getJson('/api/v1/admin/inventory/goods-receipts')->assertNotFound();
        $this->postJson('/api/v1/admin/inventory/goods-receipts')->assertNotFound();
    }

    public function test_stock_opname_routes_are_not_available(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $this->getJson('/api/v1/admin/inventory/stock-opnames')->assertNotFound();
        $this->postJson('/api/v1/admin/inventory/stock-opnames')->assertNotFound();
    }

    public function test_stock_transfer_routes_are_not_available(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $this->getJson('/api/v1/admin/inventory/stock-transfers')->assertNotFound();
        $this->postJson('/api/v1/admin/inventory/stock-transfers')->assertNotFound();
    }

    private function createAdministrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Super Administrator',
            'administrator_group_type' => 'superuser',
            'administrator_group_is_active' => 1,
        ]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->getKey(),
            'administrator_username' => 'inventory.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Inventory Admin',
            'administrator_email' => 'inventory.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
