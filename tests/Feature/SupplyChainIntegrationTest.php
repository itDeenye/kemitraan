<?php

namespace Tests\Feature;

use App\Contracts\Integrations\SupplyChainGateway;
use App\Exceptions\SupplyChainRequestException;
use App\Integrations\SupplyChain\HttpSupplyChainGateway;
use App\Jobs\SyncSupplyChainMember;
use App\Jobs\SyncSupplyChainSale;
use App\Models\Member;
use App\Models\MemberAddress;
use App\Models\MemberSupplyChainSync;
use App\Models\Product;
use App\Models\ShippingDetail;
use App\Models\ShippingPickup;
use App\Models\Trx;
use App\Models\TrxDetail;
use App\Models\TrxSupplyChainSync;
use App\Models\Warehouse;
use App\Services\Integration\SupplyChainSyncService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupplyChainIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_member_sync_command_uses_missing_member_code_and_job_retries_three_times(): void
    {
        config()->set('services.supply_chain.enabled', true);
        [$member] = $this->createTransactionData();
        MemberSupplyChainSync::query()->create([
            'member_supply_chain_sync_member_id' => $member->getKey(),
            'member_supply_chain_sync_status' => 'failed',
            'member_supply_chain_sync_error' => 'Percobaan sebelumnya gagal.',
        ]);

        Queue::fake();
        $this->artisan('supply-chain:sync', ['--members' => true, '--scheduled' => true])
            ->expectsOutput('Job member: 0; job penjualan: 0.')
            ->assertSuccessful();
        Queue::assertNothingPushed();

        Queue::fake();
        $this->artisan('supply-chain:sync', ['--members' => true])
            ->expectsOutput('Job member: 1; job penjualan: 0.')
            ->assertSuccessful();
        Queue::assertPushed(
            SyncSupplyChainMember::class,
            fn (SyncSupplyChainMember $job): bool => $job->memberId === $member->getKey()
                && $job->tries === 3
                && $job->backoff === [10, 30],
        );

        $member->forceFill(['member_supply_chain_code' => 'CUST-EXISTING'])->save();
        Queue::fake();
        $this->artisan('supply-chain:sync', ['--members' => true])
            ->expectsOutput('Job member: 0; job penjualan: 0.')
            ->assertSuccessful();
        Queue::assertNothingPushed();
    }

    public function test_sale_sync_command_uses_missing_transaction_code_and_job_retries_three_times(): void
    {
        config()->set('services.supply_chain.enabled', true);
        [, $trx] = $this->createTransactionData();
        TrxSupplyChainSync::query()->create([
            'trx_supply_chain_sync_trx_id' => $trx->getKey(),
            'trx_supply_chain_sync_status' => 'failed',
            'trx_supply_chain_sync_error' => 'Percobaan sebelumnya gagal.',
        ]);

        Queue::fake();
        $this->artisan('supply-chain:sync', ['--sales' => true, '--scheduled' => true])
            ->expectsOutput('Job member: 0; job penjualan: 0.')
            ->assertSuccessful();
        Queue::assertNothingPushed();

        Queue::fake();
        $this->artisan('supply-chain:sync', ['--sales' => true])
            ->expectsOutput('Job member: 0; job penjualan: 1.')
            ->assertSuccessful();
        Queue::assertPushed(
            SyncSupplyChainSale::class,
            fn (SyncSupplyChainSale $job): bool => $job->trxId === $trx->getKey()
                && $job->tries === 3
                && $job->backoff === [10, 30],
        );

        $trx->forceFill(['trx_supply_chain_code' => 'SALE-EXISTING'])->save();
        Queue::fake();
        $this->artisan('supply-chain:sync', ['--sales' => true])
            ->expectsOutput('Job member: 0; job penjualan: 0.')
            ->assertSuccessful();
        Queue::assertNothingPushed();
    }

    public function test_member_prefix_is_only_used_in_outbound_payload(): void
    {
        config()->set('services.supply_chain.enabled', true);
        config()->set('services.supply_chain.member_prefix', 'LC01');
        config()->set('services.supply_chain.base_url', 'https://supply.test');
        [$member] = $this->createTransactionData();
        $gateway = new FakeSupplyChainGateway;
        $service = new SupplyChainSyncService($gateway);

        $sync = $service->syncMember($member);

        $this->assertSame('0001/0000/0000', $member->refresh()->member_code);
        $this->assertSame('LC01/0001/0000/0000', $gateway->customerPayloads[0]['no_kemitraan']);
        $this->assertSame('CUST-001', $sync->member_supply_chain_sync_customer_no);
        $this->assertSame('CUST-001', $member->refresh()->member_supply_chain_code);
        $this->assertSame(
            'https://supply.test/api/v1/penjualan-supply-chain/tambah-customer',
            $sync->member_supply_chain_sync_url,
        );
        $this->assertSame(
            'LC01/0001/0000/0000',
            $sync->member_supply_chain_sync_param['no_kemitraan'],
        );
        $this->assertSame('CUST-001', $sync->member_supply_chain_sync_response['cust_no']);
        $this->assertFalse(Schema::hasColumn('member', 'member_supply_chain_customer_no'));

        $member->forceFill(['member_supply_chain_code' => null])->save();
        $service->syncMember($member);

        $this->assertSame('CUST-001', $member->refresh()->member_supply_chain_code);
        $this->assertCount(1, $gateway->customerPayloads);
    }

    public function test_existing_supply_chain_customer_response_is_synchronized_as_success(): void
    {
        config()->set('services.supply_chain.enabled', true);
        config()->set('services.supply_chain.base_url', 'https://supply-duplicate.test');
        config()->set('services.supply_chain.username', 'duplicate-customer-test');
        config()->set('services.supply_chain.password', 'secret');
        [$member] = $this->createTransactionData();
        Http::fake([
            'https://supply-duplicate.test/api/v1/penjualan-supply-chain/login' => Http::response([
                'success' => true,
                'data' => ['api_token' => 'test-token'],
            ]),
            'https://supply-duplicate.test/api/v1/penjualan-supply-chain/tambah-customer' => Http::response([
                'success' => false,
                'message' => "Customer dengan No KTP '3171012345670001' sudah terdaftar pada sistem (data kembar).",
                'data' => [
                    'nama' => 'Budi Santoso',
                    'Cust_no' => '10030900 ',
                    'no_kemitraan' => 'KM-2026-002',
                ],
            ], 400),
        ]);

        $sync = (new SupplyChainSyncService(new HttpSupplyChainGateway))->syncMember($member);

        $this->assertSame('synced', $sync->member_supply_chain_sync_status);
        $this->assertSame('10030900', $sync->member_supply_chain_sync_customer_no);
        $this->assertSame('10030900', $sync->member_supply_chain_sync_response['cust_no']);
        $this->assertSame('10030900', $member->refresh()->member_supply_chain_code);
    }

    public function test_company_sale_uses_response_number_without_adding_duplicate_column_and_prefills_shipping(): void
    {
        config()->set('services.supply_chain.enabled', true);
        config()->set('services.supply_chain.member_prefix', 'DEV01');
        config()->set('services.supply_chain.base_url', 'https://supply.test');
        [$member, $trx, $shipping] = $this->createTransactionData();
        $gateway = new FakeSupplyChainGateway;
        $service = new SupplyChainSyncService($gateway);

        $service->syncSale($trx);

        $shipping->refresh();
        $memberSync = MemberSupplyChainSync::query()->where(
            'member_supply_chain_sync_member_id',
            $member->getKey(),
        )->firstOrFail();
        $trxSync = TrxSupplyChainSync::query()->where(
            'trx_supply_chain_sync_trx_id',
            $trx->getKey(),
        )->firstOrFail();
        $this->assertSame('CUST-001', $memberSync->member_supply_chain_sync_customer_no);
        $this->assertSame('synced', $trxSync->trx_supply_chain_sync_status);
        $this->assertSame('approved', $trxSync->trx_supply_chain_sync_stage);
        $this->assertSame('EXT-SALE-001', data_get($trxSync->trx_supply_chain_sync_response, 'save.jual_no'));
        $this->assertSame('EXT-SALE-001', $trx->refresh()->trx_supply_chain_code);
        $this->assertSame(
            'https://supply.test/api/v1/penjualan-supply-chain/simpan',
            $trxSync->trx_supply_chain_sync_url['save'],
        );
        $this->assertSame(
            'DEV01/TRX/CMP/DST/000001',
            $trxSync->trx_supply_chain_sync_param['save']['jual_ket'],
        );
        $this->assertSame(
            ['jual_no' => 'EXT-SALE-001'],
            $trxSync->trx_supply_chain_sync_param['posting'],
        );
        $this->assertSame(
            'https://supply.test/api/v1/penjualan-supply-chain/approve',
            $trxSync->trx_supply_chain_sync_url['approve'],
        );
        $this->assertFalse(Schema::hasColumn('trx', 'trx_supply_chain_sale_no'));
        $this->assertFalse(Schema::hasColumn('trx', 'trx_supply_chain_sync_status'));
        $this->assertSame(['EXT-SALE-001'], $gateway->postedSaleNumbers);
        $this->assertSame(['EXT-SALE-001'], $gateway->approvedSaleNumbers);
        $this->assertSame('SJ-2026-001', $shipping->shipping_pickup_delivery_note_number);
        $this->assertSame('DNY0003', $gateway->salePayloads[0]['produk'][0]['kode_produk']);
        $detail = ShippingDetail::query()->firstOrFail();
        $this->assertSame('BATCH-001', $detail->shipping_detail_batch_number);
        $this->assertSame(2, $detail->shipping_detail_qty);
        $this->assertSame('2028-12-31', $detail->shipping_detail_expire_date->toDateString());
        $this->assertSame('DEV01/TRX/CMP/DST/000001', $gateway->salePayloads[0]['jual_ket']);
        $this->assertSame('TRX/CMP/DST/000001', $trx->refresh()->trx_code);

        $trx->forceFill(['trx_supply_chain_code' => null])->save();
        $service->syncSale($trx);

        $this->assertSame('EXT-SALE-001', $trx->refresh()->trx_supply_chain_code);
        $this->assertCount(1, $gateway->salePayloads);
    }

    public function test_company_sale_persists_external_error_response_for_the_failed_stage(): void
    {
        config()->set('services.supply_chain.enabled', true);
        config()->set('services.supply_chain.base_url', 'https://supply.test');
        [, $trx] = $this->createTransactionData();
        $gateway = new FakeSupplyChainGateway;
        $gateway->saveException = new SupplyChainRequestException(
            'Gagal menyimpan data',
            400,
            [
                'success' => false,
                'message' => 'Gagal menyimpan data',
                'dev_message' => 'Nilai kode produk terlalu panjang.',
            ],
        );

        try {
            (new SupplyChainSyncService($gateway))->syncSale($trx);
            $this->fail('Sinkronisasi seharusnya melempar exception dari Supply Chain.');
        } catch (SupplyChainRequestException $exception) {
            $this->assertSame('Gagal menyimpan data', $exception->getMessage());
        }

        $sync = TrxSupplyChainSync::query()->where(
            'trx_supply_chain_sync_trx_id',
            $trx->getKey(),
        )->firstOrFail();
        $this->assertSame('failed', $sync->trx_supply_chain_sync_status);
        $this->assertSame('Gagal menyimpan data', $sync->trx_supply_chain_sync_error);
        $this->assertSame(400, data_get($sync->trx_supply_chain_sync_response, 'save.http_status'));
        $this->assertFalse(data_get($sync->trx_supply_chain_sync_response, 'save.body.success'));
        $this->assertSame(
            'Nilai kode produk terlalu panjang.',
            data_get($sync->trx_supply_chain_sync_response, 'save.body.dev_message'),
        );
    }

    public function test_live_sale_stops_after_create_and_external_callback_fills_shipping_data(): void
    {
        config()->set('services.supply_chain.enabled', true);
        config()->set('services.supply_chain.approval_callback_enabled', true);
        config()->set('services.supply_chain.callback_token', 'supply-callback-secret');
        config()->set('services.supply_chain.member_prefix', 'DEV01');
        config()->set('services.supply_chain.base_url', 'https://supply.test');
        [, $trx, $shipping] = $this->createTransactionData();
        $gateway = new FakeSupplyChainGateway;

        (new SupplyChainSyncService($gateway))->syncSale($trx);

        $sync = TrxSupplyChainSync::query()
            ->where('trx_supply_chain_sync_trx_id', $trx->getKey())
            ->firstOrFail();
        $this->assertSame('EXT-SALE-001', $trx->refresh()->trx_supply_chain_code);
        $this->assertSame('awaiting_callback', $sync->trx_supply_chain_sync_status);
        $this->assertSame('saved', $sync->trx_supply_chain_sync_stage);
        $this->assertCount(1, $gateway->salePayloads);
        $this->assertSame([], $gateway->postedSaleNumbers);
        $this->assertSame([], $gateway->approvedSaleNumbers);
        $this->assertNull($shipping->refresh()->shipping_pickup_delivery_note_number);
        $this->assertDatabaseCount('shipping_detail', 0);

        $payload = [
            'invoice_number' => 'EXT-SALE-001',
            'delivery_note_number' => 'SJ-CALLBACK-001',
            'products' => [[
                'product_code' => 'DNY0003',
                'quantity' => 2,
                'batch_number' => 'BATCH-CALLBACK-001',
                'expiry_date' => '2028-12-31',
            ]],
        ];
        $this->withHeader('X-Supply-Chain-Callback-Token', 'supply-callback-secret')
            ->postJson('/api/v1/callbacks/supply-chain/sales/approved', $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.transaction_code', 'TRX/CMP/DST/000001')
            ->assertJsonPath('data.invoice_number', 'EXT-SALE-001')
            ->assertJsonPath('data.delivery_note_number', 'SJ-CALLBACK-001')
            ->assertJsonPath('data.batch_count', 1)
            ->assertJsonPath('data.batch_quantity', 2)
            ->assertJsonPath('data.sync_status', 'synced');

        $sync->refresh();
        $this->assertSame('synced', $sync->trx_supply_chain_sync_status);
        $this->assertSame('approved', $sync->trx_supply_chain_sync_stage);
        $this->assertSame(
            'http://localhost:8000/api/v1/callbacks/supply-chain/sales/approved',
            $sync->trx_supply_chain_sync_url['callback'],
        );
        $this->assertSame('EXT-SALE-001', $sync->trx_supply_chain_sync_param['callback']['invoice_number']);
        $this->assertSame(
            'SJ-CALLBACK-001',
            $sync->trx_supply_chain_sync_response['callback']['delivery_note_number'],
        );
        $this->assertSame('SJ-CALLBACK-001', $shipping->refresh()->shipping_pickup_delivery_note_number);
        $this->assertDatabaseHas('shipping_detail', [
            'shipping_detail_shipping_type' => 'pickup',
            'shipping_detail_shipping_id' => $shipping->getKey(),
            'shipping_detail_batch_number' => 'BATCH-CALLBACK-001',
            'shipping_detail_qty' => 2,
        ]);
        $this->assertSame(
            '2028-12-31',
            ShippingDetail::query()->firstOrFail()->shipping_detail_expire_date->toDateString(),
        );
        $this->assertDatabaseHas('integration_callback_log', [
            'integration_callback_log_provider' => 'supply_chain',
            'integration_callback_log_type' => 'sale',
            'integration_callback_log_event' => 'approved',
            'integration_callback_log_status' => 'completed',
            'integration_callback_log_http_code' => 200,
        ]);

        $this->withToken('supply-callback-secret')
            ->postJson('/api/v1/callbacks/supply-chain/sales/approved', [
                'invoice_number' => 'EXT-SALE-001',
                'delivery_note_number' => 'SJ-CALLBACK-001',
                'products' => [[
                    'product_code' => 'DNY0003',
                    'quantity' => 2,
                    'batch_number' => 'BATCH-CALLBACK-001',
                    'expiry_date' => '2028-12-31',
                ]],
            ])
            ->assertOk();
        $this->assertDatabaseCount('shipping_detail', 1);
    }

    public function test_supply_chain_approval_callback_requires_valid_token(): void
    {
        config()->set('services.supply_chain.callback_token', 'supply-callback-secret');

        $this->withHeader('X-Supply-Chain-Callback-Token', 'wrong-secret')
            ->postJson('/api/v1/callbacks/supply-chain/sales/approved', [
                'invoice_number' => 'EXT-SALE-001',
                'delivery_note_number' => 'SJ-CALLBACK-001',
                'products' => [[
                    'product_code' => 'DNY0003',
                    'quantity' => 2,
                    'batch_number' => 'BATCH-CALLBACK-001',
                    'expiry_date' => '2028-12-31',
                ]],
            ])
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'process_error');
    }

    public function test_supply_chain_approval_callback_rejects_incomplete_batch_quantity(): void
    {
        config()->set('services.supply_chain.enabled', true);
        config()->set('services.supply_chain.approval_callback_enabled', true);
        config()->set('services.supply_chain.callback_token', 'supply-callback-secret');
        config()->set('services.supply_chain.base_url', 'https://supply.test');
        [, $trx, $shipping] = $this->createTransactionData();

        (new SupplyChainSyncService(new FakeSupplyChainGateway))->syncSale($trx);

        $this->withToken('supply-callback-secret')
            ->postJson('/api/v1/callbacks/supply-chain/sales/approved', [
                'invoice_number' => 'EXT-SALE-001',
                'delivery_note_number' => 'SJ-CALLBACK-INVALID',
                'products' => [[
                    'product_code' => 'DNY0003',
                    'quantity' => 1,
                    'batch_number' => 'BATCH-CALLBACK-INVALID',
                    'expiry_date' => '2028-12-31',
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        $this->assertNull($shipping->refresh()->shipping_pickup_delivery_note_number);
        $this->assertDatabaseCount('shipping_detail', 0);
        $this->assertDatabaseHas('trx_supply_chain_syncs', [
            'trx_supply_chain_sync_trx_id' => $trx->getKey(),
            'trx_supply_chain_sync_status' => 'awaiting_callback',
            'trx_supply_chain_sync_stage' => 'saved',
        ]);
        $this->assertDatabaseHas('integration_callback_log', [
            'integration_callback_log_provider' => 'supply_chain',
            'integration_callback_log_status' => 'failed',
            'integration_callback_log_http_code' => 422,
        ]);
    }

    /** @return array{Member, Trx, ShippingPickup} */
    private function createTransactionData(): array
    {
        DB::table('ref_province')->insert([
            'province_id' => '31',
            'province_name' => 'DKI Jakarta',
            'province_is_active' => 1,
        ]);
        DB::table('ref_city')->insert([
            'city_id' => '3171',
            'city_province_id' => '31',
            'city_name' => 'Jakarta Selatan',
            'city_type' => 'Kota',
            'city_is_active' => 1,
        ]);
        DB::table('ref_district')->insert([
            'district_id' => '317101',
            'district_city_id' => '3171',
            'district_name' => 'Kebayoran Baru',
        ]);

        $member = Member::query()->create([
            'member_code' => '0001/0000/0000',
            'member_member_level_id' => 1,
            'member_parent_member_id' => 0,
            'member_name' => 'Budi Santoso',
            'member_email' => 'budi@example.test',
            'member_mobilephone' => '081234567890',
            'member_birth_date' => '1990-05-15',
            'member_identity_no' => '3171012345670001',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
        MemberAddress::query()->create([
            'member_address_member_id' => $member->getKey(),
            'member_address_label' => 'Domisili',
            'member_address_recipient' => $member->member_name,
            'member_address_phone' => $member->member_mobilephone,
            'member_address_full' => 'Jl. Sudirman No. 45',
            'member_address_district_id' => 317101,
            'member_address_city_id' => 3171,
            'member_address_province_id' => 31,
            'member_address_country_id' => 1,
            'member_address_is_default' => 1,
        ]);
        $warehouse = Warehouse::query()->create([
            'warehouse_name' => 'Warehouse Utama DNY',
            'warehouse_phone' => '08111111111',
            'warehouse_address' => 'Jl. Gudang DNY',
            'warehouse_is_active' => 1,
        ]);
        $product = Product::query()->create([
            'product_product_category_id' => 0,
            'product_code' => 'BRG001',
            'product_name' => 'Produk Sample',
            'product_customer_price' => 200000,
            'product_is_publish' => 1,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
            'product_input_datetime' => now(),
        ]);
        $trx = Trx::query()->create([
            'trx_code' => 'TRX/CMP/DST/000001',
            'trx_parent_trx_id' => 0,
            'trx_is_preorder' => 0,
            'trx_seller_type' => 'warehouse',
            'trx_seller_id' => $warehouse->getKey(),
            'trx_buyer_type' => 'distributor',
            'trx_buyer_id' => $member->getKey(),
            'trx_type' => 'stock',
            'trx_reference_id' => 0,
            'trx_total_price' => 400000,
            'trx_discount' => 0,
            'trx_discount_value' => 0,
            'trx_grand_total_price' => 400000,
            'trx_shipping_cost' => 25000,
            'trx_payment_charge' => 0,
            'trx_grand_total_nett_price' => 425000,
            'trx_bill_remaining' => 0,
            'trx_bill_augment' => 0,
            'trx_bill_amount' => 425000,
            'trx_payment_method' => 'transfer',
            'trx_shipping_method' => 'pickup',
            'trx_status' => 'processing',
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);
        TrxDetail::query()->create([
            'trx_detail_trx_id' => $trx->getKey(),
            'trx_detail_product_id' => $product->getKey(),
            'trx_detail_product_plan_id' => 0,
            'trx_detail_product_type' => 'stock',
            'trx_detail_product_code' => $product->product_code,
            'trx_detail_product_name' => $product->product_name,
            'trx_detail_product_price' => 200000,
            'trx_detail_product_weight' => 100,
            'trx_detail_discount_percent' => 0,
            'trx_detail_discount_value' => 0,
            'trx_detail_nett_price' => 200000,
            'trx_detail_qty' => 2,
        ]);
        $shipping = ShippingPickup::query()->create([
            'shipping_pickup_ref_type' => 'trx',
            'shipping_pickup_ref_id' => $trx->getKey(),
            'shipping_pickup_seller_address' => $warehouse->warehouse_address,
            'shipping_pickup_seller_name' => $warehouse->warehouse_name,
            'shipping_pickup_seller_mobilephone' => $warehouse->warehouse_phone,
            'shipping_pickup_pin' => '12345',
        ]);

        return [$member, $trx, $shipping];
    }
}

class FakeSupplyChainGateway implements SupplyChainGateway
{
    public ?SupplyChainRequestException $saveException = null;

    /** @var list<array<string, mixed>> */
    public array $customerPayloads = [];

    /** @var list<array<string, mixed>> */
    public array $salePayloads = [];

    /** @var list<string> */
    public array $postedSaleNumbers = [];

    /** @var list<string> */
    public array $approvedSaleNumbers = [];

    public function createCustomer(array $payload): array
    {
        $this->customerPayloads[] = $payload;

        return ['cust_no' => 'CUST-001'];
    }

    public function saveSale(array $payload): array
    {
        $this->salePayloads[] = $payload;

        if ($this->saveException) {
            throw $this->saveException;
        }

        return ['jual_no' => 'EXT-SALE-001'];
    }

    public function postSale(string $saleNumber): array
    {
        $this->postedSaleNumbers[] = $saleNumber;

        return ['jual_no' => $saleNumber, 'status' => 'POSTED'];
    }

    public function approveSale(string $saleNumber): array
    {
        $this->approvedSaleNumbers[] = $saleNumber;

        return [
            'jual_no' => $saleNumber,
            'surat_jalan' => [
                'header' => ['surat_jalan' => ' SJ-2026-001 '],
                'detail_label' => [
                    'label_biru' => [[
                        'brg_code' => 'DNY0003 ',
                        'jual_qty' => 2,
                        'no_batch' => ' BATCH-001 ',
                        'expired_date' => '2028-12-31',
                    ]],
                ],
            ],
        ];
    }
}
