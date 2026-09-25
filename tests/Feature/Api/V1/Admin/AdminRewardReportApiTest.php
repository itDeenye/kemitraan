<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Member;
use App\Models\MemberAchievement;
use App\Models\Product;
use App\Models\RewardPointMonthly;
use App\Models\RewardStockist;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminRewardReportApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reward_and_report_menus_use_existing_business_data(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();

        $this->getJson('/api/v1/admin/rewards/monthly?filter[year]=2026&filter[month]=7')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.member.level.code', 'DST')
            ->assertJsonPath('data.results.0.total_points', 25);
        $this->getJson('/api/v1/admin/rewards/monthly/2')->assertNotFound();
        $this->postJson('/api/v1/admin/rewards/monthly/1/process')
            ->assertOk()
            ->assertJsonPath('data.is_processed', true);

        $this->getJson('/api/v1/admin/rewards/stockists?filter[year]=2026')
            ->assertOk()
            ->assertJsonPath('data.results.0.voucher_value', 1250000)
            ->assertJsonPath('data.results.0.remaining_value', 1000000);

        $this->getJson('/api/v1/admin/rewards/point-achievements?year_from=2026&month_from=7&year_to=2026&month_to=7')
            ->assertOk()
            ->assertJsonPath('data.results.0.points', 25)
            ->assertJsonPath('data.results.0.total_spending', 50000000);

        $this->getJson('/api/v1/admin/reports/sales?date_from=2026-07-01&date_to=2026-07-31&pagination_bool=false')
            ->assertOk()
            ->assertJsonPath('data.results.0.code', 'TRX-REPORT-001')
            ->assertJsonMissingPath('data.pagination');
        $this->getJson('/api/v1/admin/reports/partnerships?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.results.0.purchase_amount', 50000000);
        $this->getJson('/api/v1/admin/reports/stocks?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.results.0.starting_balance', 10)
            ->assertJsonPath('data.results.0.ending_balance', 12);
        $this->getJson('/api/v1/admin/reports/cash-income?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.results.0.payment.status', 'approved')
            ->assertJsonPath('data.results.0.payment.amount', 50000000);
    }

    private function createData(): void
    {
        DB::table('warehouse')->insert(['warehouse_id' => 1, 'warehouse_name' => 'Pusat', 'warehouse_legal_name' => 'PT DNY']);
        DB::table('product_category')->insert(['product_category_id' => 1, 'product_category_name' => 'Skincare']);
        Product::query()->create([
            'product_id' => 1,
            'product_product_category_id' => 1,
            'product_code' => 'SRM-001',
            'product_name' => 'Serum DNY',
            'product_customer_price' => 150000,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
        ]);
        Member::query()->create([
            'member_id' => 1,
            'member_code' => 'DST-001',
            'member_member_level_id' => 1,
            'member_parent_member_id' => 0,
            'member_name' => 'Distributor Utama',
            'member_join_datetime' => '2026-07-01 08:00:00',
            'member_status' => 1,
        ]);
        DB::table('warehouse_stock')->insert([
            'warehouse_stock_id' => 1,
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => 1,
            'warehouse_stock_balance' => 12,
        ]);
        DB::table('warehouse_stock_log')->insert([
            [
                'warehouse_stock_log_id' => 1,
                'warehouse_stock_log_warehouse_id' => 1,
                'warehouse_stock_log_product_id' => 1,
                'warehouse_stock_log_type' => 'in',
                'warehouse_stock_log_quantity' => 5,
                'warehouse_stock_log_balance' => 15,
                'warehouse_stock_log_note' => 'Stok masuk',
                'warehouse_stock_log_datetime' => '2026-07-05 08:00:00',
            ],
            [
                'warehouse_stock_log_id' => 2,
                'warehouse_stock_log_warehouse_id' => 1,
                'warehouse_stock_log_product_id' => 1,
                'warehouse_stock_log_type' => 'out',
                'warehouse_stock_log_quantity' => 3,
                'warehouse_stock_log_balance' => 12,
                'warehouse_stock_log_note' => 'Stok keluar',
                'warehouse_stock_log_datetime' => '2026-07-06 08:00:00',
            ],
        ]);
        DB::table('trx')->insert($this->trxPayload());
        DB::table('trx_payment_transfer')->insert([
            'payment_transfer_id' => 1,
            'payment_transfer_trx_id' => 1,
            'payment_transfer_bill_amount' => 50000000,
            'payment_transfer_bank_id' => 1,
            'payment_transfer_account_name' => 'PT DNY',
            'payment_transfer_account_number' => '123456',
            'payment_transfer_amount' => 50000000,
            'payment_transfer_datetime' => '2026-07-11 08:00:00',
            'payment_transfer_approval_status' => 'approved',
            'payment_transfer_approval_admin_id' => 1,
            'payment_transfer_approval_datetime' => '2026-07-11 09:00:00',
            'payment_transfer_note' => 'Sesuai',
        ]);
        RewardPointMonthly::query()->create([
            'reward_point_monthly_id' => 1,
            'reward_point_monthly_member_id' => 1,
            'reward_point_monthly_member_level_id' => 1,
            'reward_point_monthly_year' => 2026,
            'reward_point_monthly_month' => 7,
            'reward_point_monthly_total_qty' => 25,
            'reward_point_monthly_bonus_value' => 5000,
            'reward_point_monthly_is_processed' => 0,
        ]);
        RewardPointMonthly::query()->create([
            'reward_point_monthly_id' => 2,
            'reward_point_monthly_upline_id' => 1,
            'reward_point_monthly_upline_level_id' => 1,
            'reward_point_monthly_member_id' => 2,
            'reward_point_monthly_member_level_id' => 2,
            'reward_point_monthly_year' => 2026,
            'reward_point_monthly_month' => 7,
            'reward_point_monthly_total_qty' => 20,
            'reward_point_monthly_bonus_value' => 80000,
            'reward_point_monthly_is_processed' => 0,
        ]);
        RewardStockist::query()->create([
            'reward_stockist_id' => 1,
            'reward_stockist_member_id' => 1,
            'reward_stockist_year' => 2026,
            'reward_stockist_month' => 7,
            'reward_stockist_total_trx_amount' => 50000000,
            'reward_stockist_bonus_value' => 1250000,
            'reward_stockist_used_value' => 250000,
            'reward_stockist_used_trx_id' => 1,
            'reward_stockist_expiry_date' => '2026-08-31',
            'reward_stockist_created_datetime' => '2026-07-31 23:59:59',
        ]);
        MemberAchievement::query()->create([
            'member_achievement_id' => 1,
            'member_achievement_member_id' => 1,
            'member_achievement_year' => 2026,
            'member_achievement_month' => 7,
            'member_achievement_point' => 25,
            'member_achievement_customer_count' => 5,
            'member_achievement_total_trx_amount' => 50000000,
            'member_achievement_create_datetime' => '2026-07-31 23:59:59',
        ]);
    }

    /** @return array<string, mixed> */
    private function trxPayload(): array
    {
        return [
            'trx_id' => 1,
            'trx_code' => 'TRX-REPORT-001',
            'trx_seller_type' => 'warehouse',
            'trx_seller_id' => 1,
            'trx_buyer_type' => 'distributor',
            'trx_buyer_id' => 1,
            'trx_type' => 'stock',
            'trx_total_price' => 50000000,
            'trx_grand_total_price' => 50000000,
            'trx_grand_total_nett_price' => 50000000,
            'trx_bill_amount' => 50000000,
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
            'administrator_id' => 1,
            'administrator_administrator_group_id' => $group->getKey(),
            'administrator_username' => 'reward.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Reward Admin',
            'administrator_email' => 'reward.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
