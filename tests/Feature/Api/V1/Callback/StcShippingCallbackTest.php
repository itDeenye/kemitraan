<?php

namespace Tests\Feature\Api\V1\Callback;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StcShippingCallbackTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_express_callback_updates_awb_and_delivery_status_idempotently(): void
    {
        $this->createTransaction();
        $this->createExpressShipping('trx', 1, 'STC-EXP-001');
        config(['services.stc.callback_token' => 'callback-secret']);

        $payload = [
            'method' => 'shipped_packages',
            'data' => [[
                'order_id' => 'STC-EXP-001',
                'awb' => 'AWB-EXP-001',
                'date' => '2026-08-11 09:00:00',
                'shipped_at' => '2026-08-11 08:55:00',
            ]],
            'payment' => [],
            'packages' => [],
        ];

        $this->postJson('/api/v1/callbacks/stc/shipping', $payload)
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'process_error');

        $this->withHeader('X-STC-Callback-Token', 'callback-secret')
            ->postJson('/api/v1/callbacks/stc/shipping', $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.processed', 1)
            ->assertJsonPath('data.idempotent', 0)
            ->assertJsonPath('data.results.0.shipping_method', 'courier_express')
            ->assertJsonPath('data.results.0.reference_type', 'trx');

        $this->assertDatabaseHas('trx', ['trx_id' => 1, 'trx_status' => 'shipped']);
        $this->assertDatabaseHas('shipping_courier_express', [
            'shipping_courier_express_order_id' => 'STC-EXP-001',
            'shipping_courier_express_awb' => 'AWB-EXP-001',
        ]);
        $this->assertDatabaseHas('shipping_courier_express_status', [
            'shipping_courier_express_status_value' => 'shipped_packages',
            'shipping_courier_express_status_external_ref_code' => 'STC-EXP-001',
        ]);

        $this->withHeader('X-STC-Callback-Token', 'callback-secret')
            ->postJson('/api/v1/callbacks/stc/shipping', $payload)
            ->assertOk()
            ->assertJsonPath('data.processed', 1)
            ->assertJsonPath('data.idempotent', 1);

        $this->assertSame(
            1,
            DB::table('shipping_courier_express_status')
                ->where('shipping_courier_express_status_value', 'shipped_packages')
                ->where('shipping_courier_express_status_external_ref_code', 'STC-EXP-001')
                ->count()
        );

        $this->withHeader('X-STC-Callback-Token', 'callback-secret')
            ->postJson('/api/v1/callbacks/stc/shipping', [
                'method' => 'processed_packages',
                'data' => [['order_id' => 'STC-EXP-001']],
            ])
            ->assertOk()
            ->assertJsonPath('data.ignored', 1);
        $this->assertDatabaseMissing('shipping_courier_express_status', [
            'shipping_courier_express_status_value' => 'processed_packages',
            'shipping_courier_express_status_external_ref_code' => 'STC-EXP-001',
        ]);
        $this->assertDatabaseHas('integration_callback_log', [
            'integration_callback_log_provider' => 'stc',
            'integration_callback_log_status' => 'completed',
            'integration_callback_log_http_code' => 200,
        ]);
    }

    public function test_finished_callback_marks_order_received_and_waits_for_goods_receipt(): void
    {
        $this->createTransaction('shipped');
        $this->createExpressShipping('trx', 1, 'STC-EXP-FINISHED');

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'finished_packages',
            'data' => [[
                'order_id' => 'STC-EXP-FINISHED',
                'awb' => 'AWB-FINISHED',
                'finished_at' => '2026-08-11 12:00:00',
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('shipping_courier_express_status', [
            'shipping_courier_express_status_value' => 'finished_packages',
        ]);
        $this->assertDatabaseHas('trx', ['trx_id' => 1, 'trx_status' => 'received']);
    }

    public function test_finished_callback_uses_terminal_preorder_shipping_when_legacy_order_id_is_duplicated(): void
    {
        $this->createTransaction('shipped');
        DB::table('trx')->where('trx_id', 1)->update([
            'trx_is_preorder' => 1,
        ]);
        DB::table('trx')->insert([
            'trx_id' => 2,
            'trx_code' => 'TRX-PO-TERMINAL-002',
            'trx_parent_trx_id' => 1,
            'trx_is_preorder' => 1,
            'trx_seller_type' => 'warehouse',
            'trx_seller_id' => 1,
            'trx_buyer_type' => 'distributor',
            'trx_buyer_id' => 1,
            'trx_type' => 'stock',
            'trx_total_price' => 100000,
            'trx_grand_total_price' => 100000,
            'trx_grand_total_nett_price' => 100000,
            'trx_bill_amount' => 100000,
            'trx_payment_method' => 'transfer',
            'trx_shipping_method' => 'courier_express',
            'trx_status' => 'shipped',
            'trx_status_datetime' => '2026-08-11 08:00:00',
            'trx_datetime' => '2026-08-11 08:00:00',
        ]);
        $this->createExpressShipping('trx', 1, 'STC-PO-DUPLICATED');
        DB::table('shipping_courier_express')->insert([
            'shipping_courier_express_id' => 2,
            'shipping_courier_express_ref_type' => 'trx',
            'shipping_courier_express_ref_id' => 2,
            'shipping_courier_express_order_id' => 'STC-PO-DUPLICATED',
            'shipping_courier_express_origin_address' => 'Gudang',
            'shipping_courier_express_origin_subdistrict_id' => 1,
            'shipping_courier_express_destination_address' => 'Distributor',
            'shipping_courier_express_destination_subdistrict_id' => 2,
        ]);

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'finished_packages',
            'data' => [[
                'order_id' => 'STC-PO-DUPLICATED',
                'awb' => 'AWB-PO-TERMINAL',
                'finished_at' => '2026-08-11 12:00:00',
            ]],
        ])->assertOk();

        $this->assertSame(2, DB::table('trx')->where('trx_status', 'received')->count());
        $this->assertDatabaseHas('shipping_courier_express_status', [
            'shipping_courier_express_status_ref_id' => 2,
            'shipping_courier_express_status_value' => 'finished_packages',
        ]);
        $this->assertDatabaseMissing('shipping_courier_express_status', [
            'shipping_courier_express_status_ref_id' => 1,
            'shipping_courier_express_status_value' => 'finished_packages',
        ]);
    }

    public function test_finished_callback_completes_customer_sale_without_goods_receipt(): void
    {
        $this->createTransaction('shipped');
        DB::table('trx')->where('trx_id', 1)->update([
            'trx_buyer_type' => 'customer',
            'trx_type' => 'retail',
        ]);
        $this->createExpressShipping('trx', 1, 'STC-CUSTOMER-FINISHED');

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'finished_packages',
            'data' => [[
                'order_id' => 'STC-CUSTOMER-FINISHED',
                'awb' => 'AWB-CUSTOMER-FINISHED',
                'finished_at' => '2026-08-11 12:00:00',
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('trx', ['trx_id' => 1, 'trx_status' => 'completed']);
        $this->assertDatabaseMissing('goods_receive', ['goods_receive_trx_id' => 1]);
    }

    public function test_canceled_callback_requires_reshipment_without_reversing_stock_idempotently(): void
    {
        $this->createShippedStockTransaction();
        $this->createExpressShipping('trx', 1, 'STC-EXP-CANCELED');

        $payload = [
            'method' => 'canceled_packages',
            'data' => [[
                'order_id' => 'STC-EXP-CANCELED',
                'awb' => 'AWB-CANCELED',
                'rejected_at' => '2026-08-11 13:00:00',
                'reason' => 'Pengiriman dibatalkan provider',
            ]],
        ];

        $this->postJson('/api/v1/callbacks/stc/shipping', $payload)
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'canceled_packages');

        $this->assertFailedShipmentRequiresReshipment('canceled_packages');

        $this->postJson('/api/v1/callbacks/stc/shipping', $payload)
            ->assertOk()
            ->assertJsonPath('data.idempotent', 1);
        $this->assertFailedShipmentRequiresReshipment('canceled_packages');
        $this->assertSame(0, DB::table('warehouse_stock_log')
            ->where('warehouse_stock_log_note', 'like', 'Stok dikembalikan%')
            ->count());
    }

    public function test_returned_callback_requires_reshipment_without_reversing_stock(): void
    {
        $this->createShippedStockTransaction();
        $this->createExpressShipping('trx', 1, 'STC-EXP-RETURNED');

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'finished_packages',
            'data' => [[
                'order_id' => 'STC-EXP-RETURNED',
                'awb' => 'AWB-RETURNED',
                'finished_at' => '2026-08-11 13:00:00',
            ]],
        ])->assertOk();
        $this->assertDatabaseHas('trx', ['trx_id' => 1, 'trx_status' => 'received']);

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'returned_packages',
            'data' => [[
                'order_id' => 'STC-EXP-RETURNED',
                'awb' => 'AWB-RETURNED',
                'returned_at' => '2026-08-11 14:00:00',
                'reason' => 'Paket dikembalikan kepada pengirim',
            ]],
        ])->assertOk();

        $this->assertFailedShipmentRequiresReshipment('returned_packages');
    }

    public function test_instant_canceled_callback_requires_reshipment_on_the_same_transaction(): void
    {
        $this->createShippedStockTransaction();
        DB::table('trx')->where('trx_id', 1)->update([
            'trx_shipping_method' => 'courier_instant',
        ]);
        $this->createInstantShipping('trx', 1, 'STC-INSTANT-CANCELED');

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'canceled_packages',
            'data' => [[
                'order_id' => 'STC-INSTANT-CANCELED',
                'rejected_at' => '2026-08-11 15:00:00',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.results.0.shipping_method', 'courier_instant');

        $this->assertDatabaseHas('trx', ['trx_id' => 1, 'trx_status' => 'reship_required']);
        $this->assertDatabaseHas('shipping_courier_instant_status', [
            'shipping_courier_instant_status_value' => 'canceled_packages',
        ]);
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_product_id' => 10,
            'warehouse_stock_balance' => 8,
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 2,
            'member_stock_product_id' => 10,
            'member_stock_transfer_in' => 2,
        ]);
    }

    public function test_problem_and_return_finished_events_follow_provider_contract(): void
    {
        $this->createShippedStockTransaction();
        $this->createExpressShipping('trx', 1, 'STC-EXP-PROBLEM');

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'problem_packages',
            'data' => [[
                'order_id' => 'STC-EXP-PROBLEM',
                'problem_at' => '2026-08-11 14:00:00',
                'reason' => 'Alamat penerima perlu dikonfirmasi',
            ]],
        ])->assertOk();
        $this->assertDatabaseHas('trx', ['trx_id' => 1, 'trx_status' => 'shipped']);
        $this->assertDatabaseHas('shipping_courier_express_status', [
            'shipping_courier_express_status_value' => 'problem_packages',
        ]);

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'return_finished_package',
            'data' => [[
                'order_id' => 'STC-EXP-PROBLEM',
                'return_finished_at' => '2026-08-12 14:00:00',
            ]],
        ])->assertOk();

        $this->assertFailedShipmentRequiresReshipment('return_finished_package');
    }

    public function test_all_return_events_keep_the_same_warehouse_order_ready_for_reshipment(): void
    {
        $this->createShippedStockTransaction();
        $this->createExpressShipping('trx', 1, 'STC-EXP-RETURN-SEQUENCE');

        foreach ([
            'canceled_packages' => 'rejected_at',
            'returned_packages' => 'returned_at',
            'return_finished_package' => 'return_finished_at',
        ] as $event => $dateField) {
            $this->postJson('/api/v1/callbacks/stc/shipping', [
                'method' => $event,
                'data' => [[
                    'order_id' => 'STC-EXP-RETURN-SEQUENCE',
                    $dateField => '2026-08-12 14:00:00',
                ]],
            ])->assertOk()
                ->assertJsonPath('data.results.0.ignored', false);

            $this->assertDatabaseHas('shipping_courier_express_status', [
                'shipping_courier_express_status_ref_type' => 'trx',
                'shipping_courier_express_status_ref_id' => 1,
                'shipping_courier_express_status_value' => $event,
            ]);
            $this->assertDatabaseHas('trx', [
                'trx_id' => 1,
                'trx_seller_type' => 'warehouse',
                'trx_status' => 'reship_required',
            ]);
        }

        $this->assertDatabaseCount('trx', 1);
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_id' => 1,
            'warehouse_stock_balance' => 8,
        ]);
    }

    public function test_instant_return_failure_callback_updates_return_status(): void
    {
        $this->createTransaction('received');
        DB::table('return')->insert([
            'return_id' => 1,
            'return_code' => 'RET-001',
            'return_goods_receive_id' => null,
            'return_member_id' => 1,
            'return_description' => 'Produk rusak',
            'return_status' => 'waiting_member_shipment',
            'return_shipping_method' => 'courier_instant',
            'return_created_datetime' => '2026-08-10 08:00:00',
        ]);
        $this->createInstantShipping('return_company', 1, 'STC-RET-001');

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'returned_packages',
            'data' => [[
                'order_id' => 'STC-RET-001',
                'awb' => 'AWB-RET-001',
                'returned_at' => '2026-08-11 13:00:00',
                'reason' => 'Penerima tidak berada di lokasi',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.results.0.shipping_method', 'courier_instant')
            ->assertJsonPath('data.results.0.reference_type', 'return_company');

        $this->assertDatabaseHas('shipping_courier_instant_status', [
            'shipping_courier_instant_status_ref_type' => 'return_company',
            'shipping_courier_instant_status_ref_id' => 1,
            'shipping_courier_instant_status_value' => 'returned_packages',
        ]);
        $this->assertDatabaseHas('return', [
            'return_id' => 1,
            'return_status' => 'return_shipping_failed',
        ]);
    }

    public function test_unknown_order_is_audited_as_failed(): void
    {
        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'processed_packages',
            'data' => [['order_id' => 'STC-NOT-FOUND']],
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        $this->assertDatabaseHas('integration_callback_log', [
            'integration_callback_log_event' => 'processed_packages',
            'integration_callback_log_status' => 'failed',
            'integration_callback_log_http_code' => 422,
        ]);
    }

    public function test_non_stc_shipping_event_aliases_are_rejected(): void
    {
        foreach (['cancelled_packages', 'completed'] as $event) {
            $this->postJson('/api/v1/callbacks/stc/shipping', [
                'method' => $event,
                'data' => [['order_id' => 'STC-INVALID-EVENT']],
            ])->assertUnprocessable()
                ->assertJsonPath('error_code', 'validation');
        }
    }

    private function createTransaction(string $status = 'processing'): void
    {
        DB::table('trx')->insert([
            'trx_id' => 1,
            'trx_code' => 'TRX-001',
            'trx_seller_type' => 'distributor',
            'trx_seller_id' => 1,
            'trx_buyer_type' => 'agent',
            'trx_buyer_id' => 2,
            'trx_type' => 'stock',
            'trx_total_price' => 100000,
            'trx_grand_total_price' => 100000,
            'trx_grand_total_nett_price' => 100000,
            'trx_bill_amount' => 100000,
            'trx_payment_method' => 'transfer',
            'trx_shipping_method' => 'courier_express',
            'trx_status' => $status,
            'trx_status_datetime' => '2026-08-11 08:00:00',
            'trx_datetime' => '2026-08-11 08:00:00',
        ]);
    }

    private function createShippedStockTransaction(): void
    {
        DB::table('trx')->insert([
            'trx_id' => 1,
            'trx_code' => 'TRX-CALLBACK-001',
            'trx_seller_type' => 'warehouse',
            'trx_seller_id' => 1,
            'trx_buyer_type' => 'distributor',
            'trx_buyer_id' => 2,
            'trx_type' => 'stock',
            'trx_total_price' => 200000,
            'trx_grand_total_price' => 200000,
            'trx_grand_total_nett_price' => 200000,
            'trx_bill_amount' => 200000,
            'trx_payment_method' => 'transfer',
            'trx_shipping_method' => 'courier_express',
            'trx_status' => 'shipped',
            'trx_status_datetime' => '2026-08-11 08:00:00',
            'trx_datetime' => '2026-08-11 08:00:00',
        ]);
        DB::table('trx_detail')->insert([
            'trx_detail_trx_id' => 1,
            'trx_detail_product_id' => 10,
            'trx_detail_product_code' => 'DNY-010',
            'trx_detail_product_name' => 'Produk Callback',
            'trx_detail_product_price' => 100000,
            'trx_detail_nett_price' => 100000,
            'trx_detail_qty' => 2,
        ]);
        DB::table('warehouse_stock')->insert([
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => 10,
            'warehouse_stock_balance' => 8,
            'warehouse_stock_transfer_in' => 0,
            'warehouse_stock_transfer_out' => 0,
        ]);
        DB::table('member_stock')->insert([
            'member_stock_member_id' => 2,
            'member_stock_product_id' => 10,
            'member_stock_balance' => 0,
            'member_stock_transfer_in' => 2,
            'member_stock_transfer_out' => 0,
        ]);
        DB::table('member_point_transaction')->insert([
            'member_point_transaction_member_id' => 2,
            'member_point_transaction_trx_id' => 1,
            'member_point_transaction_quantity' => 2,
            'member_point_transaction_year' => 2026,
            'member_point_transaction_month' => 8,
            'member_point_transaction_approved_datetime' => '2026-08-11 08:00:00',
        ]);
        DB::table('reward_point_annual')->insert([
            'reward_point_annual_member_id' => 2,
            'reward_point_annual_year' => 2026,
            'reward_point_annual_total_points' => 2,
            'reward_point_annual_last_updated_datetime' => '2026-08-11 08:00:00',
        ]);
        DB::table('reward_point_annual_log')->insert([
            'reward_point_annual_log_member_id' => 2,
            'reward_point_annual_log_trx_id' => 1,
            'reward_point_annual_log_type' => 'in',
            'reward_point_annual_log_points' => 2,
            'reward_point_annual_log_note' => 'Poin dari transaksi TRX-CALLBACK-001',
            'reward_point_annual_log_datetime' => '2026-08-11 08:00:00',
        ]);
    }

    private function assertFailedShipmentRequiresReshipment(string $event): void
    {
        $this->assertDatabaseHas('trx', ['trx_id' => 1, 'trx_status' => 'reship_required']);
        $this->assertDatabaseHas('shipping_courier_express_status', [
            'shipping_courier_express_status_value' => $event,
        ]);
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => 10,
            'warehouse_stock_balance' => 8,
            'warehouse_stock_transfer_out' => 0,
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 2,
            'member_stock_product_id' => 10,
            'member_stock_transfer_in' => 2,
        ]);
        $this->assertDatabaseHas('member_point_transaction', [
            'member_point_transaction_trx_id' => 1,
        ]);
        $this->assertDatabaseHas('reward_point_annual', [
            'reward_point_annual_member_id' => 2,
            'reward_point_annual_year' => 2026,
            'reward_point_annual_total_points' => 2,
        ]);
        $this->assertDatabaseMissing('reward_point_annual_log', [
            'reward_point_annual_log_trx_id' => 1,
            'reward_point_annual_log_type' => 'out',
        ]);
    }

    private function createExpressShipping(string $refType, int $refId, string $orderId): void
    {
        DB::table('shipping_courier_express')->insert([
            'shipping_courier_express_id' => 1,
            'shipping_courier_express_ref_type' => $refType,
            'shipping_courier_express_ref_id' => $refId,
            'shipping_courier_express_order_id' => $orderId,
            'shipping_courier_express_origin_address' => 'Jakarta',
            'shipping_courier_express_origin_subdistrict_id' => 1,
            'shipping_courier_express_destination_address' => 'Surabaya',
            'shipping_courier_express_destination_subdistrict_id' => 2,
        ]);
    }

    private function createInstantShipping(string $refType, int $refId, string $orderId): void
    {
        DB::table('shipping_courier_instant')->insert([
            'shipping_courier_instant_id' => 1,
            'shipping_courier_instant_ref_type' => $refType,
            'shipping_courier_instant_ref_id' => $refId,
            'shipping_courier_instant_order_id' => $orderId,
            'shipping_courier_instant_origin_address' => 'Jakarta',
            'shipping_courier_instant_destination_address' => 'Surabaya',
        ]);
    }
}
