<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminMemberBatchStockReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-08-18 08:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_authentication_is_required(): void
    {
        $this->getJson('/api/v1/admin/reports/member-stock-batches')
            ->assertUnauthorized();
    }

    public function test_administrator_can_view_member_stock_by_batch(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createStockData();

        $response = $this->getJson(
            '/api/v1/admin/reports/member-stock-batches?pagination_bool=false&sort=batch_number',
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Laporan stok per batch milik mitra berhasil dimuat.')
            ->assertJsonCount(2, 'data.results')
            ->assertJsonMissingPath('data.pagination');

        $results = collect($response->json('data.results'));
        $batch = $results->firstWhere('batch.number', 'BATCH-SRM-001');
        $unassigned = $results->firstWhere('batch.number', null);

        $this->assertSame('DNY000001', data_get($batch, 'member.code'));
        $this->assertSame('Distributor', data_get($batch, 'member.level.name'));
        $this->assertSame('Serum DNY', data_get($batch, 'product.name'));
        $this->assertSame('safe', data_get($batch, 'batch.expiry_status'));
        $this->assertSame(10, data_get($batch, 'balance'));
        $this->assertSame(2, data_get($unassigned, 'balance'));
        $this->assertSame(12, $results->sum('balance'));
    }

    public function test_report_supports_member_batch_and_expiry_filters(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createStockData();

        $this->getJson(
            '/api/v1/admin/reports/member-stock-batches'
            .'?search=distributor&filter[member_id]=1&filter[batch_number]=BATCH-SRM-001'
            .'&filter[expiry_status]=safe',
        )
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.batch.number', 'BATCH-SRM-001')
            ->assertJsonPath('data.results.0.balance', 10);
    }

    public function test_filter_validation_uses_indonesian_attribute(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $response = $this->getJson(
            '/api/v1/admin/reports/member-stock-batches?filter[expiry_status]=invalid',
        );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation');
        $this->assertSame(
            'Status kedaluwarsa yang dipilih tidak valid.',
            $response->json('errors')['filter.expiry_status'][0],
        );
    }

    private function createStockData(): void
    {
        DB::table('member')->insert([
            'member_id' => 1,
            'member_code' => 'DNY000001',
            'member_member_level_id' => 1,
            'member_parent_member_id' => 0,
            'member_name' => 'Distributor Utama',
            'member_join_datetime' => '2026-01-01 08:00:00',
            'member_status' => 1,
        ]);
        DB::table('product_category')->insert([
            'product_category_id' => 1,
            'product_category_name' => 'Skincare',
        ]);
        DB::table('product')->insert([
            'product_id' => 1,
            'product_product_category_id' => 1,
            'product_code' => 'SRM-001',
            'product_name' => 'Serum DNY',
            'product_unit' => 'pcs',
            'product_is_deleted' => 0,
        ]);
        DB::table('member_stock')->insert([
            'member_stock_id' => 1,
            'member_stock_member_id' => 1,
            'member_stock_product_id' => 1,
            'member_stock_balance' => 12,
        ]);
        DB::table('goods_receive')->insert([
            'goods_receive_id' => 1,
            'goods_receive_number' => 'GRN000001',
            'goods_receive_trx_id' => 1,
            'goods_receive_buyer_type' => 'distributor',
            'goods_receive_buyer_id' => 1,
            'goods_receive_seller_type' => 'warehouse',
            'goods_receive_seller_id' => 1,
            'goods_receive_status' => 'completed',
            'goods_receive_created_datetime' => '2026-08-01 08:00:00',
        ]);
        DB::table('goods_receive_detail')->insert([
            'goods_receive_detail_id' => 1,
            'goods_receive_detail_receive_id' => 1,
            'goods_receive_detail_product_id' => 1,
            'goods_receive_detail_batch_number' => 'BATCH-SRM-001',
            'goods_receive_detail_expire_date' => '2027-12-31',
            'goods_receive_detail_qty' => 10,
            'goods_receive_detail_created_datetime' => '2026-08-01 08:00:00',
        ]);
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
            'administrator_username' => 'batch.report.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Batch Report Admin',
            'administrator_email' => 'batch.report.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
