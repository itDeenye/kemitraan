<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BrdSchemaAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_levels_and_member_prices_are_normalized(): void
    {
        $this->assertTrue(Schema::hasColumns('member_level', [
            'member_level_id',
            'member_level_code',
            'member_level_name',
            'member_level_min_order',
            'member_level_is_active',
        ]));
        $this->assertTrue(Schema::hasColumns('product_price', [
            'product_price_product_id',
            'product_price_member_level_id',
            'product_price_value',
        ]));
        $this->assertFalse(Schema::hasTable('member_price'));
        $this->assertTrue(Schema::hasColumn('member', 'member_member_level_id'));
        $this->assertFalse(Schema::hasColumn('member', 'member_level'));
        $this->assertFalse(Schema::hasColumn('member', 'member_agent_member_id'));
        $this->assertFalse(Schema::hasColumn('member', 'member_distributor_member_id'));
        $this->assertTrue(Schema::hasColumn('member_registration', 'member_registration_member_level_id'));
        $this->assertTrue(Schema::hasColumns('member_registration', [
            'member_registration_upline_member_id',
            'member_registration_member_id',
        ]));
        $this->assertFalse(Schema::hasColumn(
            'member_registration',
            'member_registration_parent_member_id'
        ));
        $this->assertFalse(Schema::hasColumn('member_registration', 'member_registration_member_level'));
        $this->assertFalse(Schema::hasColumn('member_registration', 'member_registration_agent_member_id'));
        $this->assertFalse(Schema::hasColumn('member_registration', 'member_registration_distributor_member_id'));
        $this->assertTrue(Schema::hasColumns('member_history', [
            'member_history_from_level_id',
            'member_history_to_level_id',
            'member_history_upline_member_level_id',
        ]));
        $this->assertFalse(Schema::hasColumn('product', 'product_distributor_price'));
        $this->assertFalse(Schema::hasColumn('product', 'product_agent_price'));
        $this->assertFalse(Schema::hasColumn('product', 'product_reseller_price'));
    }

    public function test_product_and_inventory_brd_columns_are_available(): void
    {
        $this->assertTrue(Schema::hasColumn('product', 'product_is_package'));
        $this->assertFalse(Schema::hasColumn('product', 'product_pv'));
        $this->assertFalse(Schema::hasColumn('trx_detail', 'trx_detail_product_pv'));
        $this->assertTrue(Schema::hasColumns('warehouse_stock_adjustment', [
            'stock_adjustment_administrator_id',
            'stock_adjustment_warehouse_id',
            'stock_adjustment_code',
            'stock_adjustment_note',
            'stock_adjustment_datetime',
        ]));
        $this->assertTrue(Schema::hasColumns('warehouse_stock_adjustment_detail', [
            'stock_adjustment_detail_stock_adjustment_id',
            'stock_adjustment_detail_stock_warehouse_id',
            'stock_adjustment_detail_product_id',
            'stock_adjustment_detail_type',
            'stock_adjustment_detail_qty',
        ]));
        $this->assertTrue(Schema::hasColumns('member_stock_adjustment', [
            'stock_adjustment_administrator_id',
            'stock_adjustment_member_id',
            'stock_adjustment_code',
            'stock_adjustment_note',
            'stock_adjustment_datetime',
        ]));
        $this->assertTrue(Schema::hasColumns('member_stock_adjustment_detail', [
            'stock_adjustment_detail_stock_adjustment_id',
            'stock_adjustment_detail_stock_member_id',
            'stock_adjustment_detail_product_id',
            'stock_adjustment_detail_type',
            'stock_adjustment_detail_qty',
            'stock_adjustment_detail_current_price',
            'stock_adjustment_detail_note',
        ]));
        $this->assertTrue(Schema::hasColumn('goods_receive', 'goods_receive_status_datetime'));
        $this->assertFalse(Schema::hasColumn('goods_receive', 'goods_receive_administrator_id'));
        $this->assertFalse(Schema::hasColumn('goods_receive', 'goods_receive_supplier_name'));
        $this->assertTrue(Schema::hasColumn('return', 'return_goods_receive_id'));
        $this->assertTrue(Schema::hasColumn('return_detail', 'return_detail_received_qty'));
        $this->assertFalse(Schema::hasTable('warehouse_stock_opname'));
        $this->assertFalse(Schema::hasTable('warehouse_stock_opname_detail'));
        $this->assertFalse(Schema::hasTable('warehouse_stock_transfer'));
        $this->assertFalse(Schema::hasTable('warehouse_stock_transfer_detail'));
    }

    public function test_spread_payment_uses_transaction_domain_naming(): void
    {
        $this->assertTrue(Schema::hasColumns('trx_spread_payment', [
            'trx_spread_payment_id',
            'trx_spread_payment_trx_id',
            'trx_spread_payment_upline_id',
            'trx_spread_payment_member_id',
            'trx_spread_payment_bank_id',
            'trx_spread_payment_account_name',
            'trx_spread_payment_account_number',
            'trx_spread_payment_percentage',
            'trx_spread_payment_amount',
            'trx_spread_payment_status',
            'trx_spread_payment_approved_by',
            'trx_spread_payment_approved_datetime',
            'trx_spread_payment_paid_by',
            'trx_spread_payment_paid_datetime',
            'trx_spread_payment_note',
            'trx_spread_payment_created_datetime',
        ]));
        $this->assertFalse(Schema::hasTable('reward_share_profit'));
    }

    public function test_customer_whatsapp_must_be_unique_when_filled(): void
    {
        DB::table('customer')->insert([
            'customer_member_id' => 1,
            'customer_name' => 'Pelanggan Pertama',
            'customer_whatsapp' => '628123456789',
            'customer_created_datetime' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('customer')->insert([
            'customer_member_id' => 2,
            'customer_name' => 'Pelanggan Kedua',
            'customer_whatsapp' => '628123456789',
            'customer_created_datetime' => now(),
        ]);
    }

    public function test_current_reward_schema_is_reproduced_by_migrations(): void
    {
        $this->assertTrue(Schema::hasColumns('reward_stockist', [
            'reward_stockist_id',
            'reward_stockist_member_id',
            'reward_stockist_year',
            'reward_stockist_month',
            'reward_stockist_total_trx_amount',
            'reward_stockist_bonus_value',
            'reward_stockist_used_value',
            'reward_stockist_used_trx_id',
            'reward_stockist_expiry_date',
            'reward_stockist_created_datetime',
        ]));
        $this->assertFalse(Schema::hasTable('reward_voucher_monthly'));
        $this->assertFalse(Schema::hasTable('reward_voucher_monthly_log'));
        $this->assertFalse(Schema::hasTable('x_reward_voucher_monthly'));
        $this->assertFalse(Schema::hasTable('x_reward_voucher_monthly_log'));
        $this->assertFalse(Schema::hasTable('reward_point_monthly_log'));

        $this->assertTrue(Schema::hasColumns('reward_point_monthly', [
            'reward_point_monthly_upline_id',
            'reward_point_monthly_upline_level_id',
            'reward_point_monthly_member_level_id',
            'reward_point_monthly_bonus_value',
            'reward_point_monthly_admin_id',
        ]));
        $this->assertFalse(Schema::hasColumn('reward_point_monthly', 'reward_point_monthly_total_amount'));
        $this->assertFalse(Schema::hasColumn('reward_point_monthly', 'reward_point_monthly_reward_qty_value'));
        $this->assertFalse(Schema::hasColumn('reward_point_monthly', 'reward_point_monthly_reward_stockist_value'));
        $this->assertFalse(Schema::hasColumn('reward_point_monthly', 'reward_point_monthly_reward_voucher_monthly_id'));
    }

    public function test_current_member_level_and_transaction_columns_are_reproduced_by_migrations(): void
    {
        $this->assertTrue(Schema::hasColumn('member_level', 'member_level_point_value'));
        $this->assertTrue(Schema::hasColumn('trx', 'trx_shipping_cost'));
        $this->assertTrue(Schema::hasColumns('trx', ['trx_voucher_id', 'trx_voucher_value']));
        $this->assertFalse(Schema::hasColumn('trx', 'trx_shipping_charge'));

        $trxStatusDefinition = DB::getDriverName() === 'sqlite'
            ? DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'trx'")->sql
            : Schema::getColumnType('trx', 'trx_status', true);

        $this->assertStringContainsString('shipped', $trxStatusDefinition);
        $this->assertStringNotContainsString('delivered', $trxStatusDefinition);

        $paymentStatusDefinition = DB::getDriverName() === 'sqlite'
            ? DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'trx_payment_transfer'")->sql
            : Schema::getColumnType('trx_payment_transfer', 'payment_transfer_approval_status', true);

        $this->assertStringContainsString('submitted', $paymentStatusDefinition);
    }
}
