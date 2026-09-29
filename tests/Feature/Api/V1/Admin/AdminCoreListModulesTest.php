<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCoreListModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_access_all_selected_list_modules(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createModuleData();

        $endpoints = [
            '/api/v1/admin/company/banks?filter[is_active]=1' => ['data.results.0.account_number', '1234567890'],
            '/api/v1/admin/company/warehouses?search=pusat' => ['data.results.0.name', 'Warehouse Pusat'],
            '/api/v1/admin/customers?filter[member_level_id]=3' => ['data.results.0.member.code', 'MEM-LIST-001'],
            '/api/v1/admin/inventory/stocks?filter[warehouse_id]=1' => ['data.results.0.balance', 25],
            '/api/v1/admin/partnership/member-stocks?filter[product_id]=1' => ['data.results.0.last_updated_at', '2026-07-21 08:00:00'],
            '/api/v1/admin/rewards/annual?filter[year]=2026' => ['data.results.0.total_points', 125],
        ];

        foreach ($endpoints as $endpoint => [$path, $value]) {
            $this->getJson($endpoint)
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonCount(1, 'data.results')
                ->assertJsonPath($path, $value)
                ->assertJsonStructure(['success', 'message', 'data' => ['results', 'pagination']]);
        }
    }

    public function test_list_validation_uses_validation_error_code(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $this->getJson('/api/v1/admin/company/banks?limit=101')
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonStructure(['message', 'error_code', 'errors' => ['limit']]);
    }

    public function test_member_stock_displays_products_without_a_stock_row_for_selected_member(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createModuleData();
        DB::table('product')->insert([
            'product_id' => 2,
            'product_product_category_id' => 1,
            'product_code' => 'PRD-002',
            'product_name' => 'Toner',
        ]);
        DB::table('product_category')->insert([
            'product_category_id' => 2,
            'product_category_name' => 'Kategori Kosong',
        ]);

        $this->getJson('/api/v1/admin/partnership/member-stocks?filter[member_id]=1&sort=product_name')
            ->assertOk()
            ->assertJsonCount(2, 'data.results')
            ->assertJsonPath('data.results.1.product.code', 'PRD-002')
            ->assertJsonPath('data.results.1.balance', 0);

        $this->getJson('/api/v1/admin/partnership/member-stocks?filter[member_id]=1&filter[category_id]=2')
            ->assertOk()
            ->assertJsonCount(0, 'data.results');
    }

    private function createAdministrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Super Administrator',
            'administrator_group_type' => 'superuser',
            'administrator_group_is_active' => 1,
        ]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->administrator_group_id,
            'administrator_username' => 'core-list.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Core List Admin',
            'administrator_email' => 'core-list.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }

    private function createModuleData(): void
    {
        DB::table('ref_bank')->insert(['bank_id' => 1, 'bank_code' => 'BCA', 'bank_name' => 'Bank Central Asia']);
        DB::table('bank_company')->insert(['bank_company_id' => 1, 'bank_company_bank_id' => 1, 'bank_company_bank_acc_name' => 'PT DNY', 'bank_company_bank_acc_number' => '1234567890']);
        DB::table('warehouse')->insert(['warehouse_id' => 1, 'warehouse_name' => 'Warehouse Pusat', 'warehouse_legal_name' => 'PT DNY']);
        DB::table('member')->insert(['member_id' => 1, 'member_code' => 'MEM-LIST-001', 'member_member_level_id' => 3, 'member_name' => 'Mitra Satu', 'member_join_datetime' => '2026-01-01 00:00:00']);
        DB::table('customer')->insert(['customer_id' => 1, 'customer_member_id' => 1, 'customer_name' => 'Pelanggan Satu', 'customer_created_datetime' => '2026-07-21 08:00:00']);
        DB::table('product_category')->insert(['product_category_id' => 1, 'product_category_name' => 'Perawatan']);
        DB::table('product')->insert(['product_id' => 1, 'product_product_category_id' => 1, 'product_code' => 'PRD-001', 'product_name' => 'Serum']);
        DB::table('warehouse_stock')->insert(['warehouse_stock_id' => 1, 'warehouse_stock_warehouse_id' => 1, 'warehouse_stock_product_id' => 1, 'warehouse_stock_balance' => 25]);
        DB::table('member_stock')->insert(['member_stock_id' => 1, 'member_stock_member_id' => 1, 'member_stock_product_id' => 1, 'member_stock_balance' => 10]);
        DB::table('member_stock_log')->insert(['member_stock_log_id' => 1, 'member_stock_log_member_id' => 1, 'member_stock_log_product_id' => 1, 'member_stock_log_type' => 'in', 'member_stock_log_quantity' => 10, 'member_stock_log_balance' => 10, 'member_stock_log_note' => 'Stok awal', 'member_stock_log_datetime' => '2026-07-21 08:00:00']);
        DB::table('reward_point_annual')->insert(['reward_point_annual_id' => 1, 'reward_point_annual_member_id' => 1, 'reward_point_annual_year' => 2026, 'reward_point_annual_total_points' => 125, 'reward_point_annual_last_updated_datetime' => '2026-07-21 08:00:00']);
    }
}
