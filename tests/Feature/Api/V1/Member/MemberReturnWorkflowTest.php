<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Support\BusinessConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MemberReturnWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_get_return_couriers_and_pickup_schedules_for_each_shipping_leg(): void
    {
        [$account, $product] = $this->createReturnData();
        $this->actingAs($account, 'member_api');
        $returnPayload = $this->returnPayload($product->getKey());
        unset($returnPayload['courier']['pickup_schedule']);

        $created = $this->postJson(
            '/api/v1/member/inventory/returns',
            $returnPayload,
        )->assertOk();
        $returnId = (int) $created->json('data.id');

        $this->configureStc();
        Http::fake([
            'https://stc.example.test/api/shipping/price-express' => Http::response([
                'status' => true,
                'details' => [],
                'results' => [[
                    'service' => 'JNE',
                    'service_name' => 'Regpack',
                    'service_type' => 'REGPACK',
                    'cost' => 25000,
                    'etd' => '2-3 hari',
                    'drop' => true,
                    'force_insurance' => false,
                    'insurance' => 0,
                ]],
            ]),
            'https://stc.example.test/api/shipping/schedule-express' => Http::response([
                'status' => true,
                'schedules' => [[
                    'clock' => now()->addDay()->setTime(14, 0)->toDateTimeString(),
                    'until' => '22:00',
                    'expired' => now()->addDay()->endOfDay()->timestamp,
                    'libur' => false,
                ]],
            ]),
        ]);
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $this->getJson(
            "/api/v1/admin/transactions/returns/{$returnId}/shipping/return_company/couriers?couriers[]=JNE",
        )->assertOk()
            ->assertJsonPath('data.reference_type', 'return_company')
            ->assertJsonPath('data.origin.district_id', 357801)
            ->assertJsonPath('data.destination.district_id', 317101)
            ->assertJsonPath('data.items.0.quantity', 1)
            ->assertJsonPath('data.items.0.total_weight', 100)
            ->assertJsonPath('data.items.0.subtotal_value', 100000)
            ->assertJsonPath('data.package.weight', 100)
            ->assertJsonPath('data.package.item_value', 100000)
            ->assertJsonPath('data.results.0.courier_code', 'JNE')
            ->assertJsonPath('data.results.0.cost', 25000);

        $this->getJson(
            "/api/v1/admin/transactions/returns/{$returnId}/shipping/return_company/schedules",
        )->assertOk()
            ->assertJsonPath('data.reference_type', 'return_company')
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.is_available', true);

        DB::table('return')->where('return_id', $returnId)->update([
            'return_status' => 'received_by_company',
        ]);
        DB::table('return_detail')->where('return_detail_return_id', $returnId)->update([
            'return_detail_received_qty' => 1,
        ]);
        $this->getJson(
            "/api/v1/admin/transactions/returns/{$returnId}/shipping/return_replacement/couriers?couriers[]=jne",
        )->assertOk()
            ->assertJsonPath('data.reference_type', 'return_replacement')
            ->assertJsonPath('data.origin.district_id', 317101)
            ->assertJsonPath('data.destination.district_id', 357801)
            ->assertJsonPath('data.results.0.courier_code', 'JNE');

        Http::assertSent(fn (Request $request): bool => $request->url()
            === 'https://stc.example.test/api/shipping/price-express'
            && $request['origin'] === 357801
            && $request['destination'] === 317101
            && $request['courier'] === ['jne']);
        Http::assertSent(fn (Request $request): bool => $request->url()
            === 'https://stc.example.test/api/shipping/price-express'
            && $request['origin'] === 317101
            && $request['destination'] === 357801
            && $request['courier'] === ['jne']);
    }

    public function test_member_can_get_return_couriers_before_submitting_return(): void
    {
        [$account] = $this->createReturnData();
        $this->configureStc();
        Http::fake([
            'https://stc.example.test/api/shipping/price-express' => Http::response([
                'status' => true,
                'details' => [],
                'results' => [[
                    'service' => 'JNE',
                    'service_name' => 'Regpack',
                    'service_type' => 'REGPACK',
                    'cost' => 25000,
                    'etd' => '2-3 hari',
                    'drop' => true,
                    'force_insurance' => false,
                    'insurance' => 0,
                ]],
            ]),
            'https://stc.example.test/api/shipping/schedule-express' => Http::response([
                'status' => true,
                'schedules' => [[
                    'clock' => now()->addDay()->setTime(14, 0)->toDateTimeString(),
                    'until' => '22:00',
                    'expired' => now()->addDay()->endOfDay()->timestamp,
                    'libur' => false,
                ]],
            ]),
        ]);

        $this->actingAs($account, 'member_api');
        $payload = [
            'goods_receive_id' => 1,
            'address_id' => 1,
            'couriers' => ['JNE'],
            'items' => [[
                'product_id' => 1,
                'quantity' => 1,
                'batch_number' => 'BATCH-RET-001',
            ]],
        ];
        $this->postJson('/api/v1/member/inventory/returns/shipping/couriers', $payload)
            ->assertOk()
            ->assertJsonPath('data.reference_type', 'return_company')
            ->assertJsonPath('data.origin.district_id', 357801)
            ->assertJsonPath('data.destination.district_id', 317101)
            ->assertJsonPath('data.results.0.courier_code', 'JNE');
        $this->postJson('/api/v1/member/inventory/returns/shipping/schedules', $payload)
            ->assertOk()
            ->assertJsonPath('data.reference_type', 'return_company')
            ->assertJsonPath('data.results.0.is_available', true);
    }

    public function test_preorder_receipts_cannot_be_returned(): void
    {
        [$account, $product] = $this->createReturnData();
        DB::table('trx')->where('trx_id', 1)->update(['trx_is_preorder' => 1]);

        $this->actingAs($account, 'member_api');
        $this->getJson('/api/v1/member/inventory/returns/eligible-receipts')
            ->assertOk()
            ->assertJsonCount(0, 'data.results');
        $this->postJson('/api/v1/member/inventory/returns', $this->returnPayload($product->getKey()))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath('message', 'Pesanan PO tidak dapat diajukan retur.');
    }

    public function test_return_workflow_is_completed_without_creating_a_new_transaction(): void
    {
        [$account, $product] = $this->createReturnData();
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/inventory/returns/eligible-receipts')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.transaction.id', 1)
            ->assertJsonPath('data.results.0.summary.remaining_quantity', 2);

        $created = $this->postJson(
            '/api/v1/member/inventory/returns',
            $this->returnPayload($product->getKey()),
        )->assertOk()
            ->assertJsonPath('data.status.code', 'submitted')
            ->assertJsonPath('data.pickup_address.address_id', 1)
            ->assertJsonPath('data.return_shipping.method', 'courier_express')
            ->assertJsonPath('data.return_shipping.delivery_note_number', 'SJ-RETUR-EXPRESS-001')
            ->assertJsonPath('data.items.0.goods_receive_detail_id', 1)
            ->assertJsonPath('data.items.0.batch_number', 'BATCH-RET-001')
            ->assertJsonPath('data.summary.total_quantity', 1);
        $returnId = (int) $created->json('data.id');

        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 2,
            'member_stock_transfer_out' => 1,
        ]);
        $this->assertDatabaseHas('return', [
            'return_id' => $returnId,
            'return_goods_receive_id' => 1,
            'return_member_address_id' => 1,
            'return_status' => 'submitted',
        ]);
        $this->assertDatabaseHas('return_detail', [
            'return_detail_return_id' => $returnId,
            'return_detail_goods_receive_detail_id' => 1,
            'return_detail_product_id' => $product->getKey(),
        ]);
        $this->getJson('/api/v1/member/purchases/goods-receipts/1')
            ->assertOk()
            ->assertJsonPath('data.received_items.0.goods_receive_detail_id', 1)
            ->assertJsonPath('data.received_items.0.returned_quantity', 1)
            ->assertJsonPath('data.received_items.0.remaining_return_quantity', 1);
        $this->assertDatabaseCount('trx', 1);

        $administrator = $this->createAdministrator();
        $this->configureStc();
        $pickupSchedule = now()->addDay()->setTime(14, 0)->toDateTimeString();
        Http::fake([
            'https://stc.example.test/api/shipping/schedule-express' => Http::response([
                'status' => true,
                'schedules' => [[
                    'clock' => $pickupSchedule,
                    'until' => '22:00',
                    'expired' => now()->addDay()->endOfDay()->timestamp,
                    'libur' => false,
                ]],
            ]),
            'https://stc.example.test/api/shipping/pickup-express' => Http::sequence()->push([
                'status' => true,
                'pickup_number' => 'PU-RET-IN-001',
                'details' => [['order_id' => 'STC-RET-IN-001', 'awb' => 'AWB-RET-IN-001']],
            ])->push([
                'status' => true,
                'pickup_number' => 'PU-RET-OUT-001',
                'details' => [['order_id' => 'STC-RET-OUT-001', 'awb' => 'AWB-RET-OUT-001']],
            ]),
        ]);
        $this->actingAs($administrator, 'admin_api');

        $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/approve",
            ['note' => 'Retur disetujui.'],
        )->assertOk()
            ->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('notification', [
            'notification_user_type' => 'member',
            'notification_user_id' => $account->member_account_member_id,
            'notification_title' => 'Pengajuan Retur Disetujui',
            'notification_ref_table' => 'return',
            'notification_ref_id' => $returnId,
            'notification_is_read' => 0,
        ]);

        $this->actingAs($account, 'member_api');
        $this->getJson("/api/v1/member/inventory/returns/{$returnId}/shipping/schedules")
            ->assertOk()
            ->assertJsonPath('data.reference_type', 'return_company')
            ->assertJsonPath('data.results.0.time', $pickupSchedule)
            ->assertJsonPath('data.results.0.is_available', true);
        $this->postJson("/api/v1/member/inventory/returns/{$returnId}/ship", [
            'pickup_schedule' => $pickupSchedule,
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'waiting_member_shipment')
            ->assertJsonPath('data.return_shipping.tracking_number', 'AWB-RET-IN-001')
            ->assertJsonPath('data.return_shipping.items.0.batch_number', 'BATCH-RET-001')
            ->assertJsonPath('data.actions.can_ship_return', false);

        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 2,
            'member_stock_transfer_out' => 1,
        ]);
        $this->assertDatabaseHas('shipping_courier_express', [
            'shipping_courier_express_ref_type' => 'return_company',
            'shipping_courier_express_ref_id' => $returnId,
            'shipping_courier_express_order_id' => 'STC-RET-IN-001',
            'shipping_courier_express_insurance_is_force' => 0,
            'shipping_courier_express_insurance' => 0,
        ]);
        Http::assertSent(fn (Request $request): bool => $request->url()
            === 'https://stc.example.test/api/shipping/pickup-express'
            && $request['packages'][0]['destination_kelurahan_id'] === 31710101);

        $this->actingAs($administrator, 'admin_api');
        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'shipped_packages',
            'data' => [[
                'order_id' => 'STC-RET-IN-001',
                'awb' => 'AWB-RET-IN-001',
                'shipped_at' => now()->toDateTimeString(),
            ]],
        ])->assertOk()
            ->assertJsonPath('data.results.0.reference_type', 'return_company');
        $this->assertDatabaseHas('return', [
            'return_id' => $returnId,
            'return_status' => 'return_in_transit',
        ]);

        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/receive", [
            'delivery_note_number' => 'SJ-RETUR-EXPRESS-001',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_note_number');

        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/receive")
            ->assertOk()
            ->assertJsonPath('data.status', 'received_by_company')
            ->assertJsonPath('data.actions.can_ship_replacement', true);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 1,
            'member_stock_transfer_out' => 0,
        ]);

        $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/replacement/ship",
            $this->manualCourierPayload('JNE-PENGGANTI-TIDAK-VALID'),
        )->assertUnprocessable()
            ->assertJsonValidationErrors('shipping_method');

        $replacementResponse = $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/replacement/ship",
            $this->courierPayload('Kirim barang pengganti.', false),
        )->assertOk()
            ->assertJsonPath('data.status', 'replacement_in_transit');
        $this->assertDatabaseHas('notification', [
            'notification_user_type' => 'member',
            'notification_user_id' => $account->member_account_member_id,
            'notification_title' => 'Barang Pengganti Dikirim',
            'notification_ref_table' => 'return',
            'notification_ref_id' => $returnId,
            'notification_is_read' => 0,
        ]);

        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 4,
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 1,
            'member_stock_transfer_in' => 1,
        ]);
        $this->assertDatabaseHas('shipping_courier_express', [
            'shipping_courier_express_ref_type' => 'return_replacement',
            'shipping_courier_express_ref_id' => $returnId,
            'shipping_courier_express_order_id' => 'STC-RET-OUT-001',
        ]);
        $replacementResponse
            ->assertJsonPath('data.replacement_shipping.tracking_number', 'AWB-RET-OUT-001')
            ->assertJsonPath('data.replacement_shipping.items.0.batch_number', 'BATCH-PENGGANTI-001');
        $this->assertDatabaseCount('trx', 1);

        $this->actingAs($account, 'member_api');
        $this->postJson("/api/v1/member/inventory/returns/{$returnId}/replacement/receive")
            ->assertOk()
            ->assertJsonPath('data.status.code', 'completed')
            ->assertJsonPath('data.actions.can_confirm_replacement', false);

        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 2,
            'member_stock_transfer_in' => 0,
            'member_stock_transfer_out' => 0,
        ]);
        $this->assertDatabaseHas('return', [
            'return_id' => $returnId,
            'return_status' => 'completed',
        ]);
        $this->assertDatabaseCount('trx', 1);
    }

    public function test_member_return_summary_and_action_list_only_count_member_actions(): void
    {
        [$account, $product] = $this->createReturnData();
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/inventory/returns/summary')
            ->assertOk()
            ->assertJsonPath('data.eligible', 1)
            ->assertJsonPath('data.action_required', 0)
            ->assertJsonPath('data.history', 0)
            ->assertJsonPath('data.total_actions', 1);

        $returnId = (int) $this->postJson(
            '/api/v1/member/inventory/returns',
            $this->returnPayload($product->getKey()),
        )->assertOk()->json('data.id');

        $this->getJson('/api/v1/member/inventory/returns/eligible-receipts')
            ->assertOk()
            ->assertJsonCount(0, 'data.results');
        $this->getJson('/api/v1/member/inventory/returns/summary')
            ->assertOk()
            ->assertJsonPath('data.eligible', 0)
            ->assertJsonPath('data.action_required', 0)
            ->assertJsonPath('data.history', 1)
            ->assertJsonPath('data.total_actions', 0);

        DB::table('return')->where('return_id', $returnId)->update([
            'return_status' => 'approved',
        ]);

        $this->getJson('/api/v1/member/inventory/returns?filter[action_required]=true')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.actions.can_ship_return', true);
        $this->getJson('/api/v1/member/inventory/returns/summary')
            ->assertOk()
            ->assertJsonPath('data.action_required', 1)
            ->assertJsonPath('data.total_actions', 1);
    }

    public function test_member_can_submit_and_complete_pickup_return_flow_with_verification_pin(): void
    {
        [$account, $product] = $this->createReturnData();
        $this->actingAs($account, 'member_api');

        // Member submits pickup return with delivery note and received batch number.
        $created = $this->postJson(
            '/api/v1/member/inventory/returns',
            $this->pickupReturnPayload($product->getKey()),
        )->assertOk()
            ->assertJsonPath('data.status.code', 'submitted')
            ->assertJsonPath('data.return_shipping.method', 'pickup')
            ->assertJsonPath('data.actions.can_show_pickup_code', true);

        $returnId = (int) $created->json('data.id');
        $pickupPin = (string) $created->json('data.return_shipping.pin');
        $this->assertNotEmpty($pickupPin);
        $this->assertSame(5, strlen($pickupPin));
        $this->assertSame($pickupPin, (string) $created->json('data.actions.pickup_code'));

        $this->assertDatabaseHas('return', [
            'return_id' => $returnId,
            'return_status' => 'submitted',
            'return_shipping_method' => 'pickup',
        ]);
        $this->assertDatabaseHas('shipping_pickup', [
            'shipping_pickup_ref_type' => 'return_company',
            'shipping_pickup_ref_id' => $returnId,
            'shipping_pickup_pin' => $pickupPin,
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 2,
            'member_stock_transfer_out' => 1,
        ]);

        // Admin approves and receives the pickup return by validating its PIN.
        $administrator = $this->createAdministrator();
        $this->actingAs($administrator, 'admin_api');

        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/approve", [
            'note' => 'Retur disetujui, silakan serahkan barang ke gudang.',
            'pickup_pin' => '00000',
        ])->assertStatus(422)
            ->assertJsonPath('errors.pickup_pin.0', 'PIN pickup tidak sesuai.');

        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/approve", [
            'note' => 'Retur disetujui, silakan serahkan barang ke gudang.',
            'pickup_pin' => $pickupPin,
        ])->assertOk()
            ->assertJsonPath('data.status', 'received_by_company')
            ->assertJsonPath('data.actions.requires_pickup_pin', false)
            ->assertJsonPath('data.actions.can_ship_replacement', true);

        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 1,
            'member_stock_transfer_out' => 0,
        ]);

        // Admin ships replacement
        $this->configureStc();
        Http::fake([
            'https://stc.example.test/api/shipping/pickup-express' => Http::response([
                'status' => true,
                'pickup_number' => 'PU-RET-OUT-002',
                'details' => [['order_id' => 'STC-RET-OUT-002', 'awb' => 'AWB-RET-OUT-002']],
            ]),
        ]);

        $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/replacement/ship",
            $this->courierPayload('Kirim barang pengganti pickup retur.', false),
        )->assertOk()
            ->assertJsonPath('data.status', 'replacement_in_transit');

        // Member receives replacement
        $this->actingAs($account, 'member_api');
        $this->postJson("/api/v1/member/inventory/returns/{$returnId}/replacement/receive")
            ->assertOk()
            ->assertJsonPath('data.status.code', 'completed');

        $this->assertDatabaseHas('return', [
            'return_id' => $returnId,
            'return_status' => 'completed',
        ]);
    }

    public function test_pickup_approval_backfills_missing_reservation_for_legacy_submitted_return(): void
    {
        [$account, $product] = $this->createReturnData();
        $this->actingAs($account, 'member_api');

        $created = $this->postJson(
            '/api/v1/member/inventory/returns',
            $this->pickupReturnPayload($product->getKey()),
        )->assertOk();
        $returnId = (int) $created->json('data.id');
        $pickupPin = (string) $created->json('data.return_shipping.pin');

        DB::table('member_stock')
            ->where('member_stock_member_id', 1)
            ->where('member_stock_product_id', $product->getKey())
            ->update(['member_stock_transfer_out' => 0]);

        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/approve", [
            'pickup_pin' => $pickupPin,
            'note' => 'Retur lama disetujui dan diterima.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'received_by_company');

        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 1,
            'member_stock_transfer_out' => 0,
        ]);
    }

    public function test_admin_rejection_releases_stock_reserved_when_return_is_submitted(): void
    {
        [$account, $product] = $this->createReturnData();
        $this->actingAs($account, 'member_api');
        $returnId = (int) $this->postJson(
            '/api/v1/member/inventory/returns',
            $this->returnPayload($product->getKey()),
        )->assertOk()->json('data.id');
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 2,
            'member_stock_transfer_out' => 1,
        ]);

        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/reject", [
            'note' => 'Bukti kerusakan tidak sesuai.',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 2,
            'member_stock_transfer_out' => 0,
        ]);
        $this->assertDatabaseCount('member_stock_log', 0);
    }

    public function test_return_shipping_may_be_partial_or_split_but_cannot_exceed_return(): void
    {
        [$account, $product] = $this->createReturnData();
        $this->actingAs($account, 'member_api');
        $returnPayload = $this->returnPayload($product->getKey());
        $returnPayload['items'][0]['quantity'] = 2;
        $returnId = (int) $this->postJson('/api/v1/member/inventory/returns', $returnPayload)
            ->assertOk()
            ->json('data.id');

        $this->actingAs($this->createAdministrator(), 'admin_api');
        $invalidShipment = $this->manualCourierPayload('JNE-RETUR-SPLIT-001');
        $invalidShipment['shipping_cost_bearer'] = 'warehouse';
        $invalidShipment['items'][0]['quantity'] = 3;
        $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/approve",
            $invalidShipment,
        )->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');
        $this->assertDatabaseCount('shipping_courier_manual', 0);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_transfer_out' => 2,
        ]);

        $validShipment = $invalidShipment;
        $validShipment['items'] = [
            [
                'product_id' => $product->getKey(),
                'quantity' => 1,
                'batch_number' => 'BATCH-RETUR-SPLIT-A',
                'expiry_date' => now()->addYear()->toDateString(),
            ],
            [
                'product_id' => $product->getKey(),
                'quantity' => 1,
                'batch_number' => 'BATCH-RETUR-SPLIT-B',
                'expiry_date' => now()->addYears(2)->toDateString(),
            ],
        ];
        $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/approve",
            $validShipment,
        )->assertOk()
            ->assertJsonCount(2, 'data.return_shipping.items')
            ->assertJsonPath('data.return_shipping.items.0.batch_number', 'BATCH-RETUR-SPLIT-A')
            ->assertJsonPath('data.return_shipping.items.1.batch_number', 'BATCH-RETUR-SPLIT-B');
    }

    public function test_company_may_receive_less_than_the_requested_return_quantity(): void
    {
        [$account, $product] = $this->createReturnData();
        $this->actingAs($account, 'member_api');
        $returnPayload = $this->returnPayload($product->getKey());
        $returnPayload['items'][0]['quantity'] = 2;
        $returnId = (int) $this->postJson('/api/v1/member/inventory/returns', $returnPayload)
            ->assertOk()
            ->json('data.id');
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->configureStc();
        Http::fake([
            'https://stc.example.test/api/shipping/price-express' => Http::response([
                'status' => true,
                'details' => [],
                'results' => [[
                    'service' => 'JNE',
                    'service_name' => 'Regpack',
                    'service_type' => 'REGPACK',
                    'cost' => 25000,
                    'etd' => '2-3 hari',
                    'drop' => true,
                    'force_insurance' => false,
                    'insurance' => 0,
                ]],
            ]),
        ]);
        $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/shipping/return_company/couriers",
            [
                'couriers' => ['jne'],
                'items' => [[
                    'product_id' => $product->getKey(),
                    'quantity' => 1,
                ]],
            ],
        )->assertOk()
            ->assertJsonPath('data.items.0.quantity', 1)
            ->assertJsonPath('data.package.weight', 100)
            ->assertJsonPath('data.package.item_value', 100000);
        $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/shipping/return_company/couriers",
            [
                'items' => [[
                    'product_id' => $product->getKey(),
                    'quantity' => 3,
                ]],
            ],
        )->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        $approval = $this->manualCourierPayload('JNE-RETUR-PARSIAL-001');
        $approval['shipping_cost_bearer'] = 'warehouse';
        $approval['items'][0]['quantity'] = 1;
        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/approve", $approval)
            ->assertOk()
            ->assertJsonPath('data.return_shipping.items.0.quantity', 1);

        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/receive")
            ->assertOk()
            ->assertJsonPath('data.status', 'received_by_company')
            ->assertJsonPath('data.summary.requested_quantity', 2)
            ->assertJsonPath('data.summary.received_quantity', 1)
            ->assertJsonPath('data.summary.not_received_quantity', 1)
            ->assertJsonPath('data.summary.replacement_quantity', 1)
            ->assertJsonPath('data.details.0.received_quantity', 1);

        $this->assertDatabaseHas('return_detail', [
            'return_detail_return_id' => $returnId,
            'return_detail_qty' => 2,
            'return_detail_received_qty' => 1,
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 1,
            'member_stock_transfer_out' => 0,
        ]);
        $replacementCourierPayload = [
            'couriers' => ['jne'],
            'items' => [[
                'product_id' => $product->getKey(),
                'quantity' => 1,
            ]],
        ];
        $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/shipping/return_replacement/couriers",
            $replacementCourierPayload,
        )->assertOk()
            ->assertJsonPath('data.items.0.quantity', 1)
            ->assertJsonPath('data.package.weight', 100)
            ->assertJsonPath('data.package.item_value', 100000);
        $replacementCourierPayload['items'][0]['quantity'] = 2;
        $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/shipping/return_replacement/couriers",
            $replacementCourierPayload,
        )->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/replacement/ship",
            $this->manualCourierPayload('JNE-PENGGANTI-PARSIAL-001'),
        )->assertOk()
            ->assertJsonPath('data.replacement_shipping.items.0.quantity', 1);
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 4,
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_transfer_in' => 1,
        ]);
    }

    public function test_return_and_replacement_support_manual_shipping(): void
    {
        [$account, $product] = $this->createReturnData();
        $this->actingAs($account, 'member_api');
        $returnId = (int) $this->postJson(
            '/api/v1/member/inventory/returns',
            $this->returnPayload($product->getKey()),
        )->assertOk()->json('data.id');

        $this->actingAs($this->createAdministrator(), 'admin_api');
        $approval = $this->manualCourierPayload('JNE-RETUR-001');
        $approval['shipping_cost_bearer'] = 'warehouse';
        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/approve", $approval)
            ->assertOk()
            ->assertJsonPath('data.return_shipping.method', 'courier_manual')
            ->assertJsonPath('data.return_shipping.tracking_number', 'JNE-RETUR-001');
        $this->assertDatabaseHas('shipping_courier_manual', [
            'shipping_courier_manual_ref_type' => 'return_company',
            'shipping_courier_manual_ref_id' => $returnId,
            'shipping_courier_manual_awb' => 'JNE-RETUR-001',
        ]);

        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/receive")
            ->assertOk();
        $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/replacement/ship",
            $this->manualCourierPayload('JNE-PENGGANTI-001'),
        )->assertOk()
            ->assertJsonPath('data.replacement_shipping.method', 'courier_manual')
            ->assertJsonPath('data.replacement_shipping.tracking_number', 'JNE-PENGGANTI-001');
        $this->assertDatabaseHas('shipping_courier_manual', [
            'shipping_courier_manual_ref_type' => 'return_replacement',
            'shipping_courier_manual_ref_id' => $returnId,
        ]);
    }

    public function test_return_supports_pickup_at_company(): void
    {
        [$account, $product] = $this->createReturnData();
        $payload = $this->pickupReturnPayload($product->getKey());

        $this->actingAs($account, 'member_api');
        $created = $this->postJson(
            '/api/v1/member/inventory/returns',
            $payload,
        )->assertOk()
            ->assertJsonPath('data.return_shipping.method', 'pickup');
        $returnId = (int) $created->json('data.id');
        $pickupPin = (string) $created->json('data.return_shipping.pin');

        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/approve", [
            'note' => 'Retur pickup disetujui.',
        ])->assertStatus(422)
            ->assertJsonPath('errors.pickup_pin.0', 'Kode verifikasi pickup wajib diisi saat menyetujui retur ambil di tempat.');

        $approved = $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/approve", [
            'note' => 'Retur pickup disetujui.',
            'pickup_pin' => $pickupPin,
        ])->assertOk()
            ->assertJsonPath('data.return_shipping.method', 'pickup')
            ->assertJsonPath('data.return_shipping.location.id', 1)
            ->assertJsonPath('data.return_shipping.delivery_status', 'completed')
            ->assertJsonPath('data.actions.requires_pickup_pin', false);
        $this->assertDatabaseHas('shipping_pickup', [
            'shipping_pickup_ref_type' => 'return_company',
            'shipping_pickup_ref_id' => $returnId,
        ]);

        $this->assertDatabaseHas('shipping_pickup_status', [
            'shipping_pickup_status_ref_type' => 'return_company',
            'shipping_pickup_status_ref_id' => $returnId,
            'shipping_pickup_status_value' => 'picked_up',
        ]);
        $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/replacement/ship",
            [
                'delivery_note_number' => 'SJ-PENGGANTI-PICKUP-001',
                'shipping_method' => 'pickup',
                'items' => [[
                    'product_id' => $product->getKey(),
                    'quantity' => 1,
                    'batch_number' => 'BATCH-PENGGANTI-PICKUP-001',
                    'expiry_date' => now()->addYear()->toDateString(),
                ]],
                'courier' => [],
            ],
        )->assertOk()
            ->assertJsonPath('data.replacement_shipping.method', 'pickup')
            ->assertJsonPath(
                'data.replacement_shipping.delivery_note_number',
                'SJ-PENGGANTI-PICKUP-001',
            )
            ->assertJsonPath('data.replacement_shipping.delivery_status', 'ready_to_pickup');

        $this->actingAs($account, 'member_api');
        $this->postJson("/api/v1/member/inventory/returns/{$returnId}/replacement/receive")
            ->assertOk()
            ->assertJsonPath('data.status.code', 'completed');
    }

    public function test_return_supports_instant_shipping_and_callback(): void
    {
        [$account, $product] = $this->createReturnData();
        $this->actingAs($account, 'member_api');
        $returnId = (int) $this->postJson(
            '/api/v1/member/inventory/returns',
            $this->returnPayload($product->getKey()),
        )->assertOk()->json('data.id');

        $this->configureStc();
        Http::fake([
            'https://stc.example.test/api/shipping/pickup-instant' => Http::sequence()->push([
                'message' => 'OK',
                'error' => null,
                'data' => ['results' => [
                    'status' => true,
                    'details' => [[
                        'order_id' => 'STC-INSTANT-RETUR-001',
                        'awb' => 'AWB-INSTANT-RETUR-001',
                        'status' => 105,
                    ]],
                ]],
            ])->push([
                'message' => 'OK',
                'error' => null,
                'data' => ['results' => [
                    'status' => true,
                    'details' => [[
                        'order_id' => 'STC-INSTANT-PENGGANTI-001',
                        'awb' => 'AWB-INSTANT-PENGGANTI-001',
                        'status' => 105,
                    ]],
                ]],
            ]),
        ]);
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/approve", [
            'delivery_note_number' => 'SJ-RETUR-INSTANT-001',
            'shipping_cost_bearer' => 'warehouse',
            'shipping_method' => 'courier_instant',
            'items' => [[
                'product_id' => $product->getKey(),
                'quantity' => 1,
                'batch_number' => 'BATCH-RETUR-INSTANT-001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
            'courier' => [
                'name' => 'gosend',
                'service' => 'instant',
                'type' => 'instant',
                'cost' => 18000,
                'vehicle' => 'motor',
                'etd' => '1-2 jam',
                'admin_fee' => 1500,
                'origin_latitude' => -7.26,
                'origin_longitude' => 112.74,
                'destination_latitude' => -6.22,
                'destination_longitude' => 106.8,
            ],
        ])->assertOk()
            ->assertJsonPath('data.return_shipping.method', 'courier_instant')
            ->assertJsonPath('data.return_shipping.order_id', 'STC-INSTANT-RETUR-001');

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'shipped_packages',
            'data' => [[
                'order_id' => 'STC-INSTANT-RETUR-001',
                'awb' => 'AWB-INSTANT-RETUR-001',
                'shipped_at' => now()->toDateTimeString(),
            ]],
        ])->assertOk()
            ->assertJsonPath('data.results.0.reference_type', 'return_company');
        $this->assertDatabaseHas('return', [
            'return_id' => $returnId,
            'return_status' => 'return_in_transit',
        ]);

        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/receive")
            ->assertOk();
        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/replacement/ship", [
            'delivery_note_number' => 'SJ-PENGGANTI-INSTANT-001',
            'shipping_method' => 'courier_instant',
            'items' => [[
                'product_id' => $product->getKey(),
                'quantity' => 1,
                'batch_number' => 'BATCH-PENGGANTI-INSTANT-001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
            'courier' => [
                'name' => 'gosend',
                'service' => 'instant',
                'type' => 'instant',
                'cost' => 18000,
                'vehicle' => 'motor',
                'etd' => '1-2 jam',
                'admin_fee' => 1500,
                'origin_latitude' => -6.22,
                'origin_longitude' => 106.8,
                'destination_latitude' => -7.26,
                'destination_longitude' => 112.74,
            ],
        ])->assertOk()
            ->assertJsonPath('data.replacement_shipping.method', 'courier_instant')
            ->assertJsonPath(
                'data.replacement_shipping.order_id',
                'STC-INSTANT-PENGGANTI-001',
            )
            ->assertJsonPath(
                'data.replacement_shipping.items.0.batch_number',
                'BATCH-PENGGANTI-INSTANT-001',
            );

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'shipped_packages',
            'data' => [[
                'order_id' => 'STC-INSTANT-PENGGANTI-001',
                'awb' => 'AWB-INSTANT-PENGGANTI-001',
                'shipped_at' => now()->toDateTimeString(),
            ]],
        ])->assertOk()
            ->assertJsonPath('data.results.0.reference_type', 'return_replacement');

        $this->actingAs($account, 'member_api');
        $this->postJson("/api/v1/member/inventory/returns/{$returnId}/replacement/receive", [
            'delivery_note_number' => 'SJ-PENGGANTI-INSTANT-001',
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'completed');
    }

    public function test_failed_return_shipping_releases_stock_and_can_be_retried(): void
    {
        [$account, $product] = $this->createReturnData();
        $this->actingAs($account, 'member_api');
        $returnId = (int) $this->postJson(
            '/api/v1/member/inventory/returns',
            $this->returnPayload($product->getKey()),
        )->assertOk()->json('data.id');

        $this->configureStc();
        Http::fake([
            'https://stc.example.test/api/shipping/pickup-express' => Http::response([
                'status' => true,
                'pickup_number' => 'PU-FAILED-001',
                'details' => [['order_id' => 'STC-FAILED-001', 'awb' => 'AWB-FAILED-001']],
            ]),
        ]);
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/approve",
            $this->courierPayload('Kirim retur.'),
        )->assertOk();

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'canceled_packages',
            'data' => [[
                'order_id' => 'STC-FAILED-001',
                'reason' => 'Kurir tidak tersedia',
            ]],
        ])->assertOk();
        $this->assertDatabaseHas('return', [
            'return_id' => $returnId,
            'return_status' => 'return_shipping_failed',
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 2,
            'member_stock_transfer_out' => 0,
        ]);

        $retry = $this->manualCourierPayload('JNE-RETRY-001');
        $retry['shipping_cost_bearer'] = 'warehouse';
        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/approve", $retry)
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting_member_shipment')
            ->assertJsonPath('data.return_shipping.method', 'courier_manual')
            ->assertJsonPath('data.actions.can_receive', true);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_transfer_out' => 1,
        ]);
    }

    public function test_failed_replacement_shipping_restores_stock_and_can_be_retried(): void
    {
        [$account, $product] = $this->createReturnData();
        $this->actingAs($account, 'member_api');
        $returnId = (int) $this->postJson(
            '/api/v1/member/inventory/returns',
            $this->returnPayload($product->getKey()),
        )->assertOk()->json('data.id');

        $this->actingAs($this->createAdministrator(), 'admin_api');
        $approval = $this->manualCourierPayload('JNE-INBOUND-001');
        $approval['shipping_cost_bearer'] = 'warehouse';
        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/approve", $approval)
            ->assertOk();
        $this->postJson("/api/v1/admin/transactions/returns/{$returnId}/receive")
            ->assertOk();

        $this->configureStc();
        Http::fake([
            'https://stc.example.test/api/shipping/pickup-express' => Http::response([
                'status' => true,
                'pickup_number' => 'PU-REPLACEMENT-FAILED-001',
                'details' => [[
                    'order_id' => 'STC-REPLACEMENT-FAILED-001',
                    'awb' => 'AWB-REPLACEMENT-FAILED-001',
                ]],
            ]),
        ]);
        $this->postJson(
            "/api/v1/admin/transactions/returns/{$returnId}/replacement/ship",
            $this->courierPayload('Kirim pengganti.', false),
        )->assertOk();
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_transfer_in' => 1,
        ]);

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'returned_packages',
            'data' => [[
                'order_id' => 'STC-REPLACEMENT-FAILED-001',
                'reason' => 'Paket kembali ke pengirim',
            ]],
        ])->assertOk();
        $this->assertDatabaseHas('return', [
            'return_id' => $returnId,
            'return_status' => 'replacement_shipping_failed',
        ]);
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 5,
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_transfer_in' => 0,
        ]);
        $this->getJson("/api/v1/admin/transactions/returns/{$returnId}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_ship_replacement', true);
    }

    public function test_return_items_are_scoped_to_the_selected_goods_receive_batch(): void
    {
        [$account, $product] = $this->createReturnData();
        DB::table('goods_receive')->insert([
            'goods_receive_id' => 2,
            'goods_receive_number' => 'GR/000002/A7K9Q2',
            'goods_receive_trx_id' => 1,
            'goods_receive_buyer_type' => 'distributor',
            'goods_receive_buyer_id' => 1,
            'goods_receive_seller_type' => 'warehouse',
            'goods_receive_seller_id' => 1,
            'goods_receive_status' => 'completed',
            'goods_receive_status_datetime' => now(),
            'goods_receive_created_datetime' => now(),
        ]);
        DB::table('goods_receive_detail')->insert([
            'goods_receive_detail_id' => 2,
            'goods_receive_detail_receive_id' => 2,
            'goods_receive_detail_product_id' => $product->getKey(),
            'goods_receive_detail_batch_number' => 'BATCH-LAIN-001',
            'goods_receive_detail_expire_date' => now()->addYear(),
            'goods_receive_detail_qty' => 1,
            'goods_receive_detail_created_datetime' => now(),
        ]);
        $this->actingAs($account, 'member_api');

        $payload = $this->returnPayload($product->getKey());
        $payload['items'][0]['goods_receive_detail_id'] = 2;
        $this->postJson('/api/v1/member/inventory/returns', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath(
                'message',
                'Batch produk retur tidak terdapat pada penerimaan barang yang dipilih.',
            );
    }

    public function test_return_deadline_uses_grouped_json_configuration(): void
    {
        [$account, $product] = $this->createReturnData();
        BusinessConfig::put('return', ['return_max_days' => 1]);
        DB::table('goods_receive')->where('goods_receive_id', 1)->update([
            'goods_receive_status_datetime' => now()->subDays(2),
            'goods_receive_created_datetime' => now()->subDays(2),
        ]);
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/inventory/returns/eligible-receipts')
            ->assertOk()
            ->assertJsonCount(0, 'data.results');
        $this->postJson('/api/v1/member/inventory/returns', $this->returnPayload($product->getKey()))
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath('message', 'Batas pengajuan retur maksimal 1 hari setelah barang diterima.');
    }

    public function test_receipt_and_address_must_belong_to_authenticated_member(): void
    {
        [$account, $product] = $this->createReturnData();
        $otherAccount = $this->createMemberAccount(2, 'DNY000002', 'return.other');
        $this->actingAs($otherAccount, 'member_api');

        $this->postJson('/api/v1/member/inventory/returns', $this->returnPayload($product->getKey()))
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        $this->actingAs($account, 'member_api');
        $payload = $this->returnPayload($product->getKey());
        DB::table('member_address')->insert([
            'member_address_id' => 2,
            'member_address_member_id' => 2,
            'member_address_label' => 'Gudang',
            'member_address_recipient' => 'Member Lain',
            'member_address_phone' => '081200000002',
            'member_address_full' => 'Alamat member lain',
            'member_address_province_id' => 35,
            'member_address_city_id' => 3578,
            'member_address_district_id' => 357801,
            'member_address_subdistrict_id' => 35780101,
            'member_address_country_id' => 1,
        ]);
        $payload['address_id'] = 2;
        $this->postJson('/api/v1/member/inventory/returns', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');
    }

    /** @return array{MemberAccount, Product} */
    private function createReturnData(): array
    {
        $this->createRegionalReferences();
        $account = $this->createMemberAccount();
        DB::table('member_address')->insert([
            'member_address_id' => 1,
            'member_address_member_id' => 1,
            'member_address_label' => 'Gudang',
            'member_address_recipient' => 'Distributor 1',
            'member_address_phone' => '081234567890',
            'member_address_full' => 'Jalan Member Surabaya',
            'member_address_province_id' => 35,
            'member_address_city_id' => 3578,
            'member_address_district_id' => 357801,
            'member_address_subdistrict_id' => 35780101,
            'member_address_country_id' => 1,
            'member_address_is_default' => 1,
        ]);
        DB::table('warehouse')->insert([
            'warehouse_id' => 1,
            'warehouse_name' => 'Gudang Pusat',
            'warehouse_legal_name' => 'PT DNY',
            'warehouse_phone' => '0211234567',
            'warehouse_address' => 'Jalan Company Jakarta',
            'warehouse_province_id' => 31,
            'warehouse_city_id' => 3171,
            'warehouse_district_id' => 317101,
            'warehouse_subdistrict_id' => 31710101,
        ]);
        $category = ProductCategory::query()->create([
            'product_category_name' => 'Skincare',
            'product_category_is_active' => 1,
        ]);
        $product = Product::query()->create([
            'product_product_category_id' => $category->getKey(),
            'product_code' => 'SRM-RET-001',
            'product_name' => 'Serum Retur',
            'product_customer_price' => 150000,
            'product_weight' => 100,
            'product_length' => 10,
            'product_width' => 10,
            'product_height' => 10,
            'product_unit' => 'pcs',
            'product_is_publish' => 1,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
        ]);
        DB::table('trx')->insert([
            'trx_id' => 1,
            'trx_code' => 'TRX/CMP/DST/000001/A7K9Q2',
            'trx_seller_type' => 'warehouse',
            'trx_seller_id' => 1,
            'trx_buyer_type' => 'distributor',
            'trx_buyer_id' => 1,
            'trx_type' => 'stock',
            'trx_total_price' => 200000,
            'trx_grand_total_price' => 200000,
            'trx_grand_total_nett_price' => 200000,
            'trx_bill_amount' => 200000,
            'trx_payment_method' => 'transfer',
            'trx_shipping_method' => 'courier_manual',
            'trx_status' => 'received',
            'trx_status_datetime' => now(),
            'trx_datetime' => now()->subDay(),
        ]);
        DB::table('trx_detail')->insert([
            'trx_detail_id' => 1,
            'trx_detail_trx_id' => 1,
            'trx_detail_product_id' => $product->getKey(),
            'trx_detail_product_code' => $product->product_code,
            'trx_detail_product_name' => $product->product_name,
            'trx_detail_product_price' => 100000,
            'trx_detail_nett_price' => 100000,
            'trx_detail_qty' => 2,
        ]);
        DB::table('goods_receive')->insert([
            'goods_receive_id' => 1,
            'goods_receive_number' => 'GR/000001/A7K9Q2',
            'goods_receive_trx_id' => 1,
            'goods_receive_buyer_type' => 'distributor',
            'goods_receive_buyer_id' => 1,
            'goods_receive_seller_type' => 'warehouse',
            'goods_receive_seller_id' => 1,
            'goods_receive_status' => 'completed',
            'goods_receive_status_datetime' => now(),
            'goods_receive_created_datetime' => now(),
        ]);
        DB::table('goods_receive_detail')->insert([
            'goods_receive_detail_id' => 1,
            'goods_receive_detail_receive_id' => 1,
            'goods_receive_detail_product_id' => $product->getKey(),
            'goods_receive_detail_batch_number' => 'BATCH-RET-001',
            'goods_receive_detail_expire_date' => now()->addYear(),
            'goods_receive_detail_qty' => 2,
            'goods_receive_detail_created_datetime' => now(),
        ]);
        DB::table('member_stock')->insert([
            'member_stock_id' => 1,
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 2,
        ]);
        DB::table('warehouse_stock')->insert([
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 5,
        ]);

        return [$account, $product];
    }

    private function createMemberAccount(
        int $memberId = 1,
        string $code = 'DNY000001',
        string $username = 'return.distributor',
    ): MemberAccount {
        $group = MemberGroup::query()->firstOrCreate(
            ['member_group_name' => 'Mitra'],
            ['member_group_description' => 'Grup akses mitra', 'member_group_is_active' => 1],
        );
        $member = Member::query()->create([
            'member_id' => $memberId,
            'member_code' => $code,
            'member_member_level_id' => 1,
            'member_parent_member_id' => 0,
            'member_name' => "Distributor {$memberId}",
            'member_email' => "return{$memberId}@example.test",
            'member_mobilephone' => '081234567890',
            'member_address' => 'Jalan Member Surabaya',
            'member_province_id' => 35,
            'member_city_id' => 3578,
            'member_district_id' => 357801,
            'member_subdistrict_id' => 35780101,
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(),
            'member_account_member_group_id' => $group->getKey(),
            'member_account_username' => $username,
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
    }

    /** @return array<string, mixed> */
    private function returnPayload(int $productId): array
    {
        return [
            'goods_receive_id' => 1,
            'address_id' => 1,
            'description' => 'Kemasan produk rusak saat diterima.',
            'image_urls' => ['https://cdn.example.test/returns/damaged.webp'],
            'delivery_note_number' => 'SJ-RETUR-EXPRESS-001',
            'shipping_method' => 'courier_express',
            'courier' => [
                'courier_code' => 'JNE',
                'courier_name' => 'Regpack',
                'service_type' => 'REGPACK',
                'cost' => 25000,
                'etd' => '2-3 hari',
                'drop_off_available' => true,
                'force_insurance' => false,
                'insurance' => 2500,
                'pickup_method' => 'PICKUP',
                'pickup_schedule' => now()->addDay()->toDateTimeString(),
            ],
            'items' => [[
                'product_id' => $productId,
                'quantity' => 1,
                'batch_number' => 'BATCH-RET-001',
                'reason' => 'Segel kemasan pecah.',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function pickupReturnPayload(int $productId): array
    {
        return [
            'goods_receive_id' => 1,
            'address_id' => 1,
            'description' => 'Barang retur akan diserahkan langsung ke gudang.',
            'image_urls' => ['https://cdn.example.test/returns/damaged.webp'],
            'delivery_note_number' => 'SJ-RETUR-PICKUP-001',
            'shipping_method' => 'pickup',
            'items' => [[
                'product_id' => $productId,
                'quantity' => 1,
                'batch_number' => 'BATCH-RET-001',
                'reason' => 'Segel kemasan pecah.',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function courierPayload(string $note, bool $includeBearer = true): array
    {
        $payload = [
            'delivery_note_number' => $includeBearer
                ? 'SJ-RETUR-EXPRESS-001'
                : 'SJ-PENGGANTI-EXPRESS-001',
            'note' => $note,
            'shipping_method' => 'courier_express',
            'items' => [[
                'product_id' => 1,
                'quantity' => 1,
                'batch_number' => $includeBearer
                    ? 'BATCH-RETUR-KIRIM-001'
                    : 'BATCH-PENGGANTI-001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
            'courier' => [
                'name' => 'JNE',
                'service' => 'REG',
                'type' => 'REG',
                'cost' => 25000,
                'etd' => '2-3 hari',
                'force_insurance' => false,
                'insurance' => 2500,
                'pickup_method' => 'PICKUP',
                'pickup_schedule' => now()->addDay()->toDateTimeString(),
            ],
        ];
        if ($includeBearer) {
            $payload['shipping_cost_bearer'] = 'warehouse';
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function manualCourierPayload(string $trackingNumber): array
    {
        return [
            'delivery_note_number' => str_contains($trackingNumber, 'PENGGANTI')
                ? 'SJ-PENGGANTI-MANUAL-001'
                : 'SJ-RETUR-MANUAL-001',
            'shipping_method' => 'courier_manual',
            'items' => [[
                'product_id' => 1,
                'quantity' => 1,
                'batch_number' => str_contains($trackingNumber, 'PENGGANTI')
                    ? 'BATCH-PENGGANTI-MANUAL-001'
                    : 'BATCH-RETUR-MANUAL-001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
            'courier' => [
                'name' => 'JNE',
                'service' => 'REG',
                'cost' => 20000,
                'tracking_number' => $trackingNumber,
            ],
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
            'administrator_username' => 'return.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Admin Retur',
            'administrator_email' => 'return.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }

    private function configureStc(): void
    {
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-token',
            'services.stc.client_id' => 9,
            'services.stc.client_code' => 'DNY',
            'services.stc.callback_token' => '',
        ]);
    }

    private function createRegionalReferences(): void
    {
        DB::table('ref_province')->insertOrIgnore([
            ['province_id' => '31', 'province_name' => 'DKI Jakarta', 'province_is_active' => 1],
            ['province_id' => '35', 'province_name' => 'Jawa Timur', 'province_is_active' => 1],
        ]);
        DB::table('ref_city')->insertOrIgnore([
            ['city_id' => '3171', 'city_province_id' => '31', 'city_name' => 'Jakarta Selatan', 'city_type' => 'Kota', 'city_is_active' => 1],
            ['city_id' => '3578', 'city_province_id' => '35', 'city_name' => 'Surabaya', 'city_type' => 'Kota', 'city_is_active' => 1],
        ]);
        DB::table('ref_district')->insertOrIgnore([
            ['district_id' => '317101', 'district_city_id' => '3171', 'district_name' => 'Kebayoran Baru'],
            ['district_id' => '357801', 'district_city_id' => '3578', 'district_name' => 'Tegalsari'],
        ]);
        DB::table('ref_subdistrict')->insertOrIgnore([
            ['subdistrict_id' => 31710101, 'subdistrict_district_id' => 317101, 'subdistrict_name' => 'Senayan', 'subdistrict_zip_code' => 12190],
            ['subdistrict_id' => 35780101, 'subdistrict_district_id' => 357801, 'subdistrict_name' => 'Kedungdoro', 'subdistrict_zip_code' => 60261],
        ]);
    }
}
