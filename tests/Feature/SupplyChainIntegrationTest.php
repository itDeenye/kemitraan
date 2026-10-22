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
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Models\Trx;
use App\Models\TrxDetail;
use App\Models\TrxSupplyChainSync;
use App\Models\Warehouse;
use App\Services\Integration\SupplyChainSyncService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SupplyChainIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_retry_after_admin_edit_uses_latest_primary_phone_and_ktp_not_the_old_recipient_phone(): void
    {
        [$member, $trx] = $this->createTransactionData();
        $this->prepareManualSync();
        DB::table('ref_country')->insertOrIgnore([
            'country_id' => 1, 'country_iso_code' => 'ID', 'country_phone_code' => '+62',
            'country_name' => 'Indonesia', 'country_flag' => '', 'country_is_active' => '1',
        ]);
        DB::table('ref_subdistrict')->insert([
            'subdistrict_id' => 88001, 'subdistrict_district_id' => 317101,
            'subdistrict_name' => 'Kelurahan Test', 'subdistrict_zip_code' => 12160,
        ]);
        Http::fake([
            'manual-supply.test/*/login' => Http::response(['success' => true, 'data' => ['api_token' => 'test-token']]),
            'manual-supply.test/*/tambah-customer' => Http::sequence()
                ->push(['success' => false, 'message' => 'Nomor telepon sudah terdaftar.'], 422)
                ->push(['success' => true, 'data' => ['cust_no' => '90000046']]),
        ]);
        $url = "/api/v1/admin/partnership/members/{$member->getKey()}/supply-chain/sync";
        $this->postJson($url)->assertStatus(502);
        $this->assertNull($member->fresh()->member_supply_chain_code);
        $address = $member->defaultAddress;
        $this->putJson("/api/v1/admin/partnership/members/{$member->getKey()}", [
            'name' => 'Budi Santoso', 'mobile_phone' => '081799998888', 'gender' => 'Laki-laki', 'status' => 1,
            'identity_type' => 'KTP', 'identity_no' => '3171012345670002', 'bank_accounts' => [],
            'addresses' => [[
                'id' => $address->getKey(), 'recipient' => 'Penerima Lama', 'phone' => '081234567890',
                'full_address' => 'Jl. Sudirman No. 45', 'province_id' => '31', 'city_id' => '3171',
                'district_id' => '317101', 'subdistrict_id' => 88001, 'country_id' => 1, 'is_default' => true,
            ]],
        ])->assertJsonMissingValidationErrors()->assertOk();
        $this->assertSame('+6281799998888', $member->fresh()->member_mobilephone);
        $this->assertSame('3171012345670002', $member->fresh()->member_identity_no);

        $this->postJson($url)->assertOk()->assertJsonPath('data.customer_number', '90000046');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/tambah-customer')
            && $request['cust_phone1'] === '+6281799998888' && $request['NoKTP'] === '3171012345670002');
        Http::assertSentCount(3);
        $sync = MemberSupplyChainSync::query()->where('member_supply_chain_sync_member_id', $member->getKey())->firstOrFail();
        $this->assertSame('+6281799998888', $sync->member_supply_chain_sync_param['cust_phone1']);
        $this->assertSame('3171012345670002', $sync->member_supply_chain_sync_param['NoKTP']);

        $gateway = new FakeSupplyChainGateway;
        (new SupplyChainSyncService($gateway))->syncSale($trx);
        $this->assertCount(0, $gateway->customerPayloads);
        $this->assertSame('+6281234567890', $gateway->salePayloads[0]['telp_penerima']);
    }

    public function test_admin_manual_member_sync_returns_the_internal_message_and_hides_the_action(): void
    {
        [$member] = $this->createTransactionData();
        $this->prepareManualSync();
        Http::fake([
            'manual-supply.test/*/login' => Http::response(['success' => true, 'data' => ['api_token' => 'test-token']]),
            'manual-supply.test/*/tambah-customer' => Http::response([
                'success' => true, 'message' => 'Customer berhasil disimpan di internal.', 'data' => ['cust_no' => '90000046'],
            ]),
        ]);
        $this->getJson('/api/v1/admin/partnership/members')->assertOk()
            ->assertJsonPath('data.results.0.can_sync_supply_chain', true);
        $this->getJson("/api/v1/admin/partnership/members/{$member->getKey()}")->assertOk()
            ->assertJsonPath('data.can_sync_supply_chain', true);

        $url = "/api/v1/admin/partnership/members/{$member->getKey()}/supply-chain/sync";
        $this->postJson($url)->assertOk()
            ->assertJsonPath('message', 'Customer berhasil disimpan di internal.')
            ->assertJsonPath('data.customer_number', '90000046')
            ->assertJsonPath('data.can_sync_supply_chain', false);

        $this->getJson('/api/v1/admin/partnership/members')->assertOk()
            ->assertJsonPath('data.results.0.customer_number', '90000046')
            ->assertJsonPath('data.results.0.can_sync_supply_chain', false);
        $this->postJson($url)->assertStatus(409);
        Http::assertSentCount(2);
    }

    public function test_admin_manual_member_sync_returns_the_original_internal_error_and_allows_retry(): void
    {
        [$member] = $this->createTransactionData();
        $this->prepareManualSync();
        Http::fake([
            'manual-supply.test/*/login' => Http::response(['success' => true, 'data' => ['api_token' => 'test-token']]),
            'manual-supply.test/*/tambah-customer' => Http::sequence()
                ->push(['success' => false, 'message' => 'Customer ditolak: nomor identitas belum valid.'], 422)
                ->push(['success' => true, 'message' => 'Customer berhasil disinkronkan.', 'data' => ['cust_no' => '90000046']]),
        ]);
        $url = "/api/v1/admin/partnership/members/{$member->getKey()}/supply-chain/sync";
        $this->postJson($url)->assertStatus(502)
            ->assertJsonPath('message', 'Error pada Aplikasi Internal: Customer ditolak: nomor identitas belum valid.');
        $this->assertNull($member->fresh()->member_supply_chain_code);
        $this->getJson('/api/v1/admin/partnership/members')->assertOk()
            ->assertJsonPath('data.results.0.can_sync_supply_chain', true);

        $this->postJson($url)->assertOk()->assertJsonPath('data.customer_number', '90000046');
    }

    #[DataProvider('manualSaleTypes')]
    public function test_admin_manual_sale_sync_only_creates_the_internal_sale_and_hides_the_action(bool $preorder): void
    {
        [$member, $trx] = $this->createTransactionData();
        $trx->update(['trx_is_preorder' => $preorder]);
        $member->update(['member_supply_chain_code' => '90000046']);
        $this->prepareManualSync();
        Http::fake([
            'manual-supply.test/*/login' => Http::response(['success' => true, 'data' => ['api_token' => 'test-token']]),
            'manual-supply.test/*/simpan' => Http::response([
                'success' => true, 'message' => 'Transaksi berhasil dibuat di internal.', 'data' => ['jual_no' => 'INV-001'],
            ]),
        ]);
        $this->getJson('/api/v1/admin/transactions/orders')->assertOk()
            ->assertJsonPath('data.results.0.can_sync_supply_chain', true);
        $this->getJson('/api/v1/admin/inventory/shipments')->assertOk()
            ->assertJsonPath('data.results.0.can_sync_supply_chain', true);

        $url = "/api/v1/admin/transactions/orders/{$trx->getKey()}/supply-chain/sync";
        $this->postJson($url)->assertOk()
            ->assertJsonPath('message', 'Transaksi berhasil dibuat di internal.')
            ->assertJsonPath('data.invoice_number', 'INV-001')
            ->assertJsonPath('data.supply_chain_status', 'awaiting_callback')
            ->assertJsonPath('data.can_sync_supply_chain', false);

        $this->getJson('/api/v1/admin/transactions/orders')->assertOk()
            ->assertJsonPath('data.results.0.can_sync_supply_chain', false);
        $this->getJson("/api/v1/admin/transactions/orders/{$trx->getKey()}")->assertOk()
            ->assertJsonPath('data.can_sync_supply_chain', false);
        $this->getJson('/api/v1/admin/inventory/shipments')->assertOk()
            ->assertJsonPath('data.results.0.invoice_number', 'INV-001')
            ->assertJsonPath('data.results.0.can_sync_supply_chain', false);
        $this->postJson($url)->assertStatus(409);
        Http::assertSentCount(2);
    }

    public static function manualSaleTypes(): array
    {
        return ['regular' => [false], 'preorder' => [true]];
    }

    public function test_admin_manual_sale_sync_preserves_the_internal_rejection_without_an_invoice(): void
    {
        [$member, $trx] = $this->createTransactionData();
        $member->update(['member_supply_chain_code' => '90000046']);
        $this->prepareManualSync();
        Http::fake([
            'manual-supply.test/*/login' => Http::response(['success' => true, 'data' => ['api_token' => 'test-token']]),
            'manual-supply.test/*/simpan' => Http::response(['success' => false, 'message' => 'Kode produk tidak ditemukan di internal.'], 400),
        ]);

        $this->postJson("/api/v1/admin/transactions/orders/{$trx->getKey()}/supply-chain/sync")
            ->assertStatus(502)->assertJsonPath('message', 'Error pada Aplikasi Internal: Kode produk tidak ditemukan di internal.');

        $this->assertNull($trx->fresh()->trx_supply_chain_code);
        $this->getJson('/api/v1/admin/inventory/shipments')->assertOk()
            ->assertJsonPath('data.results.0.can_sync_supply_chain', true);
        $this->assertDatabaseHas('trx_supply_chain_syncs', [
            'trx_supply_chain_sync_trx_id' => $trx->getKey(), 'trx_supply_chain_sync_status' => 'failed',
        ]);
    }

    #[DataProvider('ineligibleManualSales')]
    public function test_admin_manual_sale_sync_rejects_ineligible_orders_without_calling_internal(array $attributes): void
    {
        [, $trx] = $this->createTransactionData();
        $trx->update($attributes);
        $this->prepareManualSync();
        Http::fake();

        $this->getJson('/api/v1/admin/transactions/orders')->assertOk()
            ->assertJsonPath('data.results.0.can_sync_supply_chain', false);
        $this->postJson("/api/v1/admin/transactions/orders/{$trx->getKey()}/supply-chain/sync")
            ->assertUnprocessable();

        Http::assertNothingSent();
    }

    public static function ineligibleManualSales(): array
    {
        return [
            'unpaid' => [['trx_status' => 'waiting_payment']],
            'payment under review' => [['trx_status' => 'waiting_payment_approval']],
            'cancelled' => [['trx_status' => 'cancelled']],
            'member seller' => [['trx_seller_type' => 'distributor']],
            'retail' => [['trx_type' => 'retail']],
        ];
    }

    public function test_manual_sync_requires_admin_and_rejects_inactive_members_and_disabled_integration(): void
    {
        [$member, $trx] = $this->createTransactionData();
        Http::preventStrayRequests();
        $memberUrl = "/api/v1/admin/partnership/members/{$member->getKey()}/supply-chain/sync";
        $saleUrl = "/api/v1/admin/transactions/orders/{$trx->getKey()}/supply-chain/sync";
        $this->postJson($memberUrl)->assertUnauthorized();
        $this->postJson($saleUrl)->assertUnauthorized();

        $this->prepareManualSync();
        $member->update(['member_status' => 2]);
        $this->getJson('/api/v1/admin/partnership/members')->assertOk()
            ->assertJsonPath('data.results.0.can_sync_supply_chain', false);
        $this->postJson($memberUrl)->assertUnprocessable();
        config()->set('services.supply_chain.enabled', false);
        $this->postJson($saleUrl)->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_manual_member_sync_prevents_a_second_request_while_internal_is_processing(): void
    {
        [$member] = $this->createTransactionData();
        $this->prepareManualSync();
        $url = "/api/v1/admin/partnership/members/{$member->getKey()}/supply-chain/sync";
        $nested = null;
        $attempted = false;
        Http::fake([
            'manual-supply.test/*/login' => Http::response(['success' => true, 'data' => ['api_token' => 'test-token']]),
            'manual-supply.test/*/tambah-customer' => function () use ($url, &$nested, &$attempted) {
                if (! $attempted) {
                    $attempted = true;
                    $nested = $this->postJson($url);
                }

                return Http::response(['success' => true, 'data' => ['cust_no' => '90000046']]);
            },
        ]);

        $this->postJson($url)->assertOk();
        $this->assertNotNull($nested);
        $nested->assertStatus(409);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('member_supply_chain_syncs', 1);
    }

    public function test_background_sync_reuses_the_persisted_member_and_sale_numbers_without_recreating_them(): void
    {
        [$member, $trx] = $this->createTransactionData();
        $member->update(['member_supply_chain_code' => '90000046']);
        $trx->update(['trx_supply_chain_code' => 'INV-001']);
        $this->prepareManualSync();

        app(SupplyChainSyncService::class)->syncMember($member);
        app(SupplyChainSyncService::class)->syncSale($trx);

        Http::assertNothingSent();
        $this->assertSame('90000046', $member->fresh()->member_supply_chain_code);
        $this->assertSame('INV-001', $trx->fresh()->trx_supply_chain_code);
    }

    #[DataProvider('concurrentSyncResources')]
    public function test_manual_sync_rejects_an_entity_being_synced_by_another_process(string $resource): void
    {
        [$member, $trx] = $this->createTransactionData();
        $this->prepareManualSync();
        $record = $resource === 'member' ? $member : $trx;
        $route = $resource === 'member' ? 'partnership/members' : 'transactions/orders';
        $cacheDirectory = storage_path('framework/testing/supply-chain-sync-'.Str::uuid());
        File::ensureDirectoryExists($cacheDirectory);
        $cacheDirectory = realpath($cacheDirectory);
        $this->assertStringStartsWith(realpath(storage_path('framework/testing')).DIRECTORY_SEPARATOR, $cacheDirectory);
        $originalCache = config('cache');
        config()->set('cache.default', 'file');
        config()->set('cache.stores.file.path', $cacheDirectory);
        config()->set('cache.stores.file.lock_path', $cacheDirectory);
        Cache::purge('file');

        $script = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config()->set('cache.default', 'file');
config()->set('cache.stores.file.path', $argv[2]);
config()->set('cache.stores.file.lock_path', $argv[2]);
Illuminate\Support\Facades\Cache::purge('file');
Illuminate\Support\Facades\Http::preventStrayRequests();
trait PauseAfterSyncLock {
    public function refresh() {
        fwrite(STDOUT, "LOCKED\n");
        fflush(STDOUT);
        usleep(10000000);
        throw new RuntimeException('The parent must stop this worker before synchronization continues.');
    }
}
$record = $argv[3] === 'member'
    ? new class extends App\Models\Member { use PauseAfterSyncLock; }
    : new class extends App\Models\Trx { use PauseAfterSyncLock; };
$record->setAttribute($record->getKeyName(), (int) $argv[4]);
$service = $app->make(App\Services\Integration\SupplyChainSyncService::class);
$argv[3] === 'member' ? $service->syncMember($record) : $service->syncSale($record);
PHP;

        $process = new Process([PHP_BINARY, '-r', $script, base_path(), $cacheDirectory, $resource, (string) $record->getKey()]);
        $process->setTimeout(15);

        try {
            $process->start();
            while ($process->isRunning() && ! str_contains($process->getOutput(), 'LOCKED')) {
                $process->checkTimeout();
                usleep(10000);
            }
            $this->assertStringContainsString(
                'LOCKED',
                $process->getOutput(),
                $process->getErrorOutput(),
            );
            $this->postJson("/api/v1/admin/{$route}/{$record->getKey()}/supply-chain/sync")
                ->assertStatus(409)->assertJsonPath('message', 'Sinkronisasi sedang diproses. Silakan tunggu.');
            Http::assertNothingSent();
        } finally {
            $process->stop(0);
            Cache::purge('file');
            config()->set('cache', $originalCache);
            File::deleteDirectory($cacheDirectory);
        }
    }

    public static function concurrentSyncResources(): array
    {
        return ['member' => ['member'], 'sale' => ['sale']];
    }

    private function prepareManualSync(): void
    {
        config()->set('services.supply_chain.enabled', true);
        config()->set('services.supply_chain.base_url', 'https://manual-supply.test');
        config()->set('services.supply_chain.username', 'manual-test');
        config()->set('services.supply_chain.password', 'test-secret');
        Http::preventStrayRequests();
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Super Administrator', 'administrator_group_type' => 'superuser', 'administrator_group_is_active' => 1,
        ]);
        $this->actingAs(SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->getKey(), 'administrator_username' => 'sync.admin',
            'administrator_name' => 'Sync Admin', 'administrator_email' => 'sync.admin@example.test',
            'administrator_password' => Hash::make('Secret123'), 'administrator_is_active' => 1,
        ]), 'admin_api');
    }

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
        $this->assertSame('Jakarta Selatan', $gateway->customerPayloads[0]['kabupaten']);
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
                'success' => true,
                'message' => "Customer dengan No KTP '3171012345670001' sudah terdaftar pada sistem (data kembar).",
                'data' => [
                    'nama' => 'Budi Santoso',
                    'Cust_no' => '10030900 ',
                    'no_kemitraan' => 'KM-2026-002',
                ],
            ]),
        ]);

        $sync = (new SupplyChainSyncService(new HttpSupplyChainGateway))->syncMember($member);

        $this->assertSame('synced', $sync->member_supply_chain_sync_status);
        $this->assertSame('10030900', $sync->member_supply_chain_sync_customer_no);
        $this->assertSame('10030900', $sync->member_supply_chain_sync_response['cust_no']);
        $this->assertSame('10030900', $member->refresh()->member_supply_chain_code);
    }

    #[DataProvider('rejectedCustomerResponses')]
    public function test_rejected_customer_response_never_synchronizes_even_when_it_contains_a_number(int $status, bool $refreshToken): void
    {
        [$member] = $this->createTransactionData();
        $this->prepareManualSync();
        $message = "Customer dengan No KTP '3171012345670001' sudah terdaftar pada sistem (data kembar).";
        $responses = Http::sequence();
        if ($refreshToken) {
            $responses->push(['success' => false, 'message' => 'Token kedaluwarsa.'], 401);
        }
        $responses->push([
            'success' => false,
            'message' => $message,
            'data' => ['nama' => 'Budi Santoso', 'Cust_no' => '10030900 ', 'no_kemitraan' => 'KM-2026-002'],
        ], $status);
        Http::fake([
            'manual-supply.test/*/login' => Http::response(['success' => true, 'data' => ['api_token' => 'test-token']]),
            'manual-supply.test/*/tambah-customer' => $responses,
        ]);

        $this->postJson("/api/v1/admin/partnership/members/{$member->getKey()}/supply-chain/sync")
            ->assertStatus(502)->assertJsonPath('message', 'Error pada Aplikasi Internal: '.$message);

        $this->assertNull($member->fresh()->member_supply_chain_code);
        $this->assertDatabaseHas('member_supply_chain_syncs', [
            'member_supply_chain_sync_member_id' => $member->getKey(),
            'member_supply_chain_sync_status' => 'failed',
            'member_supply_chain_sync_customer_no' => null,
            'member_supply_chain_sync_error' => 'Error pada Aplikasi Internal: '.$message,
        ]);
        $this->getJson("/api/v1/admin/partnership/members/{$member->getKey()}")->assertOk()
            ->assertJsonPath('data.can_sync_supply_chain', true);
        Http::assertSentCount($refreshToken ? 4 : 2);
    }

    public static function rejectedCustomerResponses(): array
    {
        return [
            'HTTP 200 business rejection' => [200, false],
            'HTTP 400 rejection' => [400, false],
            'HTTP 200 rejection after token refresh' => [200, true],
            'HTTP 400 rejection after token refresh' => [400, true],
        ];
    }

    public function test_company_sale_stops_after_create_and_waits_for_internal_approval_callback(): void
    {
        config()->set('services.supply_chain.enabled', true);
        config()->set('services.supply_chain.member_prefix', 'DEV01');
        config()->set('services.supply_chain.base_url', 'https://supply.test');
        [$member, $trx, $shipping] = $this->createTransactionData();
        $gateway = new FakeSupplyChainGateway;
        $service = new SupplyChainSyncService($gateway);

        $service->syncSale($trx);

        $memberSync = MemberSupplyChainSync::query()->where(
            'member_supply_chain_sync_member_id',
            $member->getKey(),
        )->firstOrFail();
        $trxSync = TrxSupplyChainSync::query()->where(
            'trx_supply_chain_sync_trx_id',
            $trx->getKey(),
        )->firstOrFail();
        $this->assertSame('CUST-001', $memberSync->member_supply_chain_sync_customer_no);
        $this->assertSame('awaiting_callback', $trxSync->trx_supply_chain_sync_status);
        $this->assertSame('saved', $trxSync->trx_supply_chain_sync_stage);
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
        $this->assertArrayNotHasKey('posting', $trxSync->trx_supply_chain_sync_param);
        $this->assertArrayNotHasKey('approve', $trxSync->trx_supply_chain_sync_param);
        $this->assertArrayNotHasKey('posting', $trxSync->trx_supply_chain_sync_url);
        $this->assertArrayNotHasKey('approve', $trxSync->trx_supply_chain_sync_url);
        $this->assertFalse(Schema::hasColumn('trx', 'trx_supply_chain_sale_no'));
        $this->assertFalse(Schema::hasColumn('trx', 'trx_supply_chain_sync_status'));
        $this->assertSame([], $gateway->postedSaleNumbers);
        $this->assertSame([], $gateway->approvedSaleNumbers);
        $this->assertNull($shipping->refresh()->shipping_pickup_delivery_note_number);
        $this->assertSame('DNY0003', $gateway->salePayloads[0]['produk'][0]['kode_produk']);
        $this->assertDatabaseCount('shipping_detail', 0);
        $this->assertSame('DEV01/TRX/CMP/DST/000001', $gateway->salePayloads[0]['jual_ket']);
        $this->assertSame('TRX/CMP/DST/000001', $trx->refresh()->trx_code);

        $trx->forceFill(['trx_supply_chain_code' => null])->save();
        $service->syncSale($trx);

        $this->assertSame('EXT-SALE-001', $trx->refresh()->trx_supply_chain_code);
        $this->assertCount(1, $gateway->salePayloads);
    }

    public function test_sale_sync_does_not_resume_legacy_posting_or_approval_stages(): void
    {
        config()->set('services.supply_chain.enabled', true);
        [, $trx] = $this->createTransactionData();
        TrxSupplyChainSync::query()->create([
            'trx_supply_chain_sync_trx_id' => $trx->getKey(),
            'trx_supply_chain_sync_status' => 'failed',
            'trx_supply_chain_sync_stage' => 'posted',
            'trx_supply_chain_sync_response' => ['save' => ['jual_no' => 'EXT-SALE-001']],
        ]);
        $gateway = new FakeSupplyChainGateway;

        (new SupplyChainSyncService($gateway))->syncSale($trx);

        $sync = TrxSupplyChainSync::query()
            ->where('trx_supply_chain_sync_trx_id', $trx->getKey())
            ->firstOrFail();
        $this->assertSame('awaiting_callback', $sync->trx_supply_chain_sync_status);
        $this->assertSame('posted', $sync->trx_supply_chain_sync_stage);
        $this->assertSame([], $gateway->salePayloads);
        $this->assertSame([], $gateway->postedSaleNumbers);
        $this->assertSame([], $gateway->approvedSaleNumbers);
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
