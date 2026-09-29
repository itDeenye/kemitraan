<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Member;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Support\BusinessConfig;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminFinalMenuApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_analytics_statistics_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/user-analytics/statistics')->assertUnauthorized();
        $this->getJson('/api/v1/admin/dashboard/action-summary')->assertUnauthorized();
    }

    public function test_final_admin_master_and_analytic_menus_are_available(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();

        $this->getJson('/api/v1/admin/dashboard/analytics?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.summary.total_partners', 2)
            ->assertJsonPath('data.summary.turnover', 250000)
            ->assertJsonPath('data.summary.total_orders', 1)
            ->assertJsonPath('data.summary.total_commission', 2500)
            ->assertJsonPath('data.summary.products_sold', 2)
            ->assertJsonPath('data.summary.active_orders', 0)
            ->assertJsonPath('data.summary.warehouse_stock', 20);

        $this->getJson('/api/v1/admin/user-analytics/statistics?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('message', 'Statistik operasional berhasil dimuat.')
            ->assertJsonPath('data.summary.total_partners', 2)
            ->assertJsonPath('data.summary.total_commission', 2500)
            ->assertJsonPath('data.summary.products_sold', 2)
            ->assertJsonPath('data.order_statuses.8.code', 'completed')
            ->assertJsonPath('data.order_statuses.8.total', 1)
            ->assertJsonPath('data.recent_orders.0.code', 'TRX-001')
            ->assertJsonPath('data.recent_orders.0.buyer.name', 'Agent Satu')
            ->assertJsonPath('data.stock_alerts.0.code', 'LOW-001')
            ->assertJsonPath('data.stock_alerts.0.balance', 0);

        $this->getJson('/api/v1/admin/partnership/genealogy?member_id=1&depth=2')
            ->assertOk()
            ->assertJsonPath('data.results.0.code', 'DST-001')
            ->assertJsonPath('data.results.0.image', 'https://example.test/avatar-dst.webp')
            ->assertJsonPath('data.results.0.image_url', 'https://example.test/avatar-dst.webp')
            ->assertJsonPath('data.results.0.downlines.0.code', 'AGT-001')
            ->assertJsonPath('data.results.0.downlines.0.image', null)
            ->assertJsonPath('data.results.0.downlines.0.image_url', null);

        $this->getJson('/api/v1/admin/customers/1')
            ->assertOk()
            ->assertJsonPath('data.name', 'Pelanggan DNY')
            ->assertJsonPath('data.member.code', 'AGT-001');

        $this->getJson('/api/v1/admin/product/prices')
            ->assertOk()
            ->assertJsonPath('data.results.0.customer_price', 150000)
            ->assertJsonPath('data.results.0.member_prices.0.price', 100000);

        $this->putJson('/api/v1/admin/product/prices/1', [
            'customer_price' => 175000,
            'member_prices' => [
                ['member_level_id' => 1, 'price' => 110000],
                ['member_level_id' => 2, 'price' => 120000],
                ['member_level_id' => 3, 'price' => 130000],
            ],
        ])->assertOk()
            ->assertJsonPath('data.customer_price', 175000)
            ->assertJsonPath('data.member_prices.2.price', 130000);

        $this->putJson('/api/v1/admin/partnership/members/2', [
            'name' => 'Agent Diperbarui',
            'email' => 'agent@example.test',
            'mobile_phone' => '081234567890',
            'gender' => 'Laki-laki',
            'birth_date' => '1990-01-01',
            'addresses' => [],
            'bank_accounts' => [],
            'status' => 1,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Agent Diperbarui');

        $config = BusinessConfig::put('return', ['return_max_days' => 3]);
        $this->putJson('/api/v1/admin/system/configs/'.$config->getKey(), [
            'type' => 'json',
            'value' => ['return_max_days' => 5],
        ])->assertOk()
            ->assertJsonPath('data.scope', 'system')
            ->assertJsonPath('data.value.return_max_days', 5);

        $this->postJson('/api/v1/admin/system/configs', [
            'key' => 'partnership.targets',
            'type' => 'json',
            'value' => ['minimum_quantity' => 10],
        ])->assertMethodNotAllowed();
        $this->deleteJson('/api/v1/admin/system/configs/'.$config->getKey())
            ->assertMethodNotAllowed();

        DB::table('trx')->where('trx_id', 1)->update([
            'trx_buyer_type' => 'distributor',
            'trx_status' => 'processing',
        ]);

        $this->getJson('/api/v1/admin/dashboard/action-summary')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.menus.4.route', '/admin/inventory/shipping')
            ->assertJsonPath('data.menus.4.count', 1)
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'menus' => [
                        '*' => ['route', 'count'],
                    ],
                ],
            ]);
    }

    private function createData(): void
    {
        DB::table('ref_bank')->insert(['bank_id' => 1, 'bank_code' => 'BCA', 'bank_name' => 'Bank Central Asia']);
        DB::table('warehouse')->insert(['warehouse_id' => 1, 'warehouse_name' => 'Pusat', 'warehouse_legal_name' => 'PT DNY']);
        DB::table('product_category')->insert(['product_category_id' => 1, 'product_category_name' => 'Skincare']);
        $product = Product::query()->create([
            'product_product_category_id' => 1,
            'product_code' => 'SRM-001',
            'product_name' => 'Serum DNY',
            'product_customer_price' => 150000,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
        ]);
        $lowStockProduct = Product::query()->create([
            'product_product_category_id' => 1,
            'product_code' => 'LOW-001',
            'product_name' => 'Produk Stok Rendah',
            'product_customer_price' => 150000,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
        ]);
        ProductPrice::query()->create([
            'product_price_product_id' => $product->getKey(),
            'product_price_member_level_id' => 1,
            'product_price_value' => 100000,
        ]);
        ProductPrice::query()->create([
            'product_price_product_id' => $lowStockProduct->getKey(),
            'product_price_member_level_id' => 1,
            'product_price_value' => 100000,
        ]);

        Member::query()->create([
            'member_id' => 1,
            'member_code' => 'DST-001',
            'member_member_level_id' => 1,
            'member_parent_member_id' => 0,
            'member_name' => 'Distributor Utama',
            'member_image' => 'https://example.test/avatar-dst.webp',
            'member_join_datetime' => '2026-07-01 08:00:00',
            'member_status' => 1,
        ]);
        Member::query()->create([
            'member_id' => 2,
            'member_code' => 'AGT-001',
            'member_member_level_id' => 2,
            'member_parent_member_id' => 1,
            'member_name' => 'Agent Satu',
            'member_join_datetime' => '2026-07-02 08:00:00',
            'member_status' => 1,
        ]);
        DB::table('customer')->insert([
            'customer_id' => 1,
            'customer_member_id' => 2,
            'customer_name' => 'Pelanggan DNY',
            'customer_whatsapp' => '081111111111',
            'customer_created_datetime' => '2026-07-03 08:00:00',
        ]);
        DB::table('warehouse_stock')->insert([
            'warehouse_stock_id' => 1,
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => 1,
            'warehouse_stock_balance' => 20,
        ]);
        DB::table('trx')->insert($this->trxPayload());
        DB::table('trx_detail')->insert([
            'trx_detail_id' => 1,
            'trx_detail_trx_id' => 1,
            'trx_detail_product_id' => $product->getKey(),
            'trx_detail_product_code' => $product->product_code,
            'trx_detail_product_name' => $product->product_name,
            'trx_detail_product_price' => 125000,
            'trx_detail_nett_price' => 125000,
            'trx_detail_qty' => 2,
        ]);
        DB::table('trx_spread_payment')->insert([
            'trx_spread_payment_id' => 1,
            'trx_spread_payment_trx_id' => 1,
            'trx_spread_payment_upline_id' => 1,
            'trx_spread_payment_member_id' => 2,
            'trx_spread_payment_percentage' => 1,
            'trx_spread_payment_amount' => 2500,
            'trx_spread_payment_status' => 'approved',
            'trx_spread_payment_created_datetime' => '2026-07-10 08:00:00',
        ]);
    }

    /** @return array<string, mixed> */
    private function trxPayload(): array
    {
        return [
            'trx_id' => 1,
            'trx_code' => 'TRX-001',
            'trx_seller_type' => 'warehouse',
            'trx_seller_id' => 1,
            'trx_buyer_type' => 'agent',
            'trx_buyer_id' => 2,
            'trx_type' => 'stock',
            'trx_total_price' => 250000,
            'trx_grand_total_price' => 250000,
            'trx_grand_total_nett_price' => 250000,
            'trx_bill_amount' => 250000,
            'trx_payment_method' => 'transfer',
            'trx_shipping_method' => 'courier_manual',
            'trx_status' => 'completed',
            'trx_status_datetime' => '2026-07-10 08:00:00',
            'trx_datetime' => '2026-07-10 08:00:00',
        ];
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
            'administrator_username' => 'final.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Final Admin',
            'administrator_email' => 'final.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
