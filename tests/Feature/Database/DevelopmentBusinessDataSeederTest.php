<?php

namespace Tests\Feature\Database;

use App\Models\Config;
use App\Models\Customer;
use App\Models\GoodsReceive;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAchievement;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Models\MemberNetworkSwitch;
use App\Models\MemberPointTransaction;
use App\Models\MemberRegistration;
use App\Models\MemberStock;
use App\Models\MemberStockLog;
use App\Models\MemberUpgradeQualified;
use App\Models\ReturnModel;
use App\Models\RewardPointAnnualLog;
use App\Models\RewardPointMonthly;
use App\Models\Stockist;
use App\Models\Trx;
use App\Models\TrxSpreadPayment;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WarehouseStockAdjustment;
use App\Models\WarehouseStockLog;
use App\Services\Transaction\TransactionCodeService;
use App\Support\PhoneNumber;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DevelopmentSeeder;
use Database\Seeders\DnyProductSeeder;
use Database\Seeders\InitialAdministratorSeeder;
use Database\Seeders\InitialMemberSeeder;
use Database\Seeders\InitialWarehouseSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DevelopmentBusinessDataSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_development_data_reproduces_the_business_distribution_chain(): void
    {
        Storage::fake('public');
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'local');

        try {
            $this->seed([
                ReferenceDataSeeder::class,
                AccessControlSeeder::class,
                DnyProductSeeder::class,
                InitialAdministratorSeeder::class,
                InitialMemberSeeder::class,
                InitialWarehouseSeeder::class,
                DevelopmentSeeder::class,
            ]);
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }

        $this->assertSame(1, Warehouse::query()->count());
        $warehouse = Warehouse::query()->findOrFail(1);
        $this->assertSame('Warehouse Utama DNY', $warehouse->warehouse_name);
        $this->assertSame('+6281110000101', $warehouse->warehouse_phone);
        $this->assertSame('warehouse@dny.example.test', $warehouse->warehouse_email);
        $this->assertSame(
            'Jalan Teuku Umar Barat No. 88, Dauh Puri, Denpasar Barat, Denpasar, Bali 80113',
            $warehouse->warehouse_address,
        );
        $this->assertSame('/storage/media/development/dny-development.png', $warehouse->warehouse_logo);

        $distributor = Member::query()->findOrFail(1);
        $agent = Member::query()->where('member_mobilephone', '+6281200000101')->firstOrFail();
        $reseller = Member::query()->where('member_mobilephone', '+6281200000201')->firstOrFail();
        $spreadPaymentDistributor = Member::query()
            ->with('level')
            ->where('member_mobilephone', '+6281200000301')
            ->firstOrFail();

        $this->assertSame('0001/0000/0000', $distributor->member_code);
        $this->assertSame('0001/0001/0000', $agent->member_code);
        $this->assertSame('0001/0001/0001', $reseller->member_code);
        $this->assertSame('0002/0000/0000', $spreadPaymentDistributor->member_code);
        $this->assertSame('DST', $spreadPaymentDistributor->level?->member_level_code);
        $this->assertSame($distributor->getKey(), $spreadPaymentDistributor->member_parent_member_id);
        $this->assertSame(1, MemberAddress::query()
            ->where('member_address_member_id', $distributor->getKey())
            ->where('member_address_is_default', 1)
            ->count());
        $this->assertSame(1, MemberBankAccount::query()
            ->where('member_bank_account_member_id', $distributor->getKey())
            ->where('member_bank_account_is_default', 1)
            ->count());

        foreach ([$agent, $reseller, $spreadPaymentDistributor] as $developmentMember) {
            $account = MemberAccount::query()
                ->where('member_account_member_id', $developmentMember->getKey())
                ->firstOrFail();
            $this->assertSame($developmentMember->member_code, $account->member_account_username);
            $this->assertTrue(Hash::check((string) config('initial_data.member.password'), $account->member_account_password));
        }

        $this->assertSame($distributor->getKey(), $agent->member_parent_member_id);
        $this->assertSame($agent->getKey(), $reseller->member_parent_member_id);
        $spreadPaymentAccount = MemberAccount::query()
            ->where('member_account_member_id', $spreadPaymentDistributor->getKey())
            ->firstOrFail();
        $this->actingAs($spreadPaymentAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/options')
            ->assertOk()
            ->assertJsonPath('data.seller.type', 'warehouse')
            ->assertJsonCount(1, 'data.spread_payment_banks')
            ->assertJsonPath('data.spread_payment_banks.0.type', 'spread_payment');
        $this->assertDatabaseHas('member_registration', [
            'member_registration_mobilephone' => '+6281500000001',
            'member_registration_member_level_id' => 2,
            'member_registration_upline_member_id' => $distributor->getKey(),
        ]);
        $this->assertDatabaseMissing('member_registration', [
            'member_registration_mobilephone' => '+6281500000002',
        ]);
        $this->assertSeederPhoneNumbersAreValid();
        $this->assertFalse(Member::query()
            ->where('member_mobilephone', $warehouse->warehouse_phone)
            ->exists());

        $qualificationEnd = now()->subMonth()->startOfMonth();
        $this->assertDatabaseHas((new MemberUpgradeQualified)->getTable(), [
            'member_upgrade_qualified_member_id' => $reseller->getKey(),
            'member_upgrade_qualified_from_year_month' => (int) $qualificationEnd
                ->copy()
                ->subMonths(2)
                ->format('ym'),
            'member_upgrade_qualified_to_year_month' => (int) $qualificationEnd->format('ym'),
            'member_upgrade_qualified_status' => 'requested',
        ]);

        $this->assertWarehouseStockLogsMatchBalances();
        $this->assertMemberStockLogsMatchBalances();
        $this->assertDocumentCodes();
        $this->assertConnectedBusinessData();
        $actionableShipment = Trx::query()
            ->with(['shippingExpress', 'details'])
            ->where('trx_seller_type', 'warehouse')
            ->where('trx_shipping_method', 'courier_express')
            ->where('trx_status', 'processing')
            ->firstOrFail();
        $this->assertTrue($actionableShipment->trx_datetime->isToday());
        $this->assertGreaterThan(0, (int) $actionableShipment->trx_shipping_cost);
        $this->assertNotNull($actionableShipment->shippingExpress);
        $this->assertSame('', $actionableShipment->shippingExpress->shipping_courier_express_order_id);
        $this->assertSame('', $actionableShipment->shippingExpress->shipping_courier_express_pickup_number);
        $this->assertNull($actionableShipment->shippingExpress->shipping_courier_express_awb);
        $this->assertSame(
            (int) $actionableShipment->details->sum(
                fn ($detail): int => (int) $detail->trx_detail_product_weight
                    * (int) $detail->trx_detail_qty,
            ),
            (int) $actionableShipment->shippingExpress->shipping_courier_express_package_weight,
        );
        $waitingReturn = ReturnModel::query()
            ->where('return_status', 'waiting_member_shipment')
            ->with('details')
            ->firstOrFail();
        $waitingReturnDetail = $waitingReturn->details->firstOrFail();
        $this->assertSame(
            (int) $waitingReturnDetail->return_detail_qty,
            (int) MemberStock::query()
                ->where('member_stock_member_id', $waitingReturn->return_member_id)
                ->where('member_stock_product_id', $waitingReturnDetail->return_detail_product_id)
                ->value('member_stock_transfer_out'),
        );
        $developmentConfig = Config::query()
            ->where('config_key', 'development')
            ->where('config_type', 'json')
            ->where('config_scope', Config::SCOPE_SYSTEM)
            ->firstOrFail();
        $this->assertNotEmpty($developmentConfig->config_value);
        $this->assertNotEmpty(json_decode($developmentConfig->config_value, true, 512, JSON_THROW_ON_ERROR)['seeded_at'] ?? null);

        $period = now()->subMonths(2)->startOfMonth();
        $periodCode = $period->format('Ym');
        $distributorPurchase = Trx::query()
            ->where(
                'trx_code',
                'like',
                '%/'.TransactionCodeService::deterministicUniqueSuffix("DEV-RWD-DST-{$periodCode}"),
            )
            ->firstOrFail();
        $agentPurchase = Trx::query()
            ->where(
                'trx_code',
                'like',
                '%/'.TransactionCodeService::deterministicUniqueSuffix("DEV-RWD-AGT-{$periodCode}"),
            )
            ->firstOrFail();
        $resellerPurchase = Trx::query()
            ->where(
                'trx_code',
                'like',
                '%/'.TransactionCodeService::deterministicUniqueSuffix("DEV-RWD-RSL-{$periodCode}"),
            )
            ->firstOrFail();

        $this->assertSame('warehouse', $distributorPurchase->trx_seller_type);
        $this->assertSame($distributor->getKey(), $agentPurchase->trx_seller_id);
        $this->assertSame($agent->getKey(), $resellerPurchase->trx_seller_id);
        $this->assertSame(400, (int) $distributorPurchase->details()->sum('trx_detail_qty'));
        $this->assertSame(180, (int) $agentPurchase->details()->sum('trx_detail_qty'));
        $this->assertSame(70, (int) $resellerPurchase->details()->sum('trx_detail_qty'));

        $resellerPo = $this->developmentTransaction('DEV-PO-RSL-AGT');
        $agentPo = $this->developmentTransaction('DEV-PO-AGT-DST');
        $distributorPo = $this->developmentTransaction('DEV-PO-DST-CMP');
        $this->assertSame(0, (int) $resellerPo->trx_parent_trx_id);
        $this->assertSame($resellerPo->getKey(), (int) $agentPo->trx_parent_trx_id);
        $this->assertSame($agentPo->getKey(), (int) $distributorPo->trx_parent_trx_id);
        $this->assertSame('agent', $resellerPo->trx_seller_type);
        $this->assertSame('distributor', $agentPo->trx_seller_type);
        $this->assertSame('warehouse', $distributorPo->trx_seller_type);
        $this->assertSame(
            explode('/', $resellerPo->trx_code)[3],
            explode('/', $agentPo->trx_code)[3],
        );
        $this->assertSame(
            explode('/', $resellerPo->trx_code)[3],
            explode('/', $distributorPo->trx_code)[3],
        );
        $this->assertSame($reseller->getKey(), $resellerPo->trx_buyer_id);
        $this->assertSame($agent->getKey(), $agentPo->trx_buyer_id);
        $this->assertSame($distributor->getKey(), $distributorPo->trx_buyer_id);
        $this->assertSame(3, MemberPointTransaction::query()
            ->whereIn('member_point_transaction_trx_id', [
                $resellerPo->getKey(),
                $agentPo->getKey(),
                $distributorPo->getKey(),
            ])
            ->count());

        $this->assertMonthlyReward(
            $distributor,
            $period->year,
            $period->month,
            400,
            2_000_000,
            0,
            1,
        );
        $this->assertMonthlyReward(
            $agent,
            $period->year,
            $period->month,
            180,
            720_000,
            $distributor->getKey(),
            0,
        );
        $this->assertMonthlyReward(
            $reseller,
            $period->year,
            $period->month,
            70,
            210_000,
            $agent->getKey(),
            0,
        );

        foreach ([$distributor, $agent, $reseller] as $member) {
            $this->assertDatabaseMissing((new RewardPointMonthly)->getTable(), [
                'reward_point_monthly_member_id' => $member->getKey(),
                'reward_point_monthly_year' => now()->year,
                'reward_point_monthly_month' => now()->month,
            ]);
            $this->assertDatabaseMissing((new MemberAchievement)->getTable(), [
                'member_achievement_member_id' => $member->getKey(),
                'member_achievement_year' => now()->year,
                'member_achievement_month' => now()->month,
            ]);
        }

        $this->assertDatabaseHas((new MemberAchievement)->getTable(), [
            'member_achievement_member_id' => $agent->getKey(),
            'member_achievement_year' => $period->year,
            'member_achievement_month' => $period->month,
            'member_achievement_point' => 180,
        ]);

        $this->assertTrue(Schema::hasColumn(
            (new MemberNetworkSwitch)->getTable(),
            'network_switch_upgrade_qualified_id',
        ));
        $this->assertFalse(Schema::hasColumn(
            (new MemberNetworkSwitch)->getTable(),
            'network_switch_qualified_id',
        ));
    }

    public function test_development_data_cannot_be_seeded_twice(): void
    {
        Storage::fake('public');
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'local');

        try {
            $this->seed([
                ReferenceDataSeeder::class,
                AccessControlSeeder::class,
                DnyProductSeeder::class,
                InitialAdministratorSeeder::class,
                InitialMemberSeeder::class,
                InitialWarehouseSeeder::class,
                DevelopmentSeeder::class,
            ]);

            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage('Data development sudah tersedia');
            $this->seed(DevelopmentSeeder::class);
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }
    }

    private function assertMonthlyReward(
        Member $member,
        int $year,
        int $month,
        int $quantity,
        int $bonusValue,
        int $uplineId,
        int $administratorId,
    ): void {
        $reward = RewardPointMonthly::query()
            ->where('reward_point_monthly_member_id', $member->getKey())
            ->where('reward_point_monthly_year', $year)
            ->where('reward_point_monthly_month', $month)
            ->firstOrFail();

        $this->assertSame($quantity, $reward->reward_point_monthly_total_qty);
        $this->assertSame($bonusValue, $reward->reward_point_monthly_bonus_value);
        $this->assertSame($uplineId, $reward->reward_point_monthly_upline_id);
        $this->assertSame($administratorId, $reward->reward_point_monthly_admin_id);
        $this->assertTrue($reward->reward_point_monthly_is_processed);
    }

    private function assertWarehouseStockLogsMatchBalances(): void
    {
        foreach (WarehouseStock::query()->get() as $stock) {
            $logs = WarehouseStockLog::query()
                ->where('warehouse_stock_log_warehouse_id', $stock->warehouse_stock_warehouse_id)
                ->where('warehouse_stock_log_product_id', $stock->warehouse_stock_product_id)
                ->orderBy('warehouse_stock_log_datetime')
                ->orderBy('warehouse_stock_log_id')
                ->get();

            $this->assertNotEmpty($logs);
            $this->assertSame(
                (int) $stock->warehouse_stock_balance,
                (int) $logs->last()->warehouse_stock_log_balance,
            );
            $this->assertSame(
                (int) $stock->warehouse_stock_balance,
                $logs->sum(fn (WarehouseStockLog $log): int => $log->warehouse_stock_log_type === 'in'
                    ? (int) $log->warehouse_stock_log_quantity
                    : -(int) $log->warehouse_stock_log_quantity),
            );
        }
    }

    private function assertMemberStockLogsMatchBalances(): void
    {
        foreach (MemberStock::query()->get() as $stock) {
            $logs = MemberStockLog::query()
                ->where('member_stock_log_member_id', $stock->member_stock_member_id)
                ->where('member_stock_log_product_id', $stock->member_stock_product_id)
                ->orderBy('member_stock_log_datetime')
                ->orderBy('member_stock_log_id')
                ->get();

            $this->assertNotEmpty($logs);
            $this->assertSame(
                (int) $stock->member_stock_balance,
                (int) $logs->last()->member_stock_log_balance,
            );
            $this->assertSame(
                (int) $stock->member_stock_balance,
                $logs->sum(fn (MemberStockLog $log): int => $log->member_stock_log_type === 'in'
                    ? (int) $log->member_stock_log_quantity
                    : -(int) $log->member_stock_log_quantity),
            );
        }
    }

    private function assertDocumentCodes(): void
    {
        $this->assertCodeCollection(
            ReturnModel::query()->pluck('return_code')->all(),
            'RTR',
        );
        $this->assertCodeCollection(
            GoodsReceive::query()->pluck('goods_receive_number')->all(),
            'GRN',
        );
        $this->assertCodeCollection(
            WarehouseStockAdjustment::query()->pluck('stock_adjustment_code')->all(),
            'ADJ',
        );
    }

    /** @param list<string> $codes */
    private function assertCodeCollection(array $codes, string $prefix): void
    {
        $this->assertNotEmpty($codes);
        $this->assertCount(count($codes), array_unique($codes));

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression(
                '~^'.$prefix.'/\d{6}/[A-Z0-9]{6}$~',
                $code,
            );
        }
    }

    private function assertConnectedBusinessData(): void
    {
        foreach (GoodsReceive::query()->with('trx')->where('goods_receive_trx_id', '>', 0)->get() as $receive) {
            $this->assertNotNull($receive->trx);
            $this->assertSame($receive->goods_receive_buyer_id, $receive->trx->trx_buyer_id);
            $this->assertSame($receive->goods_receive_seller_id, $receive->trx->trx_seller_id);
        }

        foreach (ReturnModel::query()->with(['goodsReceive', 'trx'])->get() as $return) {
            $this->assertNotNull($return->goodsReceive);
            $this->assertNotNull($return->trx);
            $this->assertSame(
                $return->goodsReceive->goods_receive_trx_id,
                $return->trx->getKey(),
            );
        }

        foreach (TrxSpreadPayment::query()->with(['trx', 'upline', 'member'])->get() as $payment) {
            $this->assertNotNull($payment->trx);
            $this->assertNotNull($payment->upline);
            $this->assertNotNull($payment->member);
        }

        foreach (RewardPointAnnualLog::query()->with(['transaction', 'member'])->get() as $rewardLog) {
            $this->assertNotNull($rewardLog->transaction);
            $this->assertNotNull($rewardLog->member);
        }
    }

    private function assertSeederPhoneNumbersAreValid(): void
    {
        $phoneNumbers = collect()
            ->merge(Warehouse::query()->pluck('warehouse_phone'))
            ->merge(Member::query()->pluck('member_mobilephone'))
            ->merge(MemberAddress::query()->pluck('member_address_phone'))
            ->merge(Stockist::query()->pluck('stockist_mobilephone'))
            ->merge(Customer::query()->pluck('customer_phone'))
            ->merge(Customer::query()->pluck('customer_whatsapp'))
            ->merge(MemberRegistration::query()->pluck('member_registration_mobilephone'))
            ->filter(fn ($phone): bool => is_string($phone) && $phone !== '');

        $this->assertNotEmpty($phoneNumbers);

        foreach ($phoneNumbers as $phoneNumber) {
            $this->assertTrue(
                PhoneNumber::isValid($phoneNumber),
                "Nomor telepon seeder tidak valid: {$phoneNumber}",
            );
        }
    }

    private function developmentTransaction(string $key): Trx
    {
        return Trx::query()
            ->where(
                'trx_code',
                'like',
                '%/'.TransactionCodeService::deterministicUniqueSuffix($key),
            )
            ->firstOrFail();
    }
}
