<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Mail\OrderShippedMail;
use App\Mail\PaymentApprovedMail;
use App\Mail\PaymentRejectedMail;
use App\Models\Member;
use App\Models\Product;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Models\Trx;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminTransactionInventoryWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shipment_list_and_detail_include_origin_and_destination_snapshots(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();
        Trx::query()->findOrFail(1)->update(['trx_status' => 'processing']);
        DB::table('shipping_courier_manual')->where('shipping_courier_manual_ref_id', 1)->update([
            'shipping_courier_manual_origin_subdistrict_id' => 31710101,
            'shipping_courier_manual_origin_subdistrict_name' => 'Gambir',
            'shipping_courier_manual_origin_district_name' => 'Gambir',
            'shipping_courier_manual_origin_city_name' => 'Jakarta Pusat',
            'shipping_courier_manual_origin_province_name' => 'DKI Jakarta',
            'shipping_courier_manual_origin_zipcode' => '10110',
            'shipping_courier_manual_destination_subdistrict_id' => 35780101,
            'shipping_courier_manual_destination_subdistrict_name' => 'Airlangga',
            'shipping_courier_manual_destination_district_name' => 'Gubeng',
            'shipping_courier_manual_destination_city_name' => 'Surabaya',
            'shipping_courier_manual_destination_province_name' => 'Jawa Timur',
            'shipping_courier_manual_destination_zipcode' => '60286',
        ]);

        $this->getJson('/api/v1/admin/inventory/shipments?filter[buyer.type]=distributor')
            ->assertOk()
            ->assertJsonPath('data.results.0.seller.origin.name', 'Gudang Pusat')
            ->assertJsonPath('data.results.0.seller.origin.province.name', 'DKI Jakarta')
            ->assertJsonPath('data.results.0.seller.origin.postal_code', '10110')
            ->assertJsonPath('data.results.0.buyer.destination.name', 'Distributor Utama')
            ->assertJsonPath('data.results.0.buyer.destination.code', 'DST-001')
            ->assertJsonPath('data.results.0.buyer.destination.city.name', 'Surabaya')
            ->assertJsonPath('data.results.0.buyer.destination.province.name', 'Jawa Timur');

        $this->getJson('/api/v1/admin/inventory/shipments/1')
            ->assertOk()
            ->assertJsonPath('data.seller.origin.phone', '0211234567')
            ->assertJsonPath('data.seller.origin.subdistrict.id', 31710101)
            ->assertJsonPath('data.buyer.destination.phone', '081234567890')
            ->assertJsonPath('data.buyer.destination.code', 'DST-001')
            ->assertJsonPath('data.buyer.destination.district.name', 'Gubeng')
            ->assertJsonPath('data.buyer.destination.postal_code', '60286');
    }

    public function test_orders_can_be_filtered_by_nested_buyer_fields(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();

        Member::query()->create([
            'member_id' => 2,
            'member_code' => 'AGT-002',
            'member_member_level_id' => 2,
            'member_parent_member_id' => 1,
            'member_name' => 'Agent Dua',
            'member_join_datetime' => '2026-07-02 08:00:00',
            'member_status' => 1,
        ]);
        DB::table('trx')->insert([
            ...$this->trxPayload(),
            'trx_id' => 2,
            'trx_code' => 'TRX-002',
            'trx_buyer_type' => 'agent',
            'trx_buyer_id' => 2,
            'trx_is_preorder' => 1,
            'trx_discount' => 5,
            'trx_discount_value' => 10000,
            'trx_voucher_id' => 7,
            'trx_voucher_value' => 3000,
            'trx_grand_total_price' => 187000,
            'trx_shipping_cost' => 20000,
            'trx_payment_charge' => 2500,
            'trx_grand_total_nett_price' => 209500,
            'trx_bill_amount' => 209500,
        ]);

        $this->getJson('/api/v1/admin/transactions/orders?filter[buyer.type]=agent&filter[buyer.id]=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.code', 'TRX-002')
            ->assertJsonPath('data.results.0.buyer.type', 'agent')
            ->assertJsonPath('data.results.0.buyer.id', 2)
            ->assertJsonPath('data.results.0.totals.product_total', 200000)
            ->assertJsonPath('data.results.0.totals.discount_percent', 5)
            ->assertJsonPath('data.results.0.totals.discount_value', 10000)
            ->assertJsonPath('data.results.0.totals.product_discount', 10000)
            ->assertJsonPath('data.results.0.totals.voucher_id', 7)
            ->assertJsonPath('data.results.0.totals.voucher_value', 3000)
            ->assertJsonPath('data.results.0.totals.after_discount', 187000)
            ->assertJsonPath('data.results.0.totals.shipping_cost', 20000)
            ->assertJsonPath('data.results.0.totals.payment_charge', 2500)
            ->assertJsonPath('data.results.0.totals.grand_total', 209500)
            ->assertJsonPath('data.results.0.totals.bill_amount', 209500);

        $this->getJson('/api/v1/admin/transactions/orders?filter[is_preorder]=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.code', 'TRX-002')
            ->assertJsonPath('data.results.0.order_type.code', 'preorder');

        $this->getJson('/api/v1/admin/transactions/orders?filter[code][like]=%25TRX-002%25&filter[ordered_at][gte]=2026-07-01&filter[ordered_at][lt]=2026-08-01')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.code', 'TRX-002');
    }

    public function test_payments_can_be_filtered_by_nested_and_legacy_buyer_type_fields(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();
        $this->createPayment();

        Member::query()->create([
            'member_id' => 2,
            'member_code' => 'AGT-002',
            'member_member_level_id' => 2,
            'member_parent_member_id' => 1,
            'member_name' => 'Agent Dua',
            'member_join_datetime' => '2026-07-02 08:00:00',
            'member_status' => 1,
        ]);
        DB::table('trx')->insert([
            ...$this->trxPayload(),
            'trx_id' => 2,
            'trx_code' => 'TRX-002',
            'trx_buyer_type' => 'agent',
            'trx_buyer_id' => 2,
        ]);
        DB::table('trx_payment_transfer')->insert([
            'payment_transfer_id' => 2,
            'payment_transfer_trx_id' => 2,
            'payment_transfer_bill_amount' => 200000,
            'payment_transfer_bank_id' => 1,
            'payment_transfer_account_name' => 'PT DNY',
            'payment_transfer_account_number' => '123456',
            'payment_transfer_amount' => 200000,
            'payment_transfer_datetime' => '2026-07-11 09:00:00',
            'payment_transfer_receipt_file' => 'https://example.test/bukti-agent.webp',
            'payment_transfer_approval_status' => 'submitted',
            'payment_transfer_note' => '',
        ]);

        $this->getJson('/api/v1/admin/transactions/payments?filter[buyer.type]=distributor')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.payment.id', 1)
            ->assertJsonPath('data.results.0.buyer.type', 'distributor')
            ->assertJsonPath('data.results.0.payment.bank.code', 'BCA')
            ->assertJsonPath('data.results.0.payment.bank.name', 'Bank Central Asia')
            ->assertJsonPath('data.results.0.payment.bank.account_name', 'PT DNY')
            ->assertJsonPath('data.results.0.payment.bank.account_number', '123456')
            ->assertJsonPath('data.results.0.payment.amount', 200000)
            ->assertJsonPath('data.results.0.payment.status', 'submitted')
            ->assertJsonPath('data.results.0.payment.spread_payment', null)
            ->assertJsonPath('data.results.0.items', [])
            ->assertJsonMissingPath('data.results.0.bank_id');

        $paymentDetail = $this->getJson('/api/v1/admin/transactions/payments/1')
            ->assertOk()
            ->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.code', 'TRX-001')
            ->assertJsonPath('data.seller.type', 'warehouse')
            ->assertJsonPath('data.seller.name', 'Pusat')
            ->assertJsonPath('data.seller.origin.address', 'Jakarta')
            ->assertJsonPath('data.buyer.destination.address', 'Surabaya')
            ->assertJsonPath('data.shipping.method', 'courier_manual')
            ->assertJsonPath('data.shipping.courier', 'JNE')
            ->assertJsonPath('data.shipping.service', 'REG')
            ->assertJsonPath('data.payment.id', 1)
            ->assertJsonPath('data.payment.bank.name', 'Bank Central Asia')
            ->assertJsonPath('data.payment.bank.account_number', '123456')
            ->assertJsonPath('data.payment.amount', 200000)
            ->assertJsonPath('data.payment.status', 'submitted')
            ->assertJsonPath('data.payment.spread_payment', null)
            ->assertJsonPath('data.details.0.product.name', 'Serum DNY')
            ->assertJsonPath('data.details.0.product.bpom_number', 'NA18250100999')
            ->assertJsonMissingPath('data.transaction')
            ->assertJsonMissingPath('data.items')
            ->assertJsonMissingPath('data.bill_amount')
            ->assertJsonMissingPath('data.bank_id');

        $this->getJson('/api/v1/admin/transactions/orders?filter[buyer.type]=distributor&filter[buyer.id]=1')
            ->assertOk()
            ->assertJsonPath('data.results.0.payment.bank.name', 'Bank Central Asia')
            ->assertJsonPath('data.results.0.payment.bank.account_name', 'PT DNY')
            ->assertJsonPath('data.results.0.payment.bank.account_number', '123456')
            ->assertJsonPath('data.results.0.payment.receipt_url', 'https://example.test/bukti.webp');

        $orderDetail = $this->getJson('/api/v1/admin/transactions/orders/1')
            ->assertOk()
            ->assertJsonPath('data.payment.bank.name', 'Bank Central Asia')
            ->assertJsonPath('data.payment.bank.account_number', '123456')
            ->assertJsonPath('data.details.0.product.bpom_number', 'NA18250100999');

        $shippingDetail = $this->getJson('/api/v1/admin/inventory/shipments/1')
            ->assertOk()
            ->assertJsonPath('data.details.0.product.bpom_number', 'NA18250100999');

        $this->assertSame(
            $orderDetail->json('data'),
            $paymentDetail->json('data'),
        );
        $this->assertSame(
            $orderDetail->json('data'),
            $shippingDetail->json('data'),
        );

        $this->getJson('/api/v1/admin/transactions/payments?filter[buyer_type]=agent')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.payment.id', 2)
            ->assertJsonPath('data.results.0.buyer.type', 'agent');

        $this->getJson('/api/v1/admin/transactions/payments?search=TRX-002&field_search=transaction.code')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.transaction.code', 'TRX-002');
    }

    public function test_payments_can_exclude_a_nested_transaction_status(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();
        $this->createPayment();

        DB::table('trx')->insert([
            ...$this->trxPayload(),
            'trx_id' => 2,
            'trx_code' => 'TRX-SCREENING',
            'trx_status' => 'waiting_stock_screening',
        ]);
        DB::table('trx_payment_transfer')->insert([
            'payment_transfer_id' => 2,
            'payment_transfer_trx_id' => 2,
            'payment_transfer_bill_amount' => 200000,
            'payment_transfer_bank_id' => 1,
            'payment_transfer_account_name' => 'PT DNY',
            'payment_transfer_account_number' => '123456',
            'payment_transfer_amount' => 0,
            'payment_transfer_datetime' => '2026-07-11 09:00:00',
            'payment_transfer_approval_status' => 'pending',
            'payment_transfer_note' => '',
        ]);

        $this->getJson('/api/v1/admin/transactions/payments?page=1&limit=10'
            .'&filter[buyer.type]=distributor'
            .'&filter[transaction.status][ne]=waiting_stock_screening'
            .'&field_search=transaction.code')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.transaction.code', 'TRX-001')
            ->assertJsonPath('data.results.0.transaction.status', 'waiting_payment');
    }

    public function test_preorder_type_and_origin_chain_are_consistent_in_admin_transaction_details(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();

        Member::query()->create([
            'member_id' => 2,
            'member_code' => 'DNY000002',
            'member_member_level_id' => 2,
            'member_parent_member_id' => 1,
            'member_name' => 'Bima Agent',
            'member_join_datetime' => '2026-07-02 08:00:00',
            'member_status' => 1,
        ]);
        Member::query()->create([
            'member_id' => 3,
            'member_code' => 'DNY000003',
            'member_member_level_id' => 3,
            'member_parent_member_id' => 2,
            'member_name' => 'Citra Reseller',
            'member_join_datetime' => '2026-07-03 08:00:00',
            'member_status' => 1,
        ]);
        DB::table('trx')->insert([
            [
                ...$this->trxPayload(),
                'trx_id' => 2,
                'trx_code' => 'TRX/AGT/RSL/000008/ROOT01',
                'trx_parent_trx_id' => 0,
                'trx_is_preorder' => 1,
                'trx_seller_type' => 'agent',
                'trx_seller_id' => 2,
                'trx_buyer_type' => 'reseller',
                'trx_buyer_id' => 3,
            ],
            [
                ...$this->trxPayload(),
                'trx_id' => 3,
                'trx_code' => 'TRX/DST/AGT/000008/CHILD1',
                'trx_parent_trx_id' => 2,
                'trx_is_preorder' => 1,
                'trx_seller_type' => 'distributor',
                'trx_seller_id' => 1,
                'trx_buyer_type' => 'agent',
                'trx_buyer_id' => 2,
            ],
        ]);
        Trx::query()->findOrFail(1)->update([
            'trx_code' => 'TRX/CMP/DST/000008/FINAL1',
            'trx_parent_trx_id' => 3,
            'trx_is_preorder' => true,
        ]);
        $this->createPayment();

        $this->getJson('/api/v1/admin/transactions/payments?filter[buyer.type]=distributor')
            ->assertOk()
            ->assertJsonPath('data.results.0.transaction.order_type.code', 'preorder')
            ->assertJsonPath('data.results.0.transaction.order_type.label', 'PO')
            ->assertJsonPath('data.results.0.transaction.is_preorder', true);

        $responses = [
            $this->getJson('/api/v1/admin/transactions/payments/1'),
            $this->getJson('/api/v1/admin/transactions/orders/1'),
            $this->getJson('/api/v1/admin/inventory/shipments/1'),
        ];

        foreach ($responses as $response) {
            $response
                ->assertOk()
                ->assertJsonPath('data.order_type.code', 'preorder')
                ->assertJsonPath('data.order_type.label', 'PO')
                ->assertJsonPath('data.preorder.current_transaction_id', 1)
                ->assertJsonPath('data.preorder.origin.transaction.id', 2)
                ->assertJsonPath('data.preorder.origin.transaction.code', 'TRX/AGT/RSL/000008/ROOT01')
                ->assertJsonPath('data.preorder.origin.buyer.type', 'reseller')
                ->assertJsonPath('data.preorder.origin.buyer.code', 'DNY000003')
                ->assertJsonPath('data.preorder.origin.buyer.name', 'Citra Reseller')
                ->assertJsonCount(3, 'data.preorder.chain')
                ->assertJsonPath('data.preorder.chain.0.buyer.type', 'reseller')
                ->assertJsonPath('data.preorder.chain.0.seller.type', 'agent')
                ->assertJsonPath('data.preorder.chain.0.transaction.payment_status', 'pending')
                ->assertJsonPath('data.preorder.chain.1.buyer.type', 'agent')
                ->assertJsonPath('data.preorder.chain.1.seller.type', 'distributor')
                ->assertJsonPath('data.preorder.chain.1.transaction.payment_status', 'pending')
                ->assertJsonPath('data.preorder.chain.2.buyer.type', 'distributor')
                ->assertJsonPath('data.preorder.chain.2.seller.type', 'warehouse')
                ->assertJsonPath('data.preorder.chain.2.transaction.payment_status', 'submitted')
                ->assertJsonPath('data.preorder.chain.2.transaction.payment_status_label', 'Menunggu Verifikasi');
        }
    }

    public function test_stock_adjustment_and_order_fulfillment_workflow(): void
    {
        Mail::fake();
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();
        Member::query()->findOrFail(1)->update(['member_email' => 'distributor@example.test']);

        $adjustment = $this->postJson('/api/v1/admin/inventory/adjustments', [
            'warehouse_id' => 1,
            'note' => 'Koreksi hasil stok fisik',
            'details' => [[
                'product_id' => 1,
                'batch_number' => 'BATCH-ADJ-001',
                'type' => 'out',
                'quantity' => 3,
                'unit_price' => 100000,
                'note' => 'Barang rusak',
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.total_quantity', 3)
            ->assertJsonPath('data.details.0.batch_number', 'BATCH-ADJ-001')
            ->assertJsonPath('data.details.0.type', 'out');
        $this->assertMatchesRegularExpression(
            '~^ADJ/\d{6}/[A-Z0-9]{6}$~',
            (string) $adjustment->json('data.code'),
        );
        $this->assertDatabaseHas('warehouse_stock', ['warehouse_stock_id' => 1, 'warehouse_stock_balance' => 7]);
        $this->assertDatabaseHas('warehouse_stock_log', ['warehouse_stock_log_balance' => 7]);
        $this->assertDatabaseHas('warehouse_stock_adjustment_detail', [
            'stock_adjustment_detail_batch_number' => 'BATCH-ADJ-001',
        ]);

        $this->getJson('/api/v1/admin/inventory/mutations?filter[product_id]=1')
            ->assertOk()
            ->assertJsonPath('data.results.0.quantity', 3);

        Trx::query()->findOrFail(1)->update([
            'trx_status' => 'waiting_payment_approval',
            'trx_status_datetime' => now(),
        ]);
        $this->createPayment();

        $this->postJson('/api/v1/admin/transactions/payments/1/approve', ['note' => 'Nominal sesuai'])
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'approved');
        $this->assertDatabaseHas('trx', ['trx_id' => 1, 'trx_status' => 'processing']);
        $this->assertDatabaseHas('reward_point_annual', [
            'reward_point_annual_member_id' => 1,
            'reward_point_annual_year' => now()->year,
            'reward_point_annual_total_points' => 2,
        ]);
        $this->assertDatabaseHas('reward_point_annual_log', [
            'reward_point_annual_log_member_id' => 1,
            'reward_point_annual_log_trx_id' => 1,
            'reward_point_annual_log_points' => 2,
        ]);
        Mail::assertSent(PaymentApprovedMail::class, function (PaymentApprovedMail $mail): bool {
            return $mail->orderNumber === 'TRX-001'
                && $mail->buyerName === 'Distributor Utama';
        });

        $this->postJson('/api/v1/admin/inventory/shipments/1/ship')
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation');

        $invalidExpiryResponse = $this->postJson('/api/v1/admin/inventory/shipments/1/ship', [
            'delivery_note_number' => 'SJ-ADMIN-INVALID',
            'items' => [[
                'product_id' => 1,
                'quantity' => 2,
                'batch_number' => 'BATCH-HARI-INI',
                'expiry_date' => today()->toDateString(),
            ]],
            'tracking_number' => 'JNE-ADMIN-INVALID',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation');
        $this->assertSame(
            'Tanggal kedaluwarsa batch harus setelah hari ini.',
            $invalidExpiryResponse->json('errors')['items.0.expiry_date'][0] ?? null,
        );

        $shipmentExpiryDate = now()->addYear()->toDateString();
        $this->postJson('/api/v1/admin/inventory/shipments/1/ship', [
            'delivery_note_number' => 'SJ-ADMIN-001',
            'items' => [[
                'product_id' => 1,
                'quantity' => 2,
                'batch_number' => 'BATCH-SHIP-001',
                'expiry_date' => $shipmentExpiryDate,
            ]],
            'tracking_number' => 'JNE-ADMIN-001',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.shipping.tracking_number', 'JNE-ADMIN-001');
        $this->getJson('/api/v1/admin/inventory/shipments/1')
            ->assertOk()
            ->assertJsonPath('data.details.0.product.batches.0.quantity', 2)
            ->assertJsonPath('data.details.0.product.batches.0.batch_number', 'BATCH-SHIP-001')
            ->assertJsonPath('data.details.0.product.batches.0.expiry_date', $shipmentExpiryDate);
        Mail::assertSent(OrderShippedMail::class, function (OrderShippedMail $mail): bool {
            return $mail->orderNumber === 'TRX-001'
                && $mail->courier === 'JNE'
                && $mail->trackingNumber === 'JNE-ADMIN-001';
        });

        $this->getJson('/api/v1/admin/transactions/orders/1/document')
            ->assertOk()
            ->assertJsonPath('data.details.0.points', 2);
    }

    public function test_payment_rejection_cancels_transaction_and_releases_stock(): void
    {
        Mail::fake();
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();
        Member::query()->findOrFail(1)->update(['member_email' => 'distributor@example.test']);
        Trx::query()->findOrFail(1)->update([
            'trx_status' => 'waiting_payment_approval',
            'trx_status_datetime' => now(),
        ]);
        $this->createPayment();

        $this->postJson('/api/v1/admin/transactions/payments/1/reject', ['note' => 'Bukti tidak sesuai'])
            ->assertOk()
            ->assertJsonPath('message', 'Pembayaran ditolak dan pesanan dibatalkan.')
            ->assertJsonPath('data.payment.status', 'rejected');
        $this->assertDatabaseHas('trx', ['trx_id' => 1, 'trx_status' => 'cancelled']);
        Mail::assertSent(PaymentRejectedMail::class, function (PaymentRejectedMail $mail): bool {
            return $mail->orderNumber === 'TRX-001'
                && str_contains($mail->rejectionReason, 'Bukti tidak sesuai')
                && $mail->orderCancelled;
        });
        $this->assertDatabaseHas('notification', [
            'notification_user_type' => 'member',
            'notification_user_id' => 1,
            'notification_title' => 'Pembayaran Ditolak',
            'notification_ref_table' => 'trx_purchase',
            'notification_ref_id' => 1,
            'notification_is_read' => 0,
        ]);

    }

    public function test_admin_can_request_stc_pickup_for_warehouse_shipment(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();
        $this->createRegionalReferences();
        DB::table('warehouse')->where('warehouse_id', 1)->update([
            'warehouse_phone' => '081111111111',
        ]);
        Trx::query()->findOrFail(1)->update([
            'trx_shipping_method' => 'courier_express',
            'trx_status' => 'processing',
            'trx_status_datetime' => now(),
        ]);
        DB::table('shipping_courier_express')->insert([
            'shipping_courier_express_ref_type' => 'trx',
            'shipping_courier_express_ref_id' => 1,
            'shipping_courier_express_type' => 'REGPACK',
            'shipping_courier_express_expedition_name' => 'JNE',
            'shipping_courier_express_expedition_service' => 'Regpack',
            'shipping_courier_express_origin_name' => 'Distributor Utama',
            'shipping_courier_express_origin_phone' => '0361500001',
            'shipping_courier_express_origin_address' => 'Jakarta',
            'shipping_courier_express_origin_subdistrict_id' => 31710101,
            'shipping_courier_express_destination_name' => 'Mitra Tujuan',
            'shipping_courier_express_destination_phone' => '82222222222',
            'shipping_courier_express_destination_address' => 'Surabaya',
            'shipping_courier_express_destination_subdistrict_id' => 35780101,
            'shipping_courier_express_package_weight' => 100,
            'shipping_courier_express_package_length' => 10,
            'shipping_courier_express_package_width' => 10,
            'shipping_courier_express_package_height' => 10,
        ]);
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-token',
            'services.stc.callback_token' => 'callback-secret',
            'services.stc.client_code' => 'DNY',
        ]);
        Http::fake([
            'https://stc.example.test/api/shipping/schedule-express' => Http::response([
                'status' => true,
                'schedules' => [
                    [
                        'clock' => '14:00',
                        'until' => '15:30',
                        'expired' => now()->addHour()->timestamp,
                        'libur' => false,
                    ],
                    [
                        'clock' => '17:00',
                        'until' => '18:30',
                        'expired' => now()->subHour()->timestamp,
                        'libur' => false,
                    ],
                    [
                        'clock' => '08:00',
                        'until' => '09:30',
                        'expired' => now()->addDay()->timestamp,
                        'libur' => true,
                    ],
                ],
            ]),
            'https://stc.example.test/api/shipping/pickup-express' => Http::response([
                'status' => true,
                'pickup_number' => 'PU-ADMIN-001',
                'details' => [['order_id' => 'STC-ADMIN-001', 'awb' => 'AWB-ADMIN-001']],
            ]),
            'https://stc.example.test/api/shipping/tracking-express' => Http::response([
                'message' => 'Paket sedang dalam perjalanan.',
                'error' => '',
                'data' => ['results' => [
                    'method' => 'shTracking',
                    'details' => [
                        'awb' => 'AWB-ADMIN-001',
                        'order_id' => 'STC-ADMIN-001',
                        'service' => 'jne',
                        'service_name' => 'REG',
                        'delivered' => false,
                    ],
                    'histories' => [[
                        'created_at' => '2026-09-02 10:00:00',
                        'status' => 'Paket sedang dalam perjalanan.',
                        'status_code' => 100,
                        'driver' => '',
                        'receiver' => '',
                    ]],
                ]],
            ]),
        ]);

        $this->getJson('/api/v1/admin/inventory/shipments/express/schedules')
            ->assertOk()
            ->assertJsonPath('data.results.0.time', '14:00')
            ->assertJsonPath('data.results.0.is_available', true)
            ->assertJsonPath('data.results.1.is_available', false)
            ->assertJsonPath('data.results.2.is_available', false);

        $schedule = now()->addDay()->setTime(14, 0)->toDateTimeString();
        $this->postJson('/api/v1/admin/inventory/shipments/1/ship', [
            'delivery_note_number' => 'SJ-EXPRESS-INVALID',
            'items' => [[
                'product_id' => 1,
                'quantity' => 2,
                'batch_number' => 'BATCH-EXPRESS-001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
            'tracking_number' => 'TIDAK-BOLEH-DIISI',
            'pickup_method' => 'PICKUP',
            'pickup_schedule' => $schedule,
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation');

        $this->postJson('/api/v1/admin/inventory/shipments/1/ship', [
            'delivery_note_number' => 'SJ-EXPRESS-001',
            'items' => [[
                'product_id' => 1,
                'quantity' => 2,
                'batch_number' => 'BATCH-EXPRESS-001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
            'pickup_method' => 'PICKUP',
            'pickup_schedule' => $schedule,
        ])->assertOk()
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.shipping.pickup_number', 'PU-ADMIN-001')
            ->assertJsonPath('data.shipping.tracking_number', 'AWB-ADMIN-001');

        $this->assertDatabaseHas('shipping_courier_express_status', [
            'shipping_courier_express_status_ref_type' => 'trx',
            'shipping_courier_express_status_ref_id' => 1,
            'shipping_courier_express_status_value' => 'processed_packages',
        ]);
        $this->getJson('/api/v1/admin/inventory/shipments?filter[buyer.type]=distributor')
            ->assertOk()
            ->assertJsonPath('data.results.0.actions.can_track', true)
            ->assertJsonPath('data.results.0.actions.can_simulate_finished', true)
            ->assertJsonPath('data.results.0.shipping.order_id', 'STC-ADMIN-001')
            ->assertJsonPath('data.results.0.shipping.tracking_number', 'AWB-ADMIN-001');
        $this->postJson('/api/v1/admin/inventory/shipments/1/tracking')
            ->assertOk()
            ->assertJsonPath('data.method', 'shTracking')
            ->assertJsonPath('data.details.awb', 'AWB-ADMIN-001')
            ->assertJsonPath('data.histories.0.status', 'Paket sedang dalam perjalanan.');
        $this->assertDatabaseCount('shipping_courier_express_status', 1);
        DB::table('shipping_courier_express')
            ->where('shipping_courier_express_ref_id', 1)
            ->update(['shipping_courier_express_awb' => null]);
        $this->getJson('/api/v1/admin/inventory/shipments?filter[buyer.type]=distributor')
            ->assertOk()
            ->assertJsonPath('data.results.0.actions.can_track', false)
            ->assertJsonPath('data.results.0.actions.can_simulate_finished', true);
        $this->postJson('/api/v1/admin/inventory/shipments/1/tracking')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'AWB belum tersedia untuk pengiriman ini.');

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'finished_packages',
            'data' => [[
                'order_id' => 'STC-ADMIN-001',
                'awb' => 'DEV-AWB-1-123456789',
                'date' => now()->toDateTimeString(),
                'finished_at' => now()->toDateTimeString(),
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.event', 'finished_packages')
            ->assertJsonPath('data.processed', 1);
        $this->getJson('/api/v1/admin/inventory/shipments/1')
            ->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.actions.can_simulate_finished', false)
            ->assertJsonPath('data.actions.can_track', true)
            ->assertJsonPath('data.shipping.tracking_number', 'DEV-AWB-1-123456789')
            ->assertJsonPath('data.shipping.delivery_status', 'finished_packages');
        $this->assertDatabaseHas('shipping_courier_express_status', [
            'shipping_courier_express_status_ref_type' => 'trx',
            'shipping_courier_express_status_ref_id' => 1,
            'shipping_courier_express_status_value' => 'finished_packages',
        ]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://stc.example.test/api/shipping/pickup-express'
            && $request['reference_id'] === 'TRX-001'
            && $request['phone'] === '6281111111111'
            && $request['packages'][0]['destination_phone'] === '6282222222222'
            && $request['packages'][0]['item_name'] === 'Skincare'
            && $request['packages'][0]['drop'] === false
        );
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://stc.example.test/api/shipping/tracking-express'
            && $request['order_id'] === 'STC-ADMIN-001'
        );
    }

    public function test_stc_phone_formatter_keeps_local_landline_prefix(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();
        $this->createRegionalReferences();
        Trx::query()->findOrFail(1)->update([
            'trx_shipping_method' => 'courier_express',
            'trx_status' => 'processing',
            'trx_status_datetime' => now(),
        ]);
        DB::table('shipping_courier_express')->insert([
            'shipping_courier_express_ref_type' => 'trx',
            'shipping_courier_express_ref_id' => 1,
            'shipping_courier_express_type' => 'REGPACK',
            'shipping_courier_express_expedition_name' => 'JNE',
            'shipping_courier_express_expedition_service' => 'Regpack',
            'shipping_courier_express_origin_name' => 'Gudang',
            'shipping_courier_express_origin_phone' => '211111111',
            'shipping_courier_express_origin_address' => 'Jakarta',
            'shipping_courier_express_origin_subdistrict_id' => 31710101,
            'shipping_courier_express_destination_name' => 'Klinik Tujuan',
            'shipping_courier_express_destination_phone' => '222222222',
            'shipping_courier_express_destination_address' => 'Surabaya',
            'shipping_courier_express_destination_subdistrict_id' => 35780101,
            'shipping_courier_express_package_weight' => 100,
            'shipping_courier_express_package_length' => 10,
            'shipping_courier_express_package_width' => 10,
            'shipping_courier_express_package_height' => 10,
        ]);
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-token',
            'services.stc.client_code' => 'DNY',
        ]);
        Http::fake([
            'https://stc.example.test/api/shipping/pickup-express' => Http::response([
                'status' => true,
                'pickup_number' => 'PU-ADMIN-LANDLINE',
                'details' => [['order_id' => 'STC-ADMIN-LANDLINE', 'awb' => 'AWB-ADMIN-LANDLINE']],
            ]),
        ]);

        $this->postJson('/api/v1/admin/inventory/shipments/1/ship', [
            'delivery_note_number' => 'SJ-LANDLINE-001',
            'items' => [[
                'product_id' => 1,
                'quantity' => 2,
                'batch_number' => 'BATCH-LANDLINE-001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
            'pickup_method' => 'PICKUP',
            'pickup_schedule' => now()->addDay()->setTime(14, 0)->toDateTimeString(),
        ])->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://stc.example.test/api/shipping/pickup-express'
            && $request['phone'] === '0211111111'
            && $request['packages'][0]['destination_phone'] === '0222222222'
        );
    }

    public function test_admin_can_choose_a_new_courier_and_reship_the_same_transaction(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();
        $this->createRegionalReferences();
        DB::table('shipping_courier_manual')->where('shipping_courier_manual_ref_id', 1)->delete();
        DB::table('warehouse_stock')->where('warehouse_stock_id', 1)->update([
            'warehouse_stock_balance' => 8,
            'warehouse_stock_transfer_out' => 0,
        ]);
        DB::table('member_stock')->insert([
            'member_stock_member_id' => 1,
            'member_stock_product_id' => 1,
            'member_stock_balance' => 0,
            'member_stock_transfer_in' => 2,
            'member_stock_transfer_out' => 0,
        ]);
        Trx::query()->findOrFail(1)->update([
            'trx_code' => 'TRX/CMP/DST/000060/4PNLIJ',
            'trx_shipping_method' => 'courier_express',
            'trx_status' => 'reship_required',
            'trx_status_datetime' => now(),
        ]);
        DB::table('shipping_courier_express')->insert([
            'shipping_courier_express_ref_type' => 'trx',
            'shipping_courier_express_ref_id' => 1,
            'shipping_courier_express_type' => 'REG23',
            'shipping_courier_express_expedition_name' => 'jne',
            'shipping_courier_express_expedition_service' => 'JNE Express Reguler',
            'shipping_courier_express_order_id' => 'STC-OLD-001',
            'shipping_courier_express_pickup_number' => 'PU-OLD-001',
            'shipping_courier_express_awb' => 'AWB-OLD-001',
            'shipping_courier_express_cost' => 18000,
            'shipping_courier_express_origin_name' => 'Gudang',
            'shipping_courier_express_origin_phone' => '6281111111111',
            'shipping_courier_express_origin_address' => 'Jakarta',
            'shipping_courier_express_origin_subdistrict_id' => 31710101,
            'shipping_courier_express_destination_name' => 'Distributor Utama',
            'shipping_courier_express_destination_phone' => '6282222222222',
            'shipping_courier_express_destination_address' => 'Surabaya',
            'shipping_courier_express_destination_subdistrict_id' => 35780101,
            'shipping_courier_express_package_weight' => 200,
            'shipping_courier_express_package_length' => 10,
            'shipping_courier_express_package_width' => 10,
            'shipping_courier_express_package_height' => 20,
        ]);
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-token',
            'services.stc.client_id' => 9,
            'services.stc.client_code' => 'DNY',
        ]);
        Http::fake([
            'https://stc.example.test/api/shipping/price-express' => Http::response([
                'status' => true,
                'details' => [],
                'results' => [[
                    'service' => 'jnt',
                    'service_name' => 'J&T EZ',
                    'service_type' => 'EZ',
                    'cost' => 19000,
                    'etd' => '1-2',
                    'drop' => true,
                    'force_insurance' => true,
                    'insurance' => 2000,
                    'logo' => 'https://example.test/jnt.png',
                ]],
            ]),
            'https://stc.example.test/api/shipping/pickup-express' => Http::response([
                'status' => true,
                'pickup_number' => 'PU-NEW-001',
                'details' => [['order_id' => 'STC-NEW-001', 'awb' => 'AWB-NEW-001']],
            ]),
        ]);

        $this->getJson('/api/v1/admin/inventory/shipments/1')
            ->assertOk()
            ->assertJsonPath('data.status', 'reship_required')
            ->assertJsonPath('data.actions.can_reship', true)
            ->assertJsonPath('data.actions.requires_courier_selection', true);
        $this->getJson('/api/v1/admin/inventory/shipments?filter[status]=reship_required')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.actions.can_reship', true);
        $this->getJson('/api/v1/admin/inventory/shipments/1/couriers')
            ->assertOk()
            ->assertJsonPath('data.results.0.courier_code', 'jnt')
            ->assertJsonPath('data.results.0.cost', 19000);

        $payload = [
            'delivery_note_number' => 'SJ/RESHIP/000001',
            'pickup_method' => 'PICKUP',
            'pickup_schedule' => now()->addDay()->setTime(14, 0)->toDateTimeString(),
            'courier' => [
                'courier_code' => 'jnt',
                'courier_name' => 'J&T EZ',
                'service_type' => 'EZ',
                'cost' => 19000,
                'etd' => '1-2',
                'drop_off_available' => true,
                'force_insurance' => true,
                'insurance' => 2000,
                'logo_url' => 'https://example.test/jnt.png',
            ],
            'items' => [[
                'product_id' => 1,
                'quantity' => 2,
                'batch_number' => 'BATCH-RESHIP-001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ];
        $this->postJson('/api/v1/admin/inventory/shipments/1/ship', collect($payload)->except('courier')->all())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('courier');
        $this->postJson('/api/v1/admin/inventory/shipments/1/ship', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.shipping.courier', 'jnt')
            ->assertJsonPath('data.shipping.pickup_number', 'PU-NEW-001');

        $this->assertDatabaseHas('shipping_courier_express', [
            'shipping_courier_express_ref_id' => 1,
            'shipping_courier_express_order_id' => 'STC-NEW-001',
            'shipping_courier_express_pickup_number' => 'PU-NEW-001',
            'shipping_courier_express_expedition_name' => 'jnt',
            'shipping_courier_express_cost' => 19000,
            'shipping_courier_express_insurance' => 2000,
        ]);
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_id' => 1,
            'warehouse_stock_balance' => 8,
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => 1,
            'member_stock_transfer_in' => 2,
        ]);
        $this->assertDatabaseHas('shipping_courier_express_status', [
            'shipping_courier_express_status_ref_id' => 1,
            'shipping_courier_express_status_value' => 'processed_packages',
            'shipping_courier_express_status_ref_code' => 'TRX/CMP/DST/000060/4PNLIJ-001',
        ]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://stc.example.test/api/shipping/pickup-express'
            && $request['reference_id'] === 'TRX/CMP/DST/000060/4PNLIJ-001'
        );

        Trx::query()->findOrFail(1)->update([
            'trx_status' => 'reship_required',
            'trx_status_datetime' => now(),
        ]);
        $this->postJson('/api/v1/admin/inventory/shipments/1/ship', $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped');
        $this->assertDatabaseHas('shipping_courier_express_status', [
            'shipping_courier_express_status_ref_id' => 1,
            'shipping_courier_express_status_value' => 'processed_packages',
            'shipping_courier_express_status_ref_code' => 'TRX/CMP/DST/000060/4PNLIJ-002',
        ]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://stc.example.test/api/shipping/pickup-express'
            && $request['reference_id'] === 'TRX/CMP/DST/000060/4PNLIJ-002'
        );
        $this->assertDatabaseCount('trx', 1);
    }

    public function test_only_terminal_preorder_seller_can_reship_an_order(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();
        Trx::query()->findOrFail(1)->update([
            'trx_status' => 'reship_required',
            'trx_status_datetime' => now(),
        ]);
        DB::table('trx')->insert([
            ...$this->trxPayload(),
            'trx_id' => 2,
            'trx_code' => 'TRX-PO-CHILD-002',
            'trx_parent_trx_id' => 1,
            'trx_status' => 'processing',
        ]);

        $this->getJson('/api/v1/admin/inventory/shipments/1')
            ->assertOk()
            ->assertJsonPath('data.status', 'reship_required')
            ->assertJsonPath('data.actions.can_ship', false)
            ->assertJsonPath('data.actions.can_reship', false);
        $this->getJson('/api/v1/admin/inventory/shipments?filter[status]=reship_required')
            ->assertOk()
            ->assertJsonPath('data.results.0.actions.can_ship', false)
            ->assertJsonPath('data.results.0.actions.can_reship', false);

        $this->postJson('/api/v1/admin/inventory/shipments/1/ship', [
            'delivery_note_number' => 'SJ/RESHIP/INTERMEDIATE',
            'tracking_number' => 'AWB-INTERMEDIATE',
            'items' => [[
                'product_id' => 1,
                'quantity' => 2,
                'batch_number' => 'BATCH-INTERMEDIATE',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ])->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Pesanan PO ini diteruskan ke seller berikutnya. Pengiriman hanya dilakukan oleh seller terakhir.',
            );
    }

    public function test_admin_can_request_instant_stc_pickup_for_distributor_shipment(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();
        Trx::query()->findOrFail(1)->update([
            'trx_seller_type' => 'distributor',
            'trx_shipping_method' => 'courier_instant',
            'trx_status' => 'processing',
            'trx_status_datetime' => now(),
        ]);
        DB::table('shipping_courier_instant')->insert([
            'shipping_courier_instant_ref_type' => 'trx',
            'shipping_courier_instant_ref_id' => 1,
            'shipping_courier_instant_type' => 'instant',
            'shipping_courier_instant_expedition_name' => 'gosend',
            'shipping_courier_instant_expedition_service' => 'instant',
            'shipping_courier_instant_expedition_vehicle' => 'motor',
            'shipping_courier_instant_estimation_hours' => '1-2 hours',
            'shipping_courier_instant_origin_name' => 'Distributor Utama',
            'shipping_courier_instant_origin_phone' => '081111111111',
            'shipping_courier_instant_origin_address' => 'Jakarta',
            'shipping_courier_instant_origin_latitude' => -6.225,
            'shipping_courier_instant_origin_longitude' => 106.8,
            'shipping_courier_instant_destination_name' => 'Mitra Tujuan',
            'shipping_courier_instant_destination_phone' => '082222222222',
            'shipping_courier_instant_destination_address' => 'Surabaya',
            'shipping_courier_instant_destination_latitude' => -7.26,
            'shipping_courier_instant_destination_longitude' => 112.74,
            'shipping_courier_instant_cost' => 16500,
            'shipping_courier_instant_admin_fee' => 1000,
            'shipping_courier_instant_package_weight' => 100,
        ]);
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-token',
        ]);
        Http::fake([
            'https://stc.example.test/api/shipping/pickup-instant' => Http::response([
                'message' => 'OK',
                'error' => null,
                'data' => ['results' => [
                    'status' => true,
                    'details' => [[
                        'order_id' => 'STC-INSTANT-ADMIN-001',
                        'awb' => 'AWB-INSTANT-ADMIN-001',
                        'status' => 105,
                    ]],
                ]],
            ]),
        ]);

        $payload = [
            'delivery_note_number' => 'SJ-INSTANT-001',
            'items' => [[
                'product_id' => 1,
                'quantity' => 2,
                'batch_number' => 'BATCH-INSTANT-001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ];
        $this->postJson('/api/v1/admin/inventory/shipments/1/ship', $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.shipping.order_id', 'STC-INSTANT-ADMIN-001')
            ->assertJsonPath('data.shipping.tracking_number', 'AWB-INSTANT-ADMIN-001');

        $this->assertDatabaseHas('shipping_courier_instant_status', [
            'shipping_courier_instant_status_ref_type' => 'trx',
            'shipping_courier_instant_status_ref_id' => 1,
            'shipping_courier_instant_status_value' => 'processed_packages',
        ]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://stc.example.test/api/shipping/pickup-instant'
            && $request['latitude'] === -6.225
            && $request['longitude'] === 106.8
            && $request['packages'][0]['order_id'] === 'TRX-001'
            && $request['packages'][0]['service'] === 'gosend'
            && $request['packages'][0]['service_type'] === 'instant'
            && $request['packages'][0]['shipping_cost'] === 16500
            && $request['packages'][0]['vehicle'] === 'motor'
            && $request['packages'][0]['items'][0]['name'] === 'Skincare'
            && ! array_key_exists('admin_fee', $request['packages'][0])
            && ! array_key_exists('total_price', $request['packages'][0])
            && ! array_key_exists('insurance_type', $request['packages'][0])
        );

        Trx::query()->findOrFail(1)->update([
            'trx_status' => 'reship_required',
            'trx_status_datetime' => now(),
        ]);
        $this->postJson('/api/v1/admin/inventory/shipments/1/ship', $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped');
        $this->assertDatabaseHas('shipping_courier_instant_status', [
            'shipping_courier_instant_status_ref_id' => 1,
            'shipping_courier_instant_status_value' => 'processed_packages',
            'shipping_courier_instant_status_ref_code' => 'TRX-001-001',
        ]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://stc.example.test/api/shipping/pickup-instant'
            && $request['packages'][0]['order_id'] === 'TRX-001-001'
        );
    }

    public function test_admin_pickup_shipping_requires_buyer_pin_and_records_batches(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createData();
        DB::table('shipping_courier_manual')->where('shipping_courier_manual_ref_id', 1)->delete();
        Trx::query()->findOrFail(1)->update([
            'trx_shipping_method' => 'pickup',
            'trx_status' => 'processing',
            'trx_status_datetime' => now(),
        ]);
        DB::table('shipping_pickup')->insert([
            'shipping_pickup_ref_type' => 'trx',
            'shipping_pickup_ref_id' => 1,
            'shipping_pickup_seller_name' => 'Gudang Pusat',
            'shipping_pickup_seller_address' => 'Jakarta',
            'shipping_pickup_pin' => '12345',
        ]);

        $payload = [
            'delivery_note_number' => 'SJ-PICKUP-ADMIN-001',
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 1,
                    'batch_number' => 'BATCH-PICKUP-A',
                    'expiry_date' => now()->addYear()->toDateString(),
                ],
                [
                    'product_id' => 1,
                    'quantity' => 1,
                    'batch_number' => 'BATCH-PICKUP-B',
                    'expiry_date' => now()->addYears(2)->toDateString(),
                ],
            ],
        ];

        $this->postJson('/api/v1/admin/inventory/shipments/1/ship', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation');
        $this->postJson('/api/v1/admin/inventory/shipments/1/ship', [
            ...$payload,
            'pickup_pin' => '00000',
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonPath('errors.pickup_pin.0', 'PIN pickup tidak sesuai.');
        $this->postJson('/api/v1/admin/inventory/shipments/1/ship', [
            ...$payload,
            'pickup_pin' => '12345',
        ])->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.shipping.verification_status', 'picked_up');

        $this->assertDatabaseHas('shipping_pickup_status', [
            'shipping_pickup_status_ref_type' => 'trx',
            'shipping_pickup_status_ref_id' => 1,
            'shipping_pickup_status_value' => 'picked_up',
        ]);
        $this->assertDatabaseCount('shipping_detail', 2);
    }

    private function createData(): void
    {
        DB::table('ref_bank')->insert([
            'bank_id' => 1,
            'bank_code' => 'BCA',
            'bank_name' => 'Bank Central Asia',
            'bank_is_active' => 1,
        ]);
        DB::table('warehouse')->insert(['warehouse_id' => 1, 'warehouse_name' => 'Pusat', 'warehouse_legal_name' => 'PT DNY']);
        DB::table('product_category')->insert(['product_category_id' => 1, 'product_category_name' => 'Skincare']);
        Product::query()->create([
            'product_id' => 1,
            'product_product_category_id' => 1,
            'product_code' => 'SRM-001',
            'product_name' => 'Serum DNY',
            'product_bpom_number' => 'NA18250100001',
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
            'warehouse_stock_balance' => 10,
        ]);
        DB::table('trx')->insert($this->trxPayload());
        DB::table('trx_detail')->insert([
            'trx_detail_id' => 1,
            'trx_detail_trx_id' => 1,
            'trx_detail_product_id' => 1,
            'trx_detail_product_code' => 'SRM-001',
            'trx_detail_product_name' => 'Serum DNY',
            'trx_detail_product_bpom_number' => 'NA18250100999',
            'trx_detail_product_price' => 100000,
            'trx_detail_nett_price' => 100000,
            'trx_detail_qty' => 2,
        ]);
        DB::table('shipping_courier_manual')->insert([
            'shipping_courier_manual_ref_type' => 'trx',
            'shipping_courier_manual_ref_id' => 1,
            'shipping_courier_manual_name' => 'JNE',
            'shipping_courier_manual_service' => 'REG',
            'shipping_courier_manual_origin_name' => 'Gudang Pusat',
            'shipping_courier_manual_origin_phone' => '0211234567',
            'shipping_courier_manual_origin_address' => 'Jakarta',
            'shipping_courier_manual_origin_subdistrict_id' => 0,
            'shipping_courier_manual_destination_name' => 'Distributor Utama',
            'shipping_courier_manual_destination_phone' => '081234567890',
            'shipping_courier_manual_destination_address' => 'Surabaya',
            'shipping_courier_manual_destination_subdistrict_id' => 0,
        ]);
    }

    private function createPayment(): void
    {
        DB::table('trx_payment_transfer')->insert([
            'payment_transfer_id' => 1,
            'payment_transfer_trx_id' => 1,
            'payment_transfer_bill_amount' => 200000,
            'payment_transfer_bank_id' => 1,
            'payment_transfer_account_name' => 'PT DNY',
            'payment_transfer_account_number' => '123456',
            'payment_transfer_amount' => 200000,
            'payment_transfer_datetime' => '2026-07-11 08:00:00',
            'payment_transfer_receipt_file' => 'https://example.test/bukti.webp',
            'payment_transfer_approval_status' => 'submitted',
            'payment_transfer_note' => '',
        ]);
    }

    private function createRegionalReferences(): void
    {
        DB::table('ref_province')->insert([
            ['province_id' => '31', 'province_name' => 'DKI Jakarta', 'province_is_active' => 1],
            ['province_id' => '35', 'province_name' => 'Jawa Timur', 'province_is_active' => 1],
        ]);
        DB::table('ref_city')->insert([
            ['city_id' => '3171', 'city_province_id' => '31', 'city_name' => 'Jakarta Selatan', 'city_type' => 'Kota', 'city_is_active' => 1],
            ['city_id' => '3578', 'city_province_id' => '35', 'city_name' => 'Surabaya', 'city_type' => 'Kota', 'city_is_active' => 1],
        ]);
        DB::table('ref_district')->insert([
            ['district_id' => '317101', 'district_city_id' => '3171', 'district_name' => 'Kebayoran Baru'],
            ['district_id' => '357801', 'district_city_id' => '3578', 'district_name' => 'Tegalsari'],
        ]);
        DB::table('ref_subdistrict')->insert([
            ['subdistrict_id' => 31710101, 'subdistrict_district_id' => 317101, 'subdistrict_name' => 'Senayan', 'subdistrict_zip_code' => 12190],
            ['subdistrict_id' => 35780101, 'subdistrict_district_id' => 357801, 'subdistrict_name' => 'Kedungdoro', 'subdistrict_zip_code' => 60261],
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
            'trx_buyer_type' => 'distributor',
            'trx_buyer_id' => 1,
            'trx_type' => 'stock',
            'trx_total_price' => 200000,
            'trx_grand_total_price' => 200000,
            'trx_grand_total_nett_price' => 200000,
            'trx_bill_amount' => 200000,
            'trx_payment_method' => 'transfer',
            'trx_shipping_method' => 'courier_manual',
            'trx_status' => 'waiting_payment',
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
            'administrator_username' => 'workflow.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Workflow Admin',
            'administrator_email' => 'workflow.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
