<?php

namespace Tests\Feature\Api\V1\Member;

use App\Mail\PaymentApprovedMail;
use App\Models\Customer;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Models\MemberGroup;
use App\Models\MemberStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Trx;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberSalesAndShippingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_seller_payment_approval_sends_notification_to_buyer(): void
    {
        Mail::fake();
        [$sellerAccount, $product] = $this->salesData();
        $buyer = Member::query()->create([
            'member_id' => 2,
            'member_code' => 'AGT-002',
            'member_member_level_id' => 2,
            'member_parent_member_id' => 1,
            'member_name' => 'Agent Pembeli',
            'member_email' => 'agent.buyer@example.test',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
        $trx = Trx::query()->create([
            'trx_code' => 'TRX/DST/AGT/000001/MAIL01',
            'trx_seller_type' => 'distributor',
            'trx_seller_id' => 1,
            'trx_buyer_type' => 'agent',
            'trx_buyer_id' => $buyer->getKey(),
            'trx_type' => 'stock',
            'trx_total_price' => 100000,
            'trx_grand_total_price' => 100000,
            'trx_grand_total_nett_price' => 100000,
            'trx_bill_amount' => 100000,
            'trx_payment_method' => 'transfer',
            'trx_shipping_method' => 'pickup',
            'trx_status' => 'waiting_payment_approval',
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);
        $trx->details()->create([
            'trx_detail_product_id' => $product->getKey(),
            'trx_detail_product_code' => $product->product_code,
            'trx_detail_product_name' => $product->product_name,
            'trx_detail_product_price' => 100000,
            'trx_detail_nett_price' => 100000,
            'trx_detail_qty' => 1,
        ]);
        $trx->paymentTransfer()->create([
            'payment_transfer_bill_amount' => 100000,
            'payment_transfer_bank_id' => 1,
            'payment_transfer_account_name' => 'Distributor DNY',
            'payment_transfer_account_number' => '1234567890',
            'payment_transfer_amount' => 100000,
            'payment_transfer_datetime' => now(),
            'payment_transfer_receipt_file' => 'https://cdn.example.test/payment/receipt.webp',
            'payment_transfer_approval_status' => 'submitted',
            'payment_transfer_note' => '',
        ]);
        $this->actingAs($sellerAccount, 'member_api');

        $this->postJson("/api/v1/member/sales/orders/{$trx->getKey()}/payment/approve")
            ->assertOk()
            ->assertJsonPath('data.payment.status.code', 'approved');

        Mail::assertSent(PaymentApprovedMail::class, function (PaymentApprovedMail $mail) use ($trx): bool {
            return $mail->hasTo('agent.buyer@example.test')
                && $mail->orderNumber === $trx->trx_code;
        });
        $this->assertDatabaseHas('notification', [
            'notification_user_type' => 'member',
            'notification_user_id' => $buyer->getKey(),
            'notification_title' => 'Pembayaran Disetujui',
            'notification_ref_table' => 'trx_purchase',
            'notification_ref_id' => $trx->getKey(),
            'notification_is_read' => 0,
        ]);
    }

    public function test_customer_options_are_separate_searchable_and_limited(): void
    {
        [$account] = $this->salesData();
        $this->actingAs($account, 'member_api');

        $createdCustomer = $this->postJson('/api/v1/member/sales/customers', $this->customerPayload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Pelanggan DNY')
            ->assertJsonPath('data.whatsapp', '+6281200000001');
        $this->assertDatabaseHas('customer', [
            'customer_id' => $createdCustomer->json('data.id'),
            'customer_member_id' => 1,
            'customer_whatsapp' => '+6281200000001',
        ]);

        foreach (range(1, 12) as $number) {
            Customer::query()->create([
                'customer_member_id' => 1,
                'customer_name' => "Pelanggan {$number}",
                'customer_whatsapp' => '08120000'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'customer_phone' => '',
                'customer_gender' => 'L',
                'customer_birth_date' => null,
                'customer_address' => "Jalan Pelanggan {$number}",
                'customer_province_id' => 35,
                'customer_city_id' => 3578,
                'customer_district_id' => 357801,
                'customer_subdistrict_id' => 35780101,
                'customer_is_deleted' => 0,
                'customer_created_datetime' => now(),
            ]);
        }

        $this->getJson('/api/v1/member/sales/options')
            ->assertOk()
            ->assertJsonMissingPath('data.customers');

        $this->getJson('/api/v1/member/sales/customers/options')
            ->assertOk()
            ->assertJsonCount(10, 'data.results');

        $this->getJson('/api/v1/member/sales/customers/options?search=Pelanggan%201&limit=5')
            ->assertOk()
            ->assertJsonCount(4, 'data.results')
            ->assertJsonPath('data.results.0.name', 'Pelanggan 12');
    }

    public function test_sale_order_requires_customer_owned_by_logged_member(): void
    {
        [$account, $product] = $this->salesData();
        $foreignCustomer = $this->customer('081200000099', 99);
        $this->actingAs($account, 'member_api');
        $order = [
            'payment_method' => 'cash',
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ];

        $this->postJson('/api/v1/member/sales/orders', [
            ...$order,
            'customer' => $this->customerPayload(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');

        $this->postJson('/api/v1/member/sales/orders', [
            ...$order,
            'customer_id' => $foreignCustomer->getKey(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');
    }

    public function test_pos_rejects_transfer_payment_and_bank_account(): void
    {
        [$account, $product] = $this->salesData();
        $customer = $this->customer();
        $this->actingAs($account, 'member_api');

        $this->postJson('/api/v1/member/sales/orders', [
            'customer_id' => $customer->getKey(),
            'payment_method' => 'transfer',
            'bank_account_id' => 1,
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method', 'bank_account_id'])
            ->assertJsonPath('errors.payment_method.0', 'Metode pembayaran POS hanya tersedia secara tunai.')
            ->assertJsonPath('errors.bank_account_id.0', 'Rekening tujuan tidak digunakan untuk transaksi POS.');
    }

    public function test_pos_options_only_offer_cash_and_pickup(): void
    {
        [$account, $product] = $this->salesData();
        Member::query()->whereKey(1)->update([
            'member_code' => 'AGT-001',
            'member_member_level_id' => 2,
            'member_name' => 'Agent DNY',
        ]);
        $account->unsetRelation('member');
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/sales/options')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.seller.level', 'Agent')
            ->assertJsonPath('data.seller.address', 'Jalan Distributor Nomor 1')
            ->assertJsonPath('data.seller.origin.name', 'Agent DNY')
            ->assertJsonPath('data.seller.origin.phone', '081234567890')
            ->assertJsonPath('data.seller.origin.address', 'Jalan Distributor Nomor 1')
            ->assertJsonPath('data.seller.origin.province_id', 31)
            ->assertJsonPath('data.seller.origin.province_name', 'DKI Jakarta')
            ->assertJsonPath('data.seller.origin.city_id', 3171)
            ->assertJsonPath('data.seller.origin.city_name', 'Jakarta Selatan')
            ->assertJsonPath('data.seller.origin.district_id', 317101)
            ->assertJsonPath('data.seller.origin.district_name', 'Kebayoran Baru')
            ->assertJsonPath('data.seller.origin.subdistrict_id', 31710101)
            ->assertJsonPath('data.seller.origin.subdistrict_name', 'Senayan')
            ->assertJsonPath('data.seller.origin.zipcode', 12190)
            ->assertJsonCount(0, 'data.bank_accounts')
            ->assertJsonCount(1, 'data.payment_methods')
            ->assertJsonPath('data.payment_methods.0.code', 'cash')
            ->assertJsonCount(1, 'data.shipping_methods')
            ->assertJsonPath('data.shipping_methods.0.code', 'pickup');

        $this->getJson('/api/v1/member/sales/catalog/products')
            ->assertOk()
            ->assertJsonPath('data.results.0.name', 'Serum DNY')
            ->assertJsonPath('data.results.0.available_stock', 5);

    }

    public function test_cash_pickup_pos_completes_with_batch_and_expiry_in_single_checkout(): void
    {
        [$account, $product] = $this->salesData();
        $customer = $this->customer();
        $this->actingAs($account, 'member_api');
        $expiryDate = now()->addMonth()->toDateString();

        $this->postJson('/api/v1/member/sales/orders', [
            'customer_id' => $customer->getKey(),
            'payment_method' => 'cash',
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.batches');

        $this->postJson('/api/v1/member/sales/orders', [
            'customer_id' => $customer->getKey(),
            'payment_method' => 'cash',
            'shipping_method' => 'pickup',
            'items' => [[
                'product_id' => $product->getKey(),
                'quantity' => 2,
                'batches' => [[
                    'quantity' => 2,
                    'batch_number' => 'BATCH-POS-001',
                ]],
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.batches.0.expiry_date');

        $this->postJson('/api/v1/member/sales/orders', [
            'customer_id' => $customer->getKey(),
            'payment_method' => 'cash',
            'shipping_method' => 'pickup',
            'items' => [[
                'product_id' => $product->getKey(),
                'quantity' => 2,
                'batches' => [[
                    'quantity' => 2,
                    'batch_number' => 'BATCH-POS-001',
                    'expiry_date' => now()->toDateString(),
                ]],
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.batches.0.expiry_date');

        $this->postJson('/api/v1/member/sales/orders', [
            'customer_id' => $customer->getKey(),
            'payment_method' => 'cash',
            'shipping_method' => 'pickup',
            'items' => [[
                'product_id' => $product->getKey(),
                'quantity' => 2,
                'batches' => [[
                    'quantity' => 1,
                    'batch_number' => 'BATCH-POS-001',
                    'expiry_date' => $expiryDate,
                ]],
            ]],
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');
        $this->assertDatabaseCount('trx', 0);

        $checkout = $this->postJson('/api/v1/member/sales/orders', [
            'customer_id' => $customer->getKey(),
            'payment_method' => 'cash',
            'shipping_method' => 'pickup',
            'items' => [[
                'product_id' => $product->getKey(),
                'quantity' => 2,
                'batches' => [
                    ['quantity' => 1, 'batch_number' => 'BATCH-POS-001', 'expiry_date' => $expiryDate],
                    ['quantity' => 1, 'batch_number' => 'BATCH-POS-002', 'expiry_date' => $expiryDate],
                ],
            ]],
        ])->assertOk()
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.items.0.product.bpom_number', 'NA18250100003')
            ->assertJsonPath('data.shipping_method', 'pickup')
            ->assertJsonPath('data.status.code', 'completed')
            ->assertJsonPath('data.actions.can_ship', false)
            ->assertJsonPath('data.summary.grand_total', 200000);

        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 3,
            'member_stock_transfer_out' => 0,
        ]);

        $trxId = (int) $checkout->json('data.id');
        $this->assertDatabaseHas('trx_detail', [
            'trx_detail_trx_id' => $trxId,
            'trx_detail_product_bpom_number' => 'NA18250100003',
        ]);
        $this->getJson('/api/v1/member/sales/orders')
            ->assertOk()
            ->assertJsonPath('data.results.0.product_preview.bpom_number', 'NA18250100003');
        $this->assertDatabaseHas('shipping_pickup', [
            'shipping_pickup_ref_type' => 'trx',
            'shipping_pickup_ref_id' => $trxId,
            'shipping_pickup_pin' => '',
        ]);
        $this->getJson("/api/v1/member/sales/orders/{$trxId}")
            ->assertOk()
            ->assertJsonPath('data.status.code', 'completed')
            ->assertJsonPath('data.items.0.product.bpom_number', 'NA18250100003')
            ->assertJsonPath('data.seller.origin.subdistrict.name', 'Senayan')
            ->assertJsonPath('data.seller.origin.district.name', 'Kebayoran Baru')
            ->assertJsonPath('data.seller.origin.city.name', 'Jakarta Selatan')
            ->assertJsonPath('data.seller.origin.province.name', 'DKI Jakarta')
            ->assertJsonPath('data.seller.origin.postal_code', 12190)
            ->assertJsonPath('data.buyer.destination.subdistrict.name', 'Kedungdoro')
            ->assertJsonPath('data.buyer.destination.district.name', 'Tegalsari')
            ->assertJsonPath('data.buyer.destination.city.name', 'Surabaya')
            ->assertJsonPath('data.buyer.destination.province.name', 'Jawa Timur')
            ->assertJsonPath('data.buyer.destination.postal_code', 60261)
            ->assertJsonPath('data.shipping.verification_status', 'completed')
            ->assertJsonPath('data.tracking.is_available', false)
            ->assertJsonPath('data.tracking.provider', null)
            ->assertJsonPath('data.tracking.method', 'pickup')
            ->assertJsonPath('data.tracking.order_id', null)
            ->assertJsonPath('data.tracking.tracking_number', null)
            ->assertJsonPath('data.tracking.status', 'completed')
            ->assertJsonCount(0, 'data.tracking.histories')
            ->assertJsonPath('data.actions.can_ship', false)
            ->assertJsonPath('data.actions.allows_delivery_note_number', false)
            ->assertJsonPath('data.actions.requires_delivery_note_number', false);

        $this->assertDatabaseHas('shipping_detail', [
            'shipping_detail_shipping_type' => 'pickup',
            'shipping_detail_product_id' => $product->getKey(),
            'shipping_detail_batch_number' => 'BATCH-POS-001',
            'shipping_detail_qty' => 1,
            'shipping_detail_expire_date' => $expiryDate,
        ]);
        $this->assertDatabaseHas('shipping_detail', [
            'shipping_detail_shipping_type' => 'pickup',
            'shipping_detail_product_id' => $product->getKey(),
            'shipping_detail_batch_number' => 'BATCH-POS-002',
            'shipping_detail_qty' => 1,
            'shipping_detail_expire_date' => $expiryDate,
        ]);
        $this->assertDatabaseHas('shipping_pickup', [
            'shipping_pickup_ref_type' => 'trx',
            'shipping_pickup_ref_id' => $trxId,
            'shipping_pickup_delivery_note_number' => null,
        ]);

        $otherCustomer = $this->customer('081200000002');
        $this->postJson('/api/v1/member/sales/orders', [
            'customer_id' => $otherCustomer->getKey(),
            'payment_method' => 'cash',
            'shipping_method' => 'pickup',
            'items' => [[
                'product_id' => $product->getKey(),
                'quantity' => 5,
                'batches' => [[
                    'quantity' => 5,
                    'batch_number' => 'BATCH-POS-003',
                    'expiry_date' => $expiryDate,
                ]],
            ]],
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');
    }

    public function test_pos_rejects_courier_manual_and_courier_payload(): void
    {
        [$account, $product] = $this->salesData();
        $customer = $this->customer();
        $this->actingAs($account, 'member_api');

        $this->postJson('/api/v1/member/sales/orders', [
            'customer_id' => $customer->getKey(),
            'payment_method' => 'cash',
            'shipping_method' => 'courier_manual',
            'courier' => [
                'name' => 'JNE',
                'service' => 'REG',
                'cost' => 20000,
            ],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['shipping_method', 'courier'])
            ->assertJsonPath('errors.shipping_method.0', 'Metode penyerahan POS hanya tersedia ambil di tempat.')
            ->assertJsonPath('errors.courier.0', 'Data kurir tidak digunakan untuk transaksi POS.');
    }

    public function test_stock_order_completion_is_owned_by_goods_receive_flow(): void
    {
        [$account, $product] = $this->salesData();
        $this->actingAs($account, 'member_api');
        Member::query()->create([
            'member_id' => 2,
            'member_code' => 'AGT-002',
            'member_member_level_id' => 2,
            'member_parent_member_id' => 1,
            'member_name' => 'Agent DNY',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
        $trx = Trx::query()->create([
            'trx_code' => 'STOCK-READY-001',
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
            'trx_shipping_method' => 'courier_manual',
            'trx_status' => 'shipped',
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);
        $trx->details()->create([
            'trx_detail_product_id' => $product->getKey(),
            'trx_detail_product_code' => $product->product_code,
            'trx_detail_product_name' => $product->product_name,
            'trx_detail_product_price' => 100000,
            'trx_detail_nett_price' => 100000,
            'trx_detail_qty' => 1,
        ]);
        MemberStock::query()->where([
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
        ])->update(['member_stock_transfer_out' => 1]);

        $this->getJson('/api/v1/member/sales/orders')
            ->assertOk()
            ->assertJsonMissingPath('data.results.0.actions.can_complete');
        $this->postJson("/api/v1/member/sales/orders/{$trx->getKey()}/complete")
            ->assertNotFound();
    }

    public function test_manual_courier_tracking_number_does_not_enable_provider_tracking(): void
    {
        [$sellerAccount, $product] = $this->salesData();
        $buyer = Member::query()->create([
            'member_id' => 2,
            'member_code' => 'AGT-002',
            'member_member_level_id' => 2,
            'member_parent_member_id' => 1,
            'member_name' => 'Agent DNY',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
        $buyerAccount = MemberAccount::query()->create([
            'member_account_member_id' => $buyer->getKey(),
            'member_account_member_group_id' => MemberGroup::query()->sole()->getKey(),
            'member_account_username' => 'purchase.agent',
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
        $trx = Trx::query()->create([
            'trx_code' => 'MANUAL-TRACKING-001',
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
            'trx_shipping_method' => 'courier_manual',
            'trx_status' => 'shipped',
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);
        $trx->details()->create([
            'trx_detail_product_id' => $product->getKey(),
            'trx_detail_product_code' => $product->product_code,
            'trx_detail_product_name' => $product->product_name,
            'trx_detail_product_price' => 100000,
            'trx_detail_nett_price' => 100000,
            'trx_detail_qty' => 1,
        ]);
        $trx->shippingManual()->create([
            'shipping_courier_manual_ref_type' => 'trx',
            'shipping_courier_manual_name' => 'sicepat',
            'shipping_courier_manual_service' => 'REG',
            'shipping_courier_manual_type' => 'REG',
            'shipping_courier_manual_awb' => '12312',
            'shipping_courier_manual_origin_address' => 'Jalan Distributor Nomor 1',
            'shipping_courier_manual_origin_subdistrict_id' => 31710101,
            'shipping_courier_manual_destination_address' => 'Jalan Agent Nomor 2',
            'shipping_courier_manual_destination_subdistrict_id' => 35780101,
        ]);

        foreach ([
            [$sellerAccount, "/api/v1/member/sales/orders/{$trx->getKey()}"],
            [$buyerAccount, "/api/v1/member/purchases/orders/{$trx->getKey()}"],
        ] as [$account, $endpoint]) {
            $this->actingAs($account, 'member_api');
            $this->getJson($endpoint)
                ->assertOk()
                ->assertJsonPath('data.tracking.is_available', false)
                ->assertJsonPath('data.tracking.provider', 'sicepat')
                ->assertJsonPath('data.tracking.method', 'courier_manual')
                ->assertJsonPath('data.tracking.order_id', null)
                ->assertJsonPath('data.tracking.tracking_number', '12312')
                ->assertJsonCount(0, 'data.tracking.histories');
        }
    }

    public function test_shipping_rates_remain_available_for_manual_member_delivery(): void
    {
        [$account, $product] = $this->salesData();
        $customer = $this->customer();
        $this->actingAs($account, 'member_api');
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-token',
            'services.stc.client_id' => 9,
        ]);
        Http::fake([
            'https://stc.example.test/api/shipping/price-express' => Http::response([
                'status' => true,
                'details' => [
                    'origin_district_id' => 317101,
                    'origin_subdistrict_id' => 31710101,
                    'destination_district_id' => 357801,
                    'destination_subdistrict_id' => 35780101,
                ],
                'results' => [[
                    'service' => 'JNE',
                    'service_name' => 'Regpack',
                    'service_type' => 'REGPACK',
                    'cost' => 25000,
                    'etd' => '2-3 hari',
                    'drop' => true,
                    'force_insurance' => false,
                    'insurance' => 0,
                    'logo' => 'https://stc.example.test/jne.png',
                ]],
            ]),
        ]);

        $this->getJson('/api/v1/member/sales/options')
            ->assertOk()
            ->assertJsonCount(1, 'data.shipping_methods')
            ->assertJsonPath('data.shipping_methods.0.code', 'pickup');

        $this->postJson('/api/v1/member/shipping/express/rates', [
            'customer_id' => $customer->getKey(),
            'couriers' => ['JNE'],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.origin.district_id', 317101)
            ->assertJsonPath('data.origin.district_name', 'Kebayoran Baru')
            ->assertJsonPath('data.origin.subdistrict_id', 31710101)
            ->assertJsonPath('data.origin.subdistrict_name', 'Senayan')
            ->assertJsonPath(
                'data.origin.display_name',
                'Senayan, Kebayoran Baru, Kota Jakarta Selatan, DKI Jakarta',
            )
            ->assertJsonPath('data.destination.district_id', 357801)
            ->assertJsonPath('data.destination.district_name', 'Tegalsari')
            ->assertJsonPath('data.destination.subdistrict_id', 35780101)
            ->assertJsonPath('data.destination.subdistrict_name', 'Kedungdoro')
            ->assertJsonPath(
                'data.destination.display_name',
                'Kedungdoro, Tegalsari, Kota Surabaya, Jawa Timur',
            )
            ->assertJsonPath('data.results.0.courier_code', 'JNE')
            ->assertJsonPath('data.results.0.service_type', 'REGPACK')
            ->assertJsonPath('data.results.0.cost', 25000);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://stc.example.test/api/shipping/price-express'
            && $request['client_id'] === 9
            && $request['origin'] === 317101
            && $request['subdistrict_origin'] === 31710101
            && $request['destination'] === 357801
            && $request['weight'] === 200
            && $request['item_value'] === 200000
            && $request['courier'] === ['jne']
        );

    }

    public function test_express_shipping_rate_rejects_product_without_shipping_dimensions(): void
    {
        [$account, $product] = $this->salesData();
        $this->actingAs($account, 'member_api');
        Http::preventStrayRequests();

        $product->forceFill([
            'product_weight' => 0,
            'product_length' => 0,
            'product_width' => 0,
            'product_height' => 0,
        ])->save();

        $this->postJson('/api/v1/member/shipping/express/rates', [
            'address_id' => 1,
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Berat dan dimensi produk Serum DNY belum lengkap. Perbarui master produk sebelum menghitung ongkir.',
                'error_code' => 'process_error',
            ]);

        Http::assertNothingSent();
    }

    public function test_rate_api_remains_available_for_member_delivery_and_agent_origin(): void
    {
        [$account, $product] = $this->salesData();
        $this->actingAs($account, 'member_api');
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-token',
            'services.stc.client_id' => 9,
        ]);
        Http::fake([
            'https://stc.example.test/api/shipping/price-express' => Http::response([
                'status' => true,
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

        $this->postJson('/api/v1/member/shipping/express/rates', [
            'address_id' => 1,
            'couriers' => ['JNE'],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.results.0.cost', 25000);

        Member::query()->create([
            'member_id' => 2,
            'member_code' => 'AGT-002',
            'member_member_level_id' => 2,
            'member_parent_member_id' => 0,
            'member_name' => 'Agent DNY',
            'member_address' => 'Jalan Agent Nomor 2',
            'member_province_id' => 31,
            'member_city_id' => 3171,
            'member_district_id' => 317101,
            'member_subdistrict_id' => 31710101,
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
        Member::query()->whereKey(1)->update([
            'member_member_level_id' => 3,
            'member_parent_member_id' => 2,
        ]);
        $account->unsetRelation('member');
        $this->postJson('/api/v1/member/shipping/express/rates', [
            'address_id' => 1,
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.results.0.cost', 25000);
    }

    public function test_instant_rate_remains_available_but_pos_checkout_rejects_it(): void
    {
        [$account, $product] = $this->salesData();
        $customer = $this->customer();
        $this->actingAs($account, 'member_api');
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-token',
        ]);
        Http::fake([
            'https://stc.example.test/api/shipping/price-instant' => Http::response([
                'message' => 'OK',
                'error' => null,
                'data' => ['results' => [
                    'method' => 'mitra_pricing',
                    'meta' => ['distance' => 6.5, 'distance_unit' => 'km'],
                    'result' => [[
                        'name' => 'gosend',
                        'costs' => [[
                            'service_type' => 'instant',
                            'estimation' => '1-2 hours',
                            'price' => [
                                'admin_fee' => 1000,
                                'shipping_costs' => 16500,
                                'total_price' => 17500,
                            ],
                        ]],
                        'insurances' => [['value' => 500, 'type' => 'silver']],
                    ]],
                ]],
            ]),
        ]);

        $ratePayload = [
            'services' => ['gosend'],
            'vehicle' => 'motor',
            'origin' => [
                'latitude' => -6.225,
                'longitude' => 106.8,
                'address' => 'Jalan Distributor Nomor 1',
            ],
            'destination' => [
                'latitude' => -7.26,
                'longitude' => 112.74,
                'address' => 'Jalan Pelanggan Nomor 2',
            ],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ];
        $this->postJson('/api/v1/member/shipping/instant/rates', $ratePayload)
            ->assertOk()
            ->assertJsonPath('data.origin.address', 'Jalan Distributor Nomor 1')
            ->assertJsonPath('data.destination.address', 'Jalan Pelanggan Nomor 2')
            ->assertJsonPath('data.distance', 6.5)
            ->assertJsonPath('data.distance_unit', 'km')
            ->assertJsonPath('data.results.0.courier_code', 'gosend')
            ->assertJsonPath('data.results.0.service_type', 'instant')
            ->assertJsonPath('data.results.0.cost', 16500)
            ->assertJsonPath('data.results.0.admin_fee', 1000)
            ->assertJsonPath('data.results.0.total_cost', 17500)
            ->assertJsonMissingPath('data.results.0.insurance_options');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://stc.example.test/api/shipping/price-instant'
            && $request['service'] === ['gosend']
            && $request['origin']['lat'] === -6.225
            && $request['origin']['long'] === 106.8
            && $request['destination']['lat'] === -7.26
            && $request['destination']['long'] === 112.74
            && $request['weight'] === 100
            && $request['vehicle'] === 'motor'
            && $request['timezone'] === 'WIB'
        );

        $checkoutPayload = [
            'customer_id' => $customer->getKey(),
            'payment_method' => 'cash',
            'shipping_method' => 'courier_instant',
            'courier' => [
                'name' => 'gosend',
                'service' => 'instant',
                'type' => 'instant',
                'cost' => 16500,
                'etd' => '1-2 hours',
                'vehicle' => 'motor',
                'admin_fee' => 1000,
                'origin_latitude' => -6.225,
                'origin_longitude' => 106.8,
                'destination_latitude' => -7.26,
                'destination_longitude' => 112.74,
            ],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ];

        $this->postJson('/api/v1/member/sales/orders', $checkoutPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shipping_method');
    }

    public function test_stc_credential_is_forwarded_without_interpreting_its_jwt_exp_claim(): void
    {
        [$account, $product] = $this->salesData();
        $this->actingAs($account, 'member_api');
        $payload = rtrim(strtr(base64_encode(json_encode(['exp' => 1])), '+/', '-_'), '=');
        $credential = "header.{$payload}.signature";
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => $credential,
        ]);
        Http::fake([
            'https://stc.example.test/api/shipping/price-instant' => Http::response([
                'message' => 'OK',
                'error' => null,
                'data' => ['results' => [
                    'method' => 'mitra_pricing',
                    'results' => [[
                        'name' => 'gosend',
                        'costs' => [[
                            'service_type' => 'instant',
                            'price' => [
                                'admin_fee' => 1000,
                                'shipping_costs' => 16500,
                                'total_price' => 17500,
                            ],
                        ]],
                        'insurances' => [],
                    ]],
                ]],
            ]),
        ]);

        $this->postJson('/api/v1/member/shipping/instant/rates', [
            'services' => ['gosend'],
            'vehicle' => 'motor',
            'origin' => [
                'latitude' => -6.225,
                'longitude' => 106.8,
                'address' => 'Jalan Distributor Nomor 1',
            ],
            'destination' => [
                'latitude' => -7.26,
                'longitude' => 112.74,
                'address' => 'Jalan Pelanggan Nomor 2',
            ],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.results.0.total_cost', 17500);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://stc.example.test/api/shipping/price-instant'
            && $request->hasHeader('Authorization', "Bearer {$credential}")
        );
    }

    public function test_express_shipping_rates_accept_owned_customer_as_sales_destination(): void
    {
        [$account, $product] = $this->salesData();
        $customer = $this->customer();
        $this->actingAs($account, 'member_api');
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-token',
            'services.stc.client_id' => 9,
        ]);
        Http::fake([
            'https://stc.example.test/api/shipping/price-express' => Http::response([
                'status' => true,
                'results' => [[
                    'service' => 'jne',
                    'service_name' => 'JNE Express Reguler',
                    'service_type' => 'REG',
                    'cost' => 18000,
                    'drop' => true,
                ]],
            ]),
        ]);

        $payload = [
            'customer_id' => $customer->getKey(),
            'couriers' => ['jne'],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ];

        $this->postJson('/api/v1/member/shipping/express/rates', $payload)
            ->assertOk()
            ->assertJsonPath('data.origin.district_id', 317101)
            ->assertJsonPath('data.origin.subdistrict_id', 31710101)
            ->assertJsonPath('data.destination.district_id', 357801)
            ->assertJsonPath('data.destination.subdistrict_id', 35780101)
            ->assertJsonPath('data.results.0.cost', 18000);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://stc.example.test/api/shipping/price-express'
            && $request['origin'] === 317101
            && $request['subdistrict_origin'] === 31710101
            && $request['destination'] === 357801
            && $request['subdistrict_destination'] === 35780101
            && $request['item_value'] === 200000
        );

        $this->postJson('/api/v1/member/shipping/express/rates', [
            ...$payload,
            'address_id' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['address_id', 'customer_id']);

        $foreignCustomer = $this->customer('081200000099', 99);
        $this->postJson('/api/v1/member/shipping/express/rates', [
            ...$payload,
            'customer_id' => $foreignCustomer->getKey(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');
    }

    public function test_instant_rate_rejects_package_above_provider_weight_limit(): void
    {
        [$account, $product] = $this->salesData();
        $this->actingAs($account, 'member_api');
        Http::preventStrayRequests();
        $product->forceFill(['product_weight' => 40001])->save();

        $this->postJson('/api/v1/member/shipping/instant/rates', [
            'services' => ['GoSend'],
            'vehicle' => 'MOTOR',
            'origin' => [
                'latitude' => -6.225,
                'longitude' => 106.8,
                'address' => 'Jalan Distributor Nomor 1',
            ],
            'destination' => [
                'latitude' => -7.26,
                'longitude' => 112.74,
                'address' => 'Jalan Pelanggan Nomor 2',
            ],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Berat paket kurir instan maksimal 40.000 gram.',
                'error_code' => 'process_error',
            ]);

        Http::assertNothingSent();
    }

    public function test_instant_rate_validates_supported_courier_vehicle_and_timezone(): void
    {
        [$account, $product] = $this->salesData();
        $this->actingAs($account, 'member_api');
        Http::preventStrayRequests();

        $this->postJson('/api/v1/member/shipping/instant/rates', [
            'services' => ['lalamove'],
            'vehicle' => 'sepeda',
            'timezone' => 'UTC',
            'origin' => [
                'latitude' => -6.225,
                'longitude' => 106.8,
                'address' => 'Jalan Distributor Nomor 1',
            ],
            'destination' => [
                'latitude' => -7.26,
                'longitude' => 112.74,
                'address' => 'Jalan Pelanggan Nomor 2',
            ],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['services.0', 'vehicle', 'timezone']);

        Http::assertNothingSent();
    }

    /** @return array{MemberAccount, Product} */
    private function salesData(): array
    {
        $this->regionalReferences();
        $group = MemberGroup::query()->firstOrCreate(
            ['member_group_name' => 'Mitra'],
            ['member_group_description' => 'Akses member', 'member_group_is_active' => 1]
        );
        $member = Member::query()->create([
            'member_id' => 1,
            'member_code' => 'DST-001',
            'member_member_level_id' => 1,
            'member_parent_member_id' => 0,
            'member_name' => 'Distributor DNY',
            'member_email' => 'distributor@example.test',
            'member_mobilephone' => '081234567890',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
        $account = MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(),
            'member_account_member_group_id' => $group->getKey(),
            'member_account_username' => 'sales.distributor',
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
        MemberAddress::query()->create([
            'member_address_id' => 1,
            'member_address_member_id' => $member->getKey(),
            'member_address_label' => 'Alamat Utama',
            'member_address_recipient' => 'Distributor DNY',
            'member_address_phone' => '081234567890',
            'member_address_full' => 'Jalan Distributor Nomor 1',
            'member_address_province_id' => 31,
            'member_address_city_id' => 3171,
            'member_address_district_id' => 317101,
            'member_address_subdistrict_id' => 31710101,
            'member_address_country_id' => 1,
            'member_address_is_default' => 1,
        ]);
        Warehouse::query()->create([
            'warehouse_id' => 1,
            'warehouse_name' => 'Warehouse Utama DNY',
            'warehouse_address' => 'Jalan Warehouse Nomor 1',
            'warehouse_province_id' => 31,
            'warehouse_city_id' => 3171,
            'warehouse_district_id' => 317101,
            'warehouse_subdistrict_id' => 31710101,
            'warehouse_is_active' => 1,
            'warehouse_created_datetime' => now(),
        ]);
        DB::table('ref_bank')->insert([
            'bank_id' => 1,
            'bank_code' => 'BCA',
            'bank_name' => 'Bank Central Asia',
            'bank_is_active' => 1,
        ]);
        MemberBankAccount::query()->create([
            'member_bank_account_id' => 1,
            'member_bank_account_member_id' => $member->getKey(),
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Distributor DNY',
            'member_bank_account_number' => '1234567890',
            'member_bank_account_is_active' => 1,
        ]);
        $category = ProductCategory::query()->create([
            'product_category_name' => 'Skincare',
            'product_category_description' => 'Produk DNY',
            'product_category_is_active' => 1,
        ]);
        $product = Product::query()->create([
            'product_product_category_id' => $category->getKey(),
            'product_code' => 'SRM-001',
            'product_name' => 'Serum DNY',
            'product_bpom_number' => 'NA18250100003',
            'product_image' => 'https://cdn.example.test/products/serum.webp',
            'product_customer_price' => 100000,
            'product_weight' => 100,
            'product_length' => 10,
            'product_width' => 8,
            'product_height' => 5,
            'product_unit' => 'pcs',
            'product_is_publish' => 1,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
        ]);
        MemberStock::query()->create([
            'member_stock_member_id' => $member->getKey(),
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 5,
            'member_stock_transfer_in' => 0,
            'member_stock_transfer_out' => 0,
        ]);
        WarehouseStock::query()->create([
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 100,
            'warehouse_stock_transfer_in' => 0,
            'warehouse_stock_transfer_out' => 0,
        ]);

        return [$account, $product];
    }

    /** @return array<string, mixed> */
    private function customerPayload(): array
    {
        return [
            'name' => 'Pelanggan DNY',
            'whatsapp' => '081200000001',
            'phone' => '',
            'gender' => 'L',
            'address' => 'Jalan Pelanggan Nomor 2',
            'province_id' => 35,
            'city_id' => 3578,
            'district_id' => 357801,
            'subdistrict_id' => 35780101,
        ];
    }

    private function customer(string $whatsapp = '081200000001', int $memberId = 1): Customer
    {
        $payload = $this->customerPayload();

        return Customer::query()->create([
            'customer_member_id' => $memberId,
            'customer_name' => $payload['name'],
            'customer_whatsapp' => '+62'.substr($whatsapp, 1),
            'customer_phone' => $payload['phone'],
            'customer_gender' => $payload['gender'],
            'customer_birth_date' => null,
            'customer_address' => $payload['address'],
            'customer_province_id' => $payload['province_id'],
            'customer_city_id' => $payload['city_id'],
            'customer_district_id' => $payload['district_id'],
            'customer_subdistrict_id' => $payload['subdistrict_id'],
            'customer_is_deleted' => 0,
            'customer_created_datetime' => now(),
        ]);
    }

    private function regionalReferences(): void
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
}
