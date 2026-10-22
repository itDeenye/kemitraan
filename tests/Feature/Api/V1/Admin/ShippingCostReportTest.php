<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Member;
use App\Models\ShippingCourierExpress;
use App\Models\ShippingCourierInstant;
use App\Models\ShippingCourierManual;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Models\Trx;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShippingCostReportTest extends TestCase
{
    use RefreshDatabase;

    private function login(): void
    {
        $this->seed(AccessControlSeeder::class);
        Http::preventStrayRequests();
        $group = SiteAdministratorGroup::query()->create(['administrator_group_title' => 'Admin', 'administrator_group_type' => 'superuser', 'administrator_group_is_active' => 1]);
        $admin = SiteAdministrator::query()->create(['administrator_administrator_group_id' => $group->getKey(),
            'administrator_username' => 'test.admin', 'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Admin', 'administrator_email' => 'admin@example.test', 'administrator_is_active' => 1]);
        $this->actingAs($admin, 'admin_api');
        Member::query()->create(['member_code' => '0001/0000/0000', 'member_member_level_id' => 1, 'member_name' => 'Pembeli',
            'member_join_datetime' => now(), 'member_status' => 1]);
    }

    private function shipment(string $method, int $charged = 0, bool $booked = true, string $status = 'shipped', bool $isPreorder = false): Trx
    {
        $id = Trx::query()->count() + 1;
        $trx = Trx::query()->create(['trx_code' => 'TRX-'.$id, 'trx_buyer_type' => 'distributor', 'trx_buyer_id' => 1,
            'trx_seller_type' => 'warehouse', 'trx_seller_id' => 1, 'trx_type' => 'stock', 'trx_total_price' => 10000,
            'trx_shipping_cost' => $charged, 'trx_shipping_method' => $method, 'trx_status' => $status, 'trx_is_preorder' => $isPreorder,
            'trx_status_datetime' => now(), 'trx_datetime' => '2026-10-21 10:00:00']);
        $prefix = 'shipping_'.$method;
        $fields = [$prefix.'_ref_type' => 'trx', $prefix.'_ref_id' => $trx->getKey(), $prefix.'_awb' => 'AWB-'.$id,
            $prefix.($method === 'courier_manual' ? '_price' : '_cost') => 20000, $prefix.'_insurance' => 1000,
            $prefix.'_origin_address' => 'Gudang', $prefix.'_destination_address' => 'Alamat Mitra'];
        if ($method !== 'courier_instant') {
            $fields[$prefix.'_origin_subdistrict_id'] = 1;
            $fields[$prefix.'_destination_subdistrict_id'] = 2;
        }
        if ($method !== 'courier_manual') {
            $fields[$prefix.'_order_id'] = $booked ? 'BOOK-'.$id : '';
        }
        if ($method === 'courier_instant') {
            $fields[$prefix.'_admin_fee'] = 500;
        }
        $fields[$prefix.($method === 'courier_manual' ? '_name' : '_expedition_name')] = match ($method) {
            'courier_express' => 'JNE', 'courier_instant' => 'Gojek', default => 'Kurir Lokal',
        };
        $fields[$prefix.($method === 'courier_manual' ? '_service' : '_expedition_service')] = match ($method) {
            'courier_express' => 'REG', 'courier_instant' => 'Instant', default => 'Reguler',
        };
        $model = match ($method) {
            'courier_express' => ShippingCourierExpress::class, 'courier_instant' => ShippingCourierInstant::class, default => ShippingCourierManual::class,
        };
        $model::query()->create($fields);

        return $trx;
    }

    public function test_report_only_includes_sent_express_shipments_not_charged_to_buyer_and_uses_recorded_costs(): void
    {
        $this->login();
        $this->shipment('courier_express');
        $this->shipment('courier_instant');
        $this->shipment('courier_manual');
        $this->shipment('courier_express', 21000);
        $this->shipment('courier_express', 0, false);
        $this->shipment('courier_express', 0, true, 'cancelled');
        $this->getJson('/api/v1/admin/stc/shipping-cost-report?sort=id')->assertOk()->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.uncharged_cost', 21000)
            ->assertJsonPath('data.results.0.method', 'courier_express')
            ->assertJsonPath('data.results.0.courier', 'JNE')
            ->assertJsonPath('data.results.0.service', 'REG')
            ->assertJsonPath('data.results.0.buyer_shipping_cost', 0)
            ->assertJsonPath('data.pagination.total_data', 1);
        Http::assertNothingSent();
    }

    public function test_report_filters_search_dates_methods_and_latest_snapshot_without_duplicates(): void
    {
        $this->login();
        $trx = $this->shipment('courier_express');
        $this->shipment('courier_instant');
        $shipping = ShippingCourierExpress::query()->firstOrFail();
        ShippingCourierExpress::query()->create([...$shipping->getAttributes(), 'shipping_courier_express_id' => 99,
            'shipping_courier_express_cost' => 30000, 'shipping_courier_express_expedition_name' => 'J&T',
            'shipping_courier_express_expedition_service' => 'EZ']);
        $url = '/api/v1/admin/stc/shipping-cost-report';
        $this->getJson($url.'?filter[method]=courier_express&filter[datetime][gte]=2026-10-21&filter[datetime][lt]=2026-10-22&search=TRX-1')
            ->assertOk()->assertJsonCount(1, 'data.results')->assertJsonPath('data.results.0.id', $trx->getKey())
            ->assertJsonPath('data.results.0.uncharged_cost', 31000)
            ->assertJsonPath('data.results.0.courier', 'J&T')
            ->assertJsonPath('data.results.0.service', 'EZ');
        $this->getJson($url.'?filter[datetime][gte]=2026-10-22')->assertOk()->assertJsonCount(0, 'data.results');
        $filters = http_build_query(['filter' => ['code' => ['like' => '%TRX-1%'], 'buyer_name' => ['like' => '%Pembeli%'], 'buyer_code' => ['eq' => '0001/0000/0000']]]);
        $this->getJson($url.'?'.$filters)->assertOk()->assertJsonCount(1, 'data.results');
        $this->getJson($url.'?filter[method][ne]=courier_express')->assertOk()->assertJsonCount(0, 'data.results');
        $this->getJson($url.'?filter[method]=courier_manual')->assertOk()->assertJsonCount(0, 'data.results');
        $this->getJson($url.'?filter[method]=courier_instant')->assertOk()->assertJsonCount(0, 'data.results');
        $this->getJson($url.'?filter[method]=pickup')->assertUnprocessable();
    }

    public function test_report_excludes_unsent_orders_even_when_courier_is_already_booked(): void
    {
        $this->login();
        foreach (['waiting_payment', 'waiting_payment_approval', 'waiting_stock_screening', 'processing'] as $status) {
            $this->shipment('courier_express', 0, true, $status);
        }
        $shipped = $this->shipment('courier_express', 0, true, 'shipped');
        $completed = $this->shipment('courier_express', 0, true, 'completed');

        $this->getJson('/api/v1/admin/stc/shipping-cost-report?sort=id')
            ->assertOk()->assertJsonCount(2, 'data.results')
            ->assertJsonPath('data.results.0.id', $shipped->getKey())
            ->assertJsonPath('data.results.1.id', $completed->getKey());
        Http::assertNothingSent();
    }

    public function test_report_returns_and_filters_regular_and_preorder_purchase_types(): void
    {
        $this->login();
        $regular = $this->shipment('courier_express');
        $preorder = $this->shipment('courier_express', 0, true, 'completed', true);
        $url = '/api/v1/admin/stc/shipping-cost-report';

        $this->getJson($url.'?sort=id')->assertOk()->assertJsonCount(2, 'data.results')
            ->assertJsonPath('data.results.0.is_preorder', false)
            ->assertJsonPath('data.results.1.is_preorder', true);
        $this->getJson($url.'?filter[is_preorder]=1')->assertOk()->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $preorder->getKey());
        $this->getJson($url.'?filter[is_preorder][eq]=0')->assertOk()->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $regular->getKey());
        $this->getJson($url.'?filter[is_preorder][ne]=1')->assertOk()->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $regular->getKey());
        $this->getJson($url.'?filter[is_preorder]=invalid')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_shipping_report_menu_is_seeded_and_migration_is_idempotent(): void
    {
        $this->login();
        $migration = require database_path('migrations/2026_10_21_111523_add_shipping_cost_report_admin_menu.php');
        $migration->up();
        $this->assertSame(1, DB::table('site_administrator_menu')->where('administrator_menu_link', '/stc/shipping-cost-report')->count());
        $migration->down();
        $migration->up();
        $this->assertSame(1, DB::table('site_administrator_menu')->where('administrator_menu_link', '/stc/shipping-cost-report')->count());
    }

    public function test_shipping_cost_report_requires_admin_authentication(): void
    {
        $this->getJson('/api/v1/admin/stc/shipping-cost-report')->assertUnauthorized();
    }

    public function test_report_menu_rename_is_idempotent_reversible_and_preserves_permissions(): void
    {
        $this->login();
        $query = DB::table('site_administrator_menu')->where('administrator_menu_link', '/stc/shipping-cost-report');
        $query->update(['administrator_menu_title' => 'Laporan Ongkir Non-Pembeli']);
        $original = (array) $query->first();
        $privileges = DB::table('site_administrator_privilege')
            ->where('administrator_privilege_administrator_menu_id', $original['administrator_menu_id'])->get()->all();
        $migration = require database_path('migrations/2026_10_21_121134_rename_shipping_cost_report_admin_menu.php');

        $migration->up();
        $migration->up();
        $this->assertSame(1, $query->count());
        $this->assertSame([...$original, 'administrator_menu_title' => 'Laporan Biaya Pengiriman'], (array) $query->first());
        $this->assertEquals($privileges, DB::table('site_administrator_privilege')
            ->where('administrator_privilege_administrator_menu_id', $original['administrator_menu_id'])->get()->all());
        $migration->down();
        $this->assertSame($original, (array) $query->first());
        $migration->up();
        $this->assertSame('Laporan Biaya Pengiriman', $query->value('administrator_menu_title'));
    }
}
