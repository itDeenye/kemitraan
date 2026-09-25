<?php

namespace Tests\Feature\Api\V1\Member;

use App\Mail\PaymentSubmittedMail;
use App\Models\GoodsReceive;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Models\MemberGroup;
use App\Models\MemberStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\RewardStockist;
use App\Models\Trx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberPurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_does_not_subtract_pending_transfer_twice_from_available_stock(): void
    {
        $account = $this->createMemberAccount();
        [$product] = $this->createPurchaseReferences();
        DB::table('warehouse_stock')
            ->where('warehouse_stock_warehouse_id', 1)
            ->where('warehouse_stock_product_id', $product->getKey())
            ->update([
                'warehouse_stock_balance' => 3,
                'warehouse_stock_transfer_out' => 3,
            ]);

        $this->actingAs($account, 'member_api');
        $this->postJson('/api/v1/member/purchases/orders', [
            'bank_company_id' => 1,
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.is_preorder', false);

        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 2,
            'warehouse_stock_transfer_out' => 4,
        ]);
    }

    public function test_order_reservation_records_out_mutation_and_cancellation_records_in(): void
    {
        $account = $this->createMemberAccount();
        [$product] = $this->createPurchaseReferences();
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/purchases/options?'.http_build_query([
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ]))->assertOk()
            ->assertJsonPath('data.seller.origin.type', 'warehouse');

        $checkout = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_company_id' => 1,
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.type', 'stock')
            ->assertJsonPath('data.customer', null)
            ->assertJsonPath('data.summary.preorder_quantity', 0)
            ->assertJsonPath('data.summary.bill_amount', 100000)
            ->assertJsonPath('data.actions.can_verify_payment', false)
            ->assertJsonPath('data.actions.can_ship', false);
        $trxId = (int) $checkout->json('data.id');
        $trxCode = (string) $checkout->json('data.code');

        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 0,
            'warehouse_stock_transfer_out' => 1,
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 0,
            'member_stock_transfer_in' => 1,
        ]);
        $this->assertDatabaseHas('warehouse_stock_log', [
            'warehouse_stock_log_warehouse_id' => 1,
            'warehouse_stock_log_product_id' => $product->getKey(),
            'warehouse_stock_log_type' => 'out',
            'warehouse_stock_log_quantity' => 1,
            'warehouse_stock_log_balance' => 0,
            'warehouse_stock_log_note' => "Pemesanan {$trxCode}",
        ]);
        $this->assertDatabaseMissing('member_stock_log', [
            'member_stock_log_member_id' => 1,
            'member_stock_log_product_id' => $product->getKey(),
        ]);

        $this->postJson("/api/v1/member/purchases/orders/{$trxId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status.code', 'cancelled');

        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 1,
            'warehouse_stock_transfer_out' => 0,
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 0,
            'member_stock_transfer_in' => 0,
        ]);
        $this->assertDatabaseHas('warehouse_stock_log', [
            'warehouse_stock_log_warehouse_id' => 1,
            'warehouse_stock_log_product_id' => $product->getKey(),
            'warehouse_stock_log_type' => 'in',
            'warehouse_stock_log_quantity' => 1,
            'warehouse_stock_log_balance' => 1,
            'warehouse_stock_log_note' => "Pembatalan pesanan {$trxCode}",
        ]);
        $this->assertSame(2, DB::table('warehouse_stock_log')
            ->where('warehouse_stock_log_product_id', $product->getKey())
            ->count());
    }

    public function test_checkout_records_voucher_id_and_value_on_transaction(): void
    {
        $account = $this->createMemberAccount();
        [$product] = $this->createPurchaseReferences();
        $voucher = RewardStockist::query()->create([
            'reward_stockist_member_id' => 1,
            'reward_stockist_year' => now()->year,
            'reward_stockist_month' => now()->month,
            'reward_stockist_total_trx_amount' => 1_000_000,
            'reward_stockist_bonus_value' => 25_000,
            'reward_stockist_used_value' => 0,
            'reward_stockist_used_trx_id' => 0,
            'reward_stockist_expiry_date' => today()->addMonth(),
            'reward_stockist_created_datetime' => now(),
        ]);
        $this->actingAs($account, 'member_api');

        $checkout = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_company_id' => 1,
            'shipping_method' => 'pickup',
            'use_voucher' => true,
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.summary.product_total', 100000)
            ->assertJsonPath('data.summary.voucher_id', $voucher->getKey())
            ->assertJsonPath('data.summary.voucher_discount', 25000)
            ->assertJsonPath('data.summary.total_discount', 0)
            ->assertJsonPath('data.summary.grand_total', 75000);

        $this->assertDatabaseHas('trx', [
            'trx_id' => $checkout->json('data.id'),
            'trx_discount_value' => 0,
            'trx_voucher_id' => $voucher->getKey(),
            'trx_voucher_value' => 25000,
        ]);

        $this->getJson('/api/v1/member/purchases/orders')
            ->assertOk()
            ->assertJsonPath('data.results.0.summary.voucher_id', $voucher->getKey())
            ->assertJsonPath('data.results.0.summary.voucher_discount', 25000)
            ->assertJsonPath('data.results.0.summary.grand_total', 75000);
    }

    public function test_voucher_reduces_the_entire_transaction_total_including_shipping_and_insurance(): void
    {
        $account = $this->createMemberAccount();
        $this->createRegionalReferences();
        [$product] = $this->createPurchaseReferences();
        $product->forceFill([
            'product_length' => 10,
            'product_width' => 8,
            'product_height' => 5,
        ])->save();
        $address = MemberAddress::query()->create([
            'member_address_member_id' => 1,
            'member_address_label' => 'Alamat Utama',
            'member_address_recipient' => 'Distributor DNY',
            'member_address_phone' => '081234567890',
            'member_address_full' => 'Jalan Distributor Nomor 1',
            'member_address_province_id' => 35,
            'member_address_city_id' => 3578,
            'member_address_district_id' => 357801,
            'member_address_subdistrict_id' => 35780101,
            'member_address_country_id' => 1,
            'member_address_is_default' => 1,
        ]);
        RewardStockist::query()->create([
            'reward_stockist_member_id' => 1,
            'reward_stockist_year' => now()->year,
            'reward_stockist_month' => now()->month,
            'reward_stockist_total_trx_amount' => 1_000_000,
            'reward_stockist_bonus_value' => 150_000,
            'reward_stockist_used_value' => 0,
            'reward_stockist_used_trx_id' => 0,
            'reward_stockist_expiry_date' => today()->addMonth(),
            'reward_stockist_created_datetime' => now(),
        ]);
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-token',
            'services.stc.client_id' => 9,
        ]);
        Http::fake([
            'https://stc.example.test/api/shipping/price-express' => Http::response([
                'status' => true,
                'details' => [],
                'results' => [[
                    'service' => 'jne',
                    'service_name' => 'JNE Express Reguler',
                    'service_type' => 'REG23',
                    'cost' => 18000,
                    'etd' => '2-3',
                    'drop' => true,
                    'force_insurance' => true,
                    'insurance' => 2500,
                    'logo' => 'https://stcdev-client.esoftdream.co.id/assets/couriers-logo/jne.png',
                ]],
            ]),
        ]);
        $this->actingAs($account, 'member_api');

        $checkout = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_company_id' => 1,
            'address_id' => $address->getKey(),
            'shipping_method' => 'courier_express',
            'courier' => [
                'courier_code' => 'jne',
                'courier_name' => 'JNE Express Reguler',
                'service_type' => 'REG23',
                'cost' => 18000,
                'etd' => '2-3',
                'drop_off_available' => true,
                'insurance' => 2500,
                'force_insurance' => true,
                'logo_url' => 'https://stcdev-client.esoftdream.co.id/assets/couriers-logo/jne.png',
            ],
            'use_voucher' => true,
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.summary.product_total', 100000)
            ->assertJsonPath('data.summary.voucher_discount', 120500)
            ->assertJsonPath('data.summary.after_discount', 0)
            ->assertJsonPath('data.summary.shipping_cost', 18000)
            ->assertJsonPath('data.summary.shipping_cost_insurance', 2500)
            ->assertJsonPath('data.summary.shipping_cost_total', 20500)
            ->assertJsonPath('data.summary.grand_total', 0);

        $this->assertDatabaseHas('trx', [
            'trx_id' => $checkout->json('data.id'),
            'trx_voucher_value' => 120500,
            'trx_grand_total_price' => 0,
            'trx_shipping_cost' => 20500,
            'trx_grand_total_nett_price' => 0,
            'trx_bill_amount' => 0,
        ]);
        $this->assertDatabaseHas('reward_stockist', [
            'reward_stockist_member_id' => 1,
            'reward_stockist_used_value' => 120500,
        ]);
    }

    public function test_express_checkout_persists_complete_provider_shipping_data(): void
    {
        $account = $this->createMemberAccount();
        $this->createRegionalReferences();
        [$product] = $this->createPurchaseReferences();
        $product->forceFill([
            'product_length' => 10,
            'product_width' => 8,
            'product_height' => 5,
        ])->save();
        $address = MemberAddress::query()->create([
            'member_address_member_id' => 1,
            'member_address_label' => 'Alamat Utama',
            'member_address_recipient' => 'Distributor DNY',
            'member_address_phone' => '081234567890',
            'member_address_full' => 'Jalan Distributor Nomor 1',
            'member_address_province_id' => 35,
            'member_address_city_id' => 3578,
            'member_address_district_id' => 357801,
            'member_address_subdistrict_id' => 35780101,
            'member_address_country_id' => 1,
            'member_address_is_default' => 1,
        ]);
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-token',
            'services.stc.client_id' => 9,
        ]);
        Http::fake([
            'https://stc.example.test/api/shipping/price-express' => Http::response([
                'status' => true,
                'details' => [],
                'results' => [[
                    'service' => 'jne',
                    'service_name' => 'JNE Express Reguler',
                    'service_type' => 'REG23',
                    'cost' => 18000,
                    'etd' => '2-3',
                    'drop' => true,
                    'force_insurance' => false,
                    'insurance' => 2500,
                    'logo' => 'https://stcdev-client.esoftdream.co.id/assets/couriers-logo/jne.png',
                ], [
                    'service' => 'jnt',
                    'service_name' => 'J&T EZ',
                    'service_type' => 'EZ',
                    'cost' => 19000,
                    'etd' => '1-2',
                    'drop' => true,
                    'force_insurance' => true,
                    'insurance' => 2000,
                    'logo' => 'https://stcdev-client.esoftdream.co.id/assets/couriers-logo/jnt.png',
                ]],
            ]),
        ]);
        $this->actingAs($account, 'member_api');

        $this->postJson('/api/v1/member/shipping/express/rates', [
            'address_id' => $address->getKey(),
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.results.0.force_insurance', false)
            ->assertJsonPath('data.results.0.insurance', 0)
            ->assertJsonPath('data.results.1.force_insurance', true)
            ->assertJsonPath('data.results.1.insurance', 2000);

        $checkout = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_company_id' => 1,
            'address_id' => $address->getKey(),
            'shipping_method' => 'courier_express',
            'courier' => [
                'courier_code' => 'jne',
                'courier_name' => 'JNE Express Reguler',
                'service_type' => 'REG23',
                'cost' => 18000,
                'etd' => '2-3',
                'drop_off_available' => true,
                'insurance' => 2500,
                'force_insurance' => false,
                'logo_url' => 'https://stcdev-client.esoftdream.co.id/assets/couriers-logo/jne.png',
            ],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.shipping.method', 'courier_express')
            ->assertJsonPath('data.summary.shipping_cost', 18000)
            ->assertJsonPath('data.summary.shipping_cost_insurance', 0)
            ->assertJsonPath('data.summary.shipping_cost_total', 18000);

        $this->assertDatabaseHas('shipping_courier_express', [
            'shipping_courier_express_ref_type' => 'trx',
            'shipping_courier_express_ref_id' => $checkout->json('data.id'),
            'shipping_courier_express_type' => 'REG23',
            'shipping_courier_express_expedition_name' => 'jne',
            'shipping_courier_express_expedition_service' => 'JNE Express Reguler',
            'shipping_courier_express_etd' => '2-3',
            'shipping_courier_express_pickup_method' => 'DROP-OFF',
            'shipping_courier_express_cost' => 18000,
            'shipping_courier_express_insurance_is_force' => 0,
            'shipping_courier_express_insurance' => 0,
        ]);
    }

    public function test_express_checkout_rejects_legacy_courier_payload(): void
    {
        $account = $this->createMemberAccount();
        $this->createRegionalReferences();
        [$product] = $this->createPurchaseReferences();
        $address = MemberAddress::query()->create([
            'member_address_member_id' => 1,
            'member_address_recipient' => 'Distributor DNY',
            'member_address_phone' => '081234567890',
            'member_address_full' => 'Jalan Distributor Nomor 1',
            'member_address_province_id' => 35,
            'member_address_city_id' => 3578,
            'member_address_district_id' => 357801,
            'member_address_subdistrict_id' => 35780101,
            'member_address_country_id' => 1,
        ]);
        $this->actingAs($account, 'member_api');
        Http::preventStrayRequests();

        $this->postJson('/api/v1/member/purchases/orders', [
            'bank_company_id' => 1,
            'address_id' => $address->getKey(),
            'shipping_method' => 'courier_express',
            'shipping_cost' => 25000,
            'courier' => [
                'name' => 'JNE',
                'service' => 'Regpack',
                'type' => 'REGPACK',
                'etd' => '2-3 hari',
                'drop' => true,
                'insurance' => 2000,
                'force_insurance' => true,
            ],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonValidationErrors([
                'shipping_cost',
                'courier.name',
                'courier.service',
                'courier.type',
                'courier.courier_code',
                'courier.courier_name',
                'courier.service_type',
                'courier.cost',
                'courier.drop',
                'courier.drop_off_available',
            ]);

        Http::assertNothingSent();
    }

    public function test_company_purchase_cannot_reserve_beyond_warehouse_balance(): void
    {
        $account = $this->createMemberAccount();
        [$firstProduct, $secondProduct] = $this->createPurchaseReferences();
        DB::table('warehouse_stock')
            ->where('warehouse_stock_warehouse_id', 1)
            ->where('warehouse_stock_product_id', $secondProduct->getKey())
            ->delete();
        $this->actingAs($account, 'member_api');

        $this->postJson('/api/v1/member/purchases/orders', [
            'bank_company_id' => 1,
            'shipping_method' => 'pickup',
            'items' => [
                ['product_id' => $firstProduct->getKey(), 'quantity' => 1],
                ['product_id' => $secondProduct->getKey(), 'quantity' => 2],
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath(
                'message',
                'Pesanan inden tidak dapat dibuat karena stok di seluruh jaringan hingga gudang tidak mencukupi.',
            );

        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $firstProduct->getKey(),
            'warehouse_stock_balance' => 1,
            'warehouse_stock_transfer_out' => 0,
        ]);
        $this->assertDatabaseMissing('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $secondProduct->getKey(),
        ]);
        $this->assertDatabaseCount('trx', 0);
        $this->assertDatabaseCount('warehouse_stock_log', 0);

        $this->getJson('/api/v1/member/purchases/catalog/products')
            ->assertOk()
            ->assertJsonPath('data.results.1.stock.available', 0)
            ->assertJsonPath('data.results.1.stock.status.code', 'out_of_stock')
            ->assertJsonPath('data.results.1.stock.requires_preorder', false);
    }

    public function test_reseller_purchase_from_agent_only_offers_manual_and_pickup(): void
    {
        $sellerAccount = $this->createMemberAccount(1, 'AGT-001', 'purchase.agent');
        $buyerAccount = $this->createMemberAccount(2, 'RSL-002', 'purchase.reseller');
        $this->createMemberAccount(3, 'DST-003', 'purchase.distributor');
        [$product] = $this->createPurchaseReferences();
        $this->createRegionalReferences();

        Member::query()->whereKey(1)->update([
            'member_member_level_id' => 2,
            'member_parent_member_id' => 3,
        ]);
        MemberAddress::query()->create([
            'member_address_member_id' => 1,
            'member_address_label' => 'Alamat Agent',
            'member_address_recipient' => 'Agent DNY',
            'member_address_phone' => '081234567890',
            'member_address_full' => 'Jalan Agent Nomor 1',
            'member_address_province_id' => 31,
            'member_address_city_id' => 3171,
            'member_address_district_id' => 317101,
            'member_address_subdistrict_id' => 31710101,
            'member_address_country_id' => 1,
            'member_address_is_default' => 1,
        ]);
        MemberBankAccount::query()->create([
            'member_bank_account_member_id' => 1,
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Agent DNY',
            'member_bank_account_number' => '1234567890',
            'member_bank_account_is_active' => 1,
            'member_bank_account_is_default' => 1,
        ]);
        MemberBankAccount::query()->create([
            'member_bank_account_member_id' => 1,
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Agent DNY Tidak Aktif',
            'member_bank_account_number' => '0987654321',
            'member_bank_account_is_active' => 0,
            'member_bank_account_is_default' => 0,
        ]);
        MemberBankAccount::query()->create([
            'member_bank_account_member_id' => 3,
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Distributor DNY',
            'member_bank_account_number' => '1234567893',
            'member_bank_account_is_active' => 1,
            'member_bank_account_is_default' => 1,
        ]);
        Member::query()->whereKey(2)->update([
            'member_member_level_id' => 3,
            'member_parent_member_id' => 1,
        ]);
        $buyerAccount->unsetRelation('member');
        $product->forceFill([
            'product_length' => 10,
            'product_width' => 8,
            'product_height' => 5,
        ])->save();
        MemberStock::query()->create([
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 0,
            'member_stock_transfer_in' => 0,
            'member_stock_transfer_out' => 0,
        ]);
        DB::table('warehouse_stock')
            ->where('warehouse_stock_warehouse_id', 1)
            ->where('warehouse_stock_product_id', $product->getKey())
            ->update(['warehouse_stock_balance' => 10]);
        $address = MemberAddress::query()->create([
            'member_address_member_id' => 2,
            'member_address_label' => 'Rumah',
            'member_address_recipient' => 'Reseller DNY',
            'member_address_phone' => '081234567891',
            'member_address_full' => 'Jalan Reseller Nomor 2',
            'member_address_province_id' => 35,
            'member_address_city_id' => 3578,
            'member_address_district_id' => 357801,
            'member_address_subdistrict_id' => 35780101,
            'member_address_country_id' => 1,
            'member_address_is_default' => 1,
        ]);
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-token',
            'services.stc.client_id' => 9,
        ]);
        Http::fake([
            'https://stc.example.test/api/shipping/price-express' => Http::response([
                'status' => true,
                'details' => [],
                'results' => [[
                    'service' => 'jne',
                    'service_name' => 'JNE Express Reguler',
                    'service_type' => 'REG23',
                    'cost' => 25000,
                    'etd' => '2-3',
                    'drop' => true,
                    'force_insurance' => true,
                    'insurance' => 2500,
                    'logo' => 'https://stc.example.test/jne.png',
                ]],
            ]),
        ]);
        $this->actingAs($buyerAccount, 'member_api');

        $this->getJson('/api/v1/member/purchases/options')
            ->assertOk()
            ->assertJsonPath('data.buyer.destination.address_id', $address->getKey())
            ->assertJsonPath('data.buyer.destination.province_name', 'Jawa Timur')
            ->assertJsonPath('data.buyer.destination.city_name', 'Surabaya')
            ->assertJsonPath('data.buyer.destination.district_id', 357801)
            ->assertJsonPath('data.buyer.destination.district_name', 'Tegalsari')
            ->assertJsonPath('data.buyer.destination.subdistrict_id', 35780101)
            ->assertJsonPath('data.buyer.destination.subdistrict_name', 'Kedungdoro')
            ->assertJsonPath('data.seller.type', 'agent')
            ->assertJsonPath('data.seller.origin.district_id', 317101)
            ->assertJsonPath('data.seller.origin.district_name', 'Kebayoran Baru')
            ->assertJsonPath('data.seller.origin.subdistrict_id', 31710101)
            ->assertJsonPath('data.seller.origin.subdistrict_name', 'Senayan')
            ->assertJsonCount(1, 'data.banks')
            ->assertJsonPath('data.banks.0.account_number', '1234567890')
            ->assertJsonPath('data.banks.0.is_default', true)
            ->assertJsonCount(0, 'data.spread_payment_banks')
            ->assertJsonCount(2, 'data.shipping_methods')
            ->assertJsonPath('data.shipping_methods.0.code', 'courier_manual')
            ->assertJsonPath('data.shipping_methods.0.name', 'Kurir')
            ->assertJsonPath('data.shipping_methods.1.code', 'pickup');

        $this->postJson('/api/v1/member/shipping/express/rates', [
            'address_id' => $address->getKey(),
            'couriers' => ['jne'],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 6]],
        ])->assertOk()
            ->assertJsonPath('data.shipping_method', 'courier_express')
            ->assertJsonPath('data.origin.source_type', 'warehouse')
            ->assertJsonPath('data.origin.name', 'Gudang Pusat')
            ->assertJsonPath('data.origin.address', 'Jakarta')
            ->assertJsonPath('data.origin.district_id', 317101)
            ->assertJsonPath('data.origin.subdistrict_id', 31710101);

        $this->postJson('/api/v1/member/purchases/orders', [
            'address_id' => $address->getKey(),
            'bank_company_id' => 1,
            'shipping_method' => 'courier_manual',
            'courier' => [
                'courier_code' => 'jne',
                'courier_name' => 'JNE Express Reguler',
                'service_type' => 'REG23',
                'cost' => 25000,
                'etd' => '2-3',
                'drop_off_available' => true,
                'force_insurance' => true,
                'insurance' => 2500,
                'logo_url' => 'https://stc.example.test/jne.png',
            ],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 6]],
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Metode pengiriman tidak tersedia untuk penjual transaksi ini.');

        $checkout = $this->postJson('/api/v1/member/purchases/orders', [
            'address_id' => $address->getKey(),
            'bank_company_id' => 1,
            'shipping_method' => 'courier_express',
            'courier' => [
                'courier_code' => 'jne',
                'courier_name' => 'JNE Express Reguler',
                'service_type' => 'REG23',
                'cost' => 25000,
                'etd' => '2-3',
                'drop_off_available' => true,
                'force_insurance' => true,
                'insurance' => 2500,
                'logo_url' => 'https://stc.example.test/jne.png',
            ],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 6]],
        ])->assertOk()
            ->assertJsonPath('data.seller.type', 'agent')
            ->assertJsonPath('data.is_preorder', true)
            ->assertJsonPath('data.summary.shipping_cost', 25000)
            ->assertJsonPath('data.summary.shipping_cost_insurance', 2500)
            ->assertJsonPath('data.summary.shipping_cost_total', 27500)
            ->assertJsonPath('data.shipping.cost', 25000)
            ->assertJsonPath('data.shipping.insurance', 2500)
            ->assertJsonPath('data.shipping.total_cost', 27500);
        $this->assertDatabaseHas('shipping_courier_express', [
            'shipping_courier_express_ref_id' => $checkout->json('data.id'),
            'shipping_courier_express_expedition_name' => 'jne',
            'shipping_courier_express_expedition_service' => 'JNE Express Reguler',
            'shipping_courier_express_origin_name' => 'Gudang Pusat',
            'shipping_courier_express_origin_address' => 'Jakarta',
            'shipping_courier_express_cost' => 25000,
            'shipping_courier_express_insurance' => 2500,
        ]);
        $this->assertMatchesRegularExpression(
            '~^TRX/AGT/RSL/\d{6}/[A-Z0-9]{6}$~',
            (string) $checkout->json('data.code'),
        );
        $this->getJson('/api/v1/member/purchases/orders/'.$checkout->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.seller.origin.name', 'Gudang Pusat')
            ->assertJsonPath('data.seller.origin.address', 'Jakarta')
            ->assertJsonPath('data.seller.origin.province.name', 'DKI Jakarta')
            ->assertJsonPath('data.seller.origin.city.name', 'Jakarta Selatan')
            ->assertJsonPath('data.seller.origin.district.name', 'Kebayoran Baru')
            ->assertJsonPath('data.seller.origin.subdistrict.id', 31710101)
            ->assertJsonPath('data.seller.origin.subdistrict.name', 'Senayan')
            ->assertJsonPath('data.buyer.destination.name', 'Reseller DNY')
            ->assertJsonPath('data.buyer.destination.phone', '081234567891')
            ->assertJsonPath('data.buyer.destination.address', 'Jalan Reseller Nomor 2')
            ->assertJsonPath('data.buyer.destination.province.name', 'Jawa Timur')
            ->assertJsonPath('data.buyer.destination.city.name', 'Surabaya')
            ->assertJsonPath('data.buyer.destination.district.name', 'Tegalsari')
            ->assertJsonPath('data.buyer.destination.subdistrict.id', 35780101)
            ->assertJsonPath('data.buyer.destination.subdistrict.name', 'Kedungdoro');
        $this->assertDatabaseHas('notification', [
            'notification_user_type' => 'member',
            'notification_user_id' => 1,
            'notification_category' => 'stock',
            'notification_ref_table' => 'trx_sale',
            'notification_is_read' => 0,
        ]);

        $this->postJson('/api/v1/member/purchases/orders', [
            'address_id' => $address->getKey(),
            'bank_company_id' => 1,
            'shipping_method' => 'courier_express',
            'courier' => [
                'courier_code' => 'JNE',
                'courier_name' => 'Regpack',
                'service_type' => 'REGPACK',
                'cost' => 25000,
                'etd' => '2-3 hari',
                'drop_off_available' => false,
                'insurance' => 0,
                'force_insurance' => false,
                'logo_url' => null,
            ],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        Trx::query()->findOrFail((int) $checkout->json('data.id'))->update([
            'trx_status' => 'processing',
            'trx_status_datetime' => now(),
        ]);
        $this->actingAs($sellerAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/options')
            ->assertOk()
            ->assertJsonPath('data.seller.type', 'distributor')
            ->assertJsonCount(0, 'data.spread_payment_banks')
            ->assertJsonPath('data.shipping_methods.0.code', 'courier_manual')
            ->assertJsonPath('data.shipping_methods.0.name', 'Kurir')
            ->assertJsonPath('data.shipping_methods.1.code', 'pickup');
        $this->getJson('/api/v1/member/sales/orders')
            ->assertOk()
            ->assertJsonPath('data.results.0.is_preorder', true)
            ->assertJsonMissingPath('data.results.0.actions.can_replenish');

        $this->postJson('/api/v1/member/sales/orders/'.$checkout->json('data.id').'/replenish')
            ->assertNotFound();
    }

    public function test_distributor_purchase_payment_and_goods_receive_workflow(): void
    {
        Mail::fake();
        $account = $this->createMemberAccount();
        $this->createRegionalReferences();
        [$firstProduct, $secondProduct] = $this->createPurchaseReferences();
        DB::table('warehouse_stock')->update(['warehouse_stock_balance' => 2]);
        $address = MemberAddress::query()->create([
            'member_address_member_id' => 1,
            'member_address_label' => 'Alamat Utama',
            'member_address_recipient' => 'Distributor DNY',
            'member_address_phone' => '081234567890',
            'member_address_full' => 'Jalan Distributor Nomor 1',
            'member_address_province_id' => 35,
            'member_address_city_id' => 3578,
            'member_address_district_id' => 357801,
            'member_address_subdistrict_id' => 35780101,
            'member_address_country_id' => 1,
            'member_address_is_default' => 1,
        ]);
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/purchases/options')
            ->assertOk()
            ->assertJsonPath('data.buyer.destination.address_id', $address->getKey())
            ->assertJsonPath('data.buyer.destination.province_name', 'Jawa Timur')
            ->assertJsonPath('data.buyer.destination.district_id', 357801)
            ->assertJsonPath('data.buyer.destination.district_name', 'Tegalsari')
            ->assertJsonPath('data.seller.type', 'warehouse')
            ->assertJsonPath('data.seller.id', 1)
            ->assertJsonPath('data.seller.origin.province_name', 'DKI Jakarta')
            ->assertJsonPath('data.seller.origin.district_id', 317101)
            ->assertJsonPath('data.seller.origin.district_name', 'Kebayoran Baru')
            ->assertJsonPath('data.seller.origin.subdistrict_id', 31710101)
            ->assertJsonPath('data.seller.origin.subdistrict_name', 'Senayan')
            ->assertJsonMissingPath('data.warehouses')
            ->assertJsonCount(1, 'data.banks')
            ->assertJsonPath('data.banks.0.type', 'company')
            ->assertJsonPath('data.banks.0.name', 'Bank DNY')
            ->assertJsonCount(0, 'data.spread_payment_banks')
            ->assertJsonCount(2, 'data.shipping_methods')
            ->assertJsonPath('data.shipping_methods.0.code', 'courier_express')
            ->assertJsonPath('data.shipping_methods.1.code', 'pickup');

        $this->postJson('/api/v1/member/purchases/orders', [
            'bank_company_id' => 1,
            'address_id' => $address->getKey(),
            'shipping_method' => 'courier_manual',
            'courier' => [
                'courier_code' => 'jne',
                'courier_name' => 'JNE Express Reguler',
                'service_type' => 'REG23',
                'cost' => 18000,
                'etd' => '2-3',
                'drop_off_available' => true,
                'force_insurance' => false,
                'insurance' => 2500,
                'logo_url' => 'https://stc.example.test/jne.png',
            ],
            'items' => [['product_id' => $firstProduct->getKey(), 'quantity' => 1]],
        ])->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Metode pengiriman tidak tersedia untuk penjual transaksi ini.',
                'error_code' => 'process_error',
            ]);

        $this->getJson('/api/v1/member/purchases/catalog/products')
            ->assertOk()
            ->assertJsonCount(2, 'data.results')
            ->assertJsonPath('data.results.0.stock.available', 2);

        $checkout = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_company_id' => 1,
            'shipping_method' => 'pickup',
            'use_voucher' => false,
            'items' => [
                ['product_id' => $firstProduct->getKey(), 'quantity' => 2],
                ['product_id' => $secondProduct->getKey(), 'quantity' => 2],
            ],
        ])->assertOk()
            ->assertJsonPath('data.is_preorder', false)
            ->assertJsonPath('data.items.0.product.bpom_number', 'NA18250100001')
            ->assertJsonPath('data.summary.total_quantity', 4)
            ->assertJsonPath('data.summary.preorder_quantity', 0)
            ->assertJsonPath('data.summary.shipping_cost', 0)
            ->assertJsonPath('data.summary.grand_total', 300000)
            ->assertJsonPath('data.seller.id', 1)
            ->assertJsonPath('data.seller.type', 'warehouse')
            ->assertJsonPath('data.status.code', 'waiting_stock_screening')
            ->assertJsonPath('data.actions.can_upload_payment', false)
            ->assertJsonPath('data.payment.status.code', 'pending')
            ->assertJsonPath('data.payment.bank.code', 'DNY')
            ->assertJsonPath('data.payment.bank.name', 'Bank DNY')
            ->assertJsonPath('data.payment.bank.account_name', 'PT DNY')
            ->assertJsonPath('data.payment.bank.account_number', '1234567890')
            ->assertJsonPath('data.shipping.method', 'pickup')
            ->assertJsonPath('data.shipping.verification_status', 'pending');
        $pickupCode = (string) $checkout->json('data.shipping.code');
        $this->assertMatchesRegularExpression('/^\d{5}$/', $pickupCode);
        $this->approveStockScreening((int) $checkout->json('data.id'));

        $this->getJson('/api/v1/member/purchases/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.seller.origin.name', 'Gudang Pusat')
            ->assertJsonPath('data.results.0.buyer.type', 'member')
            ->assertJsonPath('data.results.0.buyer.id', 1)
            ->assertJsonPath('data.results.0.buyer.code', 'DIST-001')
            ->assertJsonPath('data.results.0.buyer.name', 'Distributor 1')
            ->assertJsonPath('data.results.0.buyer.destination.name', 'Distributor 1')
            ->assertJsonPath('data.results.0.product_preview.name', 'Serum DNY')
            ->assertJsonPath('data.results.0.product_preview.bpom_number', 'NA18250100001')
            ->assertJsonPath('data.results.0.product_preview.image', 'https://cdn.example.test/products/serum.webp')
            ->assertJsonPath('data.results.0.product_preview.quantity', 2)
            ->assertJsonPath('data.results.0.product_preview.other_product_count', 1)
            ->assertJsonPath('data.results.0.actions.can_cancel', true)
            ->assertJsonPath('data.results.0.actions.can_upload_payment', true)
            ->assertJsonPath('data.results.0.actions.can_receive', false)
            ->assertJsonPath('data.results.0.payment.bank.name', 'Bank DNY')
            ->assertJsonPath('data.results.0.payment.bank.account_name', 'PT DNY')
            ->assertJsonPath('data.results.0.payment.bank.account_number', '1234567890');

        $trxId = (int) $checkout->json('data.id');
        $this->assertDatabaseHas('trx_detail', [
            'trx_detail_trx_id' => $trxId,
            'trx_detail_product_id' => $firstProduct->getKey(),
            'trx_detail_product_bpom_number' => 'NA18250100001',
            'trx_detail_qty' => 2,
        ]);

        $this->postJson("/api/v1/member/purchases/orders/{$trxId}/payment", [
            'receipt_url' => 'https://cdn.example.test/payment/receipt.webp',
            'note' => 'Transfer lunas',
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'submitted')
            ->assertJsonPath('data.amount', 300000);
        $this->assertDatabaseHas('trx', [
            'trx_id' => $trxId,
            'trx_status' => 'waiting_payment_approval',
        ]);
        Mail::assertSent(PaymentSubmittedMail::class, function (PaymentSubmittedMail $mail) use ($trxId): bool {
            return $mail->buyerName === 'Distributor 1'
                && $mail->orderUrl === url("/member/transactions/orders/{$trxId}")
                && $mail->paymentAmount !== '';
        });

        Trx::query()->findOrFail($trxId)->update([
            'trx_status' => 'received',
            'trx_status_datetime' => now(),
        ]);
        $pickupId = (int) DB::table('shipping_pickup')
            ->where('shipping_pickup_ref_type', 'trx')
            ->where('shipping_pickup_ref_id', $trxId)
            ->value('shipping_pickup_id');
        DB::table('shipping_pickup')->where('shipping_pickup_id', $pickupId)->update([
            'shipping_pickup_delivery_note_number' => 'SJ-2026-0001',
        ]);
        DB::table('shipping_detail')->insert([
            [
                'shipping_detail_shipping_type' => 'pickup',
                'shipping_detail_shipping_id' => $pickupId,
                'shipping_detail_product_id' => $firstProduct->getKey(),
                'shipping_detail_batch_number' => 'BATCH-A',
                'shipping_detail_qty' => 1,
                'shipping_detail_expire_date' => now()->addYear()->toDateString(),
            ],
            [
                'shipping_detail_shipping_type' => 'pickup',
                'shipping_detail_shipping_id' => $pickupId,
                'shipping_detail_product_id' => $firstProduct->getKey(),
                'shipping_detail_batch_number' => 'BATCH-B',
                'shipping_detail_qty' => 1,
                'shipping_detail_expire_date' => now()->addYears(2)->toDateString(),
            ],
            [
                'shipping_detail_shipping_type' => 'pickup',
                'shipping_detail_shipping_id' => $pickupId,
                'shipping_detail_product_id' => $secondProduct->getKey(),
                'shipping_detail_batch_number' => 'BATCH-C',
                'shipping_detail_qty' => 2,
                'shipping_detail_expire_date' => now()->addYear()->toDateString(),
            ],
        ]);
        DB::table('shipping_pickup_status')->insert([
            [
                'shipping_pickup_status_shipping_pickup_id' => $pickupId,
                'shipping_pickup_status_ref_type' => 'trx',
                'shipping_pickup_status_ref_id' => $trxId,
                'shipping_pickup_status_value' => 'ready_to_pickup',
                'shipping_pickup_status_datetime' => now(),
            ],
            [
                'shipping_pickup_status_shipping_pickup_id' => $pickupId,
                'shipping_pickup_status_ref_type' => 'trx',
                'shipping_pickup_status_ref_id' => $trxId,
                'shipping_pickup_status_value' => 'picked_up',
                'shipping_pickup_status_datetime' => now(),
            ],
        ]);

        $this->getJson("/api/v1/member/purchases/orders/{$trxId}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_cancel', false)
            ->assertJsonPath('data.actions.can_upload_payment', false)
            ->assertJsonPath('data.actions.can_receive', true)
            ->assertJsonPath('data.items.0.product.bpom_number', 'NA18250100001')
            ->assertJsonPath('data.actions.requires_delivery_note_number', true)
            ->assertJsonPath('data.actions.can_show_pickup_code', false)
            ->assertJsonPath('data.shipping.code', null);

        $this->getJson('/api/v1/member/purchases/goods-receipts')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.purchase_order_id', $trxId)
            ->assertJsonMissingPath('data.results.0.delivery_note_number')
            ->assertJsonPath('data.results.0.status.code', 'ready_to_receive')
            ->assertJsonPath('data.results.0.actions.can_receive', true)
            ->assertJsonPath('data.results.0.actions.requires_delivery_note_number', true);

        $this->postJson("/api/v1/member/purchases/goods-receipts/{$trxId}/confirm", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_note_number');

        $this->postJson("/api/v1/member/purchases/goods-receipts/{$trxId}/confirm", [
            'delivery_note_number' => 'SJ-TIDAK-SESUAI',
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');
        $this->assertDatabaseMissing('shipping_pickup_status', [
            'shipping_pickup_status_ref_type' => 'trx',
            'shipping_pickup_status_ref_id' => $trxId,
            'shipping_pickup_status_value' => 'completed',
        ]);

        $this->postJson("/api/v1/member/purchases/goods-receipts/{$trxId}/confirm", [
            'delivery_note_number' => 'SJ-2026-0001',
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'completed')
            ->assertJsonPath('data.delivery_note_number', 'SJ-2026-0001')
            ->assertJsonPath('data.pickup_verification_status', 'completed')
            ->assertJsonPath('data.ordered_items.0.bpom_number', 'NA18250100001')
            ->assertJsonPath(
                'data.ordered_items.0.image',
                'https://cdn.example.test/products/serum.webp',
            )
            ->assertJsonPath('data.received_items.0.bpom_number', 'NA18250100001')
            ->assertJsonPath(
                'data.received_items.0.image',
                'https://cdn.example.test/products/serum.webp',
            )
            ->assertJsonCount(3, 'data.received_items');

        $this->getJson("/api/v1/member/purchases/goods-receipts/{$trxId}")
            ->assertOk()
            ->assertJsonPath('data.delivery_note_number', 'SJ-2026-0001');
        $this->assertDatabaseHas('shipping_pickup_status', [
            'shipping_pickup_status_ref_type' => 'trx',
            'shipping_pickup_status_ref_id' => $trxId,
            'shipping_pickup_status_value' => 'completed',
        ]);

        $this->assertMatchesRegularExpression(
            '~^GRN/\d{6}/[A-Z0-9]{6}$~',
            (string) GoodsReceive::query()
                ->where('goods_receive_trx_id', $trxId)
                ->value('goods_receive_number'),
        );

        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $firstProduct->getKey(),
            'member_stock_balance' => 2,
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $secondProduct->getKey(),
            'member_stock_balance' => 2,
        ]);
        $this->assertDatabaseCount('goods_receive_detail', 3);
        $this->assertDatabaseCount('member_stock_log', 2);
    }

    public function test_upgraded_distributors_pay_company_and_contribute_spread_to_direct_distributor_upline(): void
    {
        $mainDistributorAccount = $this->createMemberAccount(1, 'DST-MAIN', 'purchase.main-distributor');
        $firstUpgradeAccount = $this->createMemberAccount(2, 'DST-UP-001', 'purchase.first-upgrade');
        $secondUpgradeAccount = $this->createMemberAccount(3, 'DST-UP-002', 'purchase.second-upgrade');
        [$product] = $this->createPurchaseReferences();
        DB::table('warehouse_stock')
            ->where('warehouse_stock_warehouse_id', 1)
            ->where('warehouse_stock_product_id', $product->getKey())
            ->update(['warehouse_stock_balance' => 10]);
        Member::query()->whereKey(2)->update(['member_parent_member_id' => 1]);
        Member::query()->whereKey(3)->update(['member_parent_member_id' => 2]);
        $mainDistributorAccount->unsetRelation('member');
        $firstUpgradeAccount->unsetRelation('member');
        $secondUpgradeAccount->unsetRelation('member');

        $this->actingAs($secondUpgradeAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/options')
            ->assertOk()
            ->assertJsonPath('data.seller.type', 'warehouse')
            ->assertJsonPath('data.seller.id', 1)
            ->assertJsonCount(1, 'data.banks')
            ->assertJsonPath('data.banks.0.type', 'company')
            ->assertJsonPath('data.banks.0.account_name', 'PT DNY')
            ->assertJsonCount(1, 'data.spread_payment_banks')
            ->assertJsonPath('data.spread_payment_banks.0.type', 'spread_payment')
            ->assertJsonPath('data.spread_payment_banks.0.account_name', 'DNY Spread Payment')
            ->assertJsonPath('data.shipping_methods.0.code', 'courier_express')
            ->assertJsonPath('data.shipping_methods.1.code', 'pickup');

        $secondUpgradeOrder = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_company_id' => 1,
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.seller.type', 'warehouse')
            ->assertJsonPath('data.payment.bank.account_name', 'PT DNY')
            ->assertJsonPath('data.payment.spread_payment.amount', 1000)
            ->assertJsonPath('data.payment.spread_payment.bank.account_name', 'DNY Spread Payment');
        $secondUpgradeTrxId = (int) $secondUpgradeOrder->json('data.id');
        $this->approveStockScreening($secondUpgradeTrxId);
        $this->assertDatabaseHas('trx_spread_payment', [
            'trx_spread_payment_trx_id' => $secondUpgradeTrxId,
            'trx_spread_payment_upline_id' => 2,
            'trx_spread_payment_member_id' => 3,
            'trx_spread_payment_percentage' => 1,
            'trx_spread_payment_amount' => 1000,
        ]);

        $this->postJson("/api/v1/member/purchases/orders/{$secondUpgradeTrxId}/payment", [
            'receipt_url' => 'https://cdn.example.test/payment/main-only.webp',
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath('message', 'Bukti pembagian pembayaran wajib diunggah.');

        $this->postJson("/api/v1/member/purchases/orders/{$secondUpgradeTrxId}/payment", [
            'receipt_url' => 'https://cdn.example.test/payment/main-second-upgrade.webp',
            'spread_receipt_url' => 'https://cdn.example.test/payment/spread-second-upgrade.webp',
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'submitted')
            ->assertJsonPath('data.spread_payment.status', 'submitted');

        $this->actingAs($firstUpgradeAccount, 'member_api');
        $firstUpgradeOrder = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_company_id' => 1,
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.seller.type', 'warehouse')
            ->assertJsonPath('data.payment.bank.account_name', 'PT DNY')
            ->assertJsonPath('data.payment.spread_payment.amount', 1000);
        $this->assertDatabaseHas('trx_spread_payment', [
            'trx_spread_payment_trx_id' => $firstUpgradeOrder->json('data.id'),
            'trx_spread_payment_upline_id' => 1,
            'trx_spread_payment_member_id' => 2,
            'trx_spread_payment_amount' => 1000,
        ]);

        $this->actingAs($mainDistributorAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/options')
            ->assertOk()
            ->assertJsonPath('data.seller.type', 'warehouse')
            ->assertJsonCount(0, 'data.spread_payment_banks');
    }

    public function test_member_cannot_read_another_members_purchase(): void
    {
        $owner = $this->createMemberAccount();
        $other = $this->createMemberAccount(2, 'DIST-002', 'purchase.other');
        [$product] = $this->createPurchaseReferences();
        $this->actingAs($owner, 'member_api');

        $checkout = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_company_id' => 1,
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertOk();

        $this->actingAs($other, 'member_api');

        $this->getJson('/api/v1/member/purchases/orders/'.$checkout->json('data.id'))
            ->assertNotFound();
    }

    public function test_express_goods_receipt_waits_for_finished_stc_callback(): void
    {
        $this->createRegionalReferences();
        $account = $this->createMemberAccount();
        [$product] = $this->createPurchaseReferences();
        DB::table('trx')->insert([
            'trx_id' => 1,
            'trx_code' => 'TRX-CMP-DST-000001-TEST',
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
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);
        DB::table('trx_detail')->insert([
            'trx_detail_trx_id' => 1,
            'trx_detail_product_id' => $product->getKey(),
            'trx_detail_product_code' => $product->product_code,
            'trx_detail_product_name' => $product->product_name,
            'trx_detail_product_price' => 100000,
            'trx_detail_nett_price' => 100000,
            'trx_detail_qty' => 1,
        ]);
        DB::table('shipping_courier_express')->insert([
            'shipping_courier_express_id' => 1,
            'shipping_courier_express_ref_type' => 'trx',
            'shipping_courier_express_ref_id' => 1,
            'shipping_courier_express_order_id' => 'STC-EXP-RECEIVE-001',
            'shipping_courier_express_delivery_note_number' => 'SJ-EXP-RECEIVE-001',
            'shipping_courier_express_origin_address' => 'Jakarta',
            'shipping_courier_express_origin_subdistrict_id' => 31710101,
            'shipping_courier_express_destination_address' => 'Surabaya',
            'shipping_courier_express_destination_subdistrict_id' => 35780101,
        ]);
        DB::table('shipping_detail')->insert([
            'shipping_detail_shipping_type' => 'courier_express',
            'shipping_detail_shipping_id' => 1,
            'shipping_detail_product_id' => $product->getKey(),
            'shipping_detail_batch_number' => 'BATCH-EXP-RECEIVE-001',
            'shipping_detail_qty' => 1,
            'shipping_detail_expire_date' => now()->addYear()->toDateString(),
        ]);
        DB::table('shipping_courier_express_status')->insert([
            'shipping_courier_express_status_shipping_courier_express_id' => 1,
            'shipping_courier_express_status_ref_type' => 'trx',
            'shipping_courier_express_status_ref_id' => 1,
            'shipping_courier_express_status_value' => 'processed_packages',
            'shipping_courier_express_status_datetime' => now(),
            'shipping_courier_express_status_external_ref_code' => 'STC-EXP-RECEIVE-001',
        ]);
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/purchases/goods-receipts')
            ->assertOk()
            ->assertJsonCount(0, 'data.results');
        $this->getJson('/api/v1/member/purchases/orders/summary')
            ->assertOk()
            ->assertJsonPath('data.goods_receipts.ready_to_receive', 0);
        $this->postJson('/api/v1/member/purchases/goods-receipts/1/confirm', [
            'delivery_note_number' => 'SJ-EXP-RECEIVE-001',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Pengiriman belum selesai dan belum dapat diterima.');

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'finished_packages',
            'data' => [[
                'order_id' => 'STC-EXP-RECEIVE-001',
                'finished_at' => now()->toDateTimeString(),
            ]],
        ])->assertOk();

        $this->getJson('/api/v1/member/purchases/goods-receipts')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.actions.can_receive', true);
        $this->getJson('/api/v1/member/purchases/orders/summary')
            ->assertOk()
            ->assertJsonPath('data.goods_receipts.ready_to_receive', 1);
        $this->postJson('/api/v1/member/purchases/goods-receipts/1/confirm', [
            'delivery_note_number' => 'SJ-EXP-RECEIVE-001',
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'completed')
            ->assertJsonPath('data.received_items.0.batch_number', 'BATCH-EXP-RECEIVE-001');
        $this->getJson('/api/v1/member/purchases/orders/summary')
            ->assertOk()
            ->assertJsonPath('data.goods_receipts.ready_to_receive', 0);
    }

    private function createMemberAccount(
        int $memberId = 1,
        string $code = 'DIST-001',
        string $username = 'purchase.distributor',
    ): MemberAccount {
        $group = MemberGroup::query()->firstOrCreate(
            ['member_group_name' => 'Mitra'],
            [
                'member_group_description' => 'Grup akses mitra',
                'member_group_is_active' => 1,
            ]
        );
        $member = Member::query()->create([
            'member_id' => $memberId,
            'member_code' => $code,
            'member_member_level_id' => 1,
            'member_parent_member_id' => 0,
            'member_name' => "Distributor {$memberId}",
            'member_email' => "distributor{$memberId}@example.test",
            'member_mobilephone' => '081234567890',
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

    private function approveStockScreening(int $trxId): void
    {
        DB::table('trx')
            ->where('trx_id', $trxId)
            ->update([
                'trx_status' => 'waiting_payment',
                'trx_status_datetime' => now(),
            ]);
    }

    /** @return array{Product, Product} */
    private function createPurchaseReferences(): array
    {
        DB::table('ref_bank')->insert([
            'bank_id' => 1,
            'bank_code' => 'DNY',
            'bank_name' => 'Bank DNY',
            'bank_is_active' => 1,
        ]);
        DB::table('bank_company')->insert([
            [
                'bank_company_id' => 1,
                'bank_company_type' => 'company',
                'bank_company_bank_id' => 1,
                'bank_company_bank_acc_name' => 'PT DNY',
                'bank_company_bank_acc_number' => '1234567890',
                'bank_company_bank_is_active' => 1,
            ],
            [
                'bank_company_id' => 2,
                'bank_company_type' => 'spread_payment',
                'bank_company_bank_id' => 1,
                'bank_company_bank_acc_name' => 'DNY Spread Payment',
                'bank_company_bank_acc_number' => '1234567891',
                'bank_company_bank_is_active' => 1,
            ],
        ]);
        DB::table('warehouse')->insert([
            'warehouse_id' => 1,
            'warehouse_name' => 'Gudang Pusat',
            'warehouse_legal_name' => 'PT DNY',
            'warehouse_address' => 'Jakarta',
            'warehouse_phone' => '0211234567',
            'warehouse_province_id' => 31,
            'warehouse_city_id' => 3171,
            'warehouse_district_id' => 317101,
            'warehouse_subdistrict_id' => 31710101,
            'warehouse_latitude' => -6.225,
            'warehouse_longitude' => 106.8,
            'warehouse_is_active' => 1,
        ]);
        $category = ProductCategory::query()->create([
            'product_category_name' => 'Skincare',
            'product_category_description' => 'Produk perawatan kulit',
            'product_category_is_active' => 1,
        ]);
        $firstProduct = Product::query()->create([
            'product_product_category_id' => $category->getKey(),
            'product_code' => 'SRM-001',
            'product_name' => 'Serum DNY',
            'product_bpom_number' => 'NA18250100001',
            'product_image' => 'https://cdn.example.test/products/serum.webp',
            'product_customer_price' => 150000,
            'product_weight' => 100,
            'product_unit' => 'pcs',
            'product_is_publish' => 1,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
        ]);
        $secondProduct = Product::query()->create([
            'product_product_category_id' => $category->getKey(),
            'product_code' => 'CRM-001',
            'product_name' => 'Cream DNY',
            'product_bpom_number' => 'NA18250100002',
            'product_customer_price' => 75000,
            'product_weight' => 75,
            'product_unit' => 'pcs',
            'product_is_publish' => 1,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
        ]);

        foreach ([[$firstProduct, 100000], [$secondProduct, 50000]] as [$product, $price]) {
            ProductPrice::query()->create([
                'product_price_product_id' => $product->getKey(),
                'product_price_member_level_id' => 1,
                'product_price_value' => $price,
            ]);
            DB::table('warehouse_stock')->insert([
                'warehouse_stock_warehouse_id' => 1,
                'warehouse_stock_product_id' => $product->getKey(),
                'warehouse_stock_balance' => 1,
            ]);
        }

        return [$firstProduct, $secondProduct];
    }
}
