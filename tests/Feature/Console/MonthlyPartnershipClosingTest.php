<?php

namespace Tests\Feature\Console;

use App\Exceptions\ProcessException;
use App\Models\Member;
use App\Models\MemberLevel;
use App\Models\MemberPointTransaction;
use App\Models\RewardStockist;
use App\Models\Stockist;
use App\Models\Trx;
use App\Models\TrxDetail;
use App\Models\TrxSpreadPayment;
use App\Services\Partnership\MonthlyPartnershipClosingService;
use App\Services\Reward\MemberPointService;
use App\Support\BusinessConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonthlyPartnershipClosingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_monthly_closing_creates_reward_achievement_and_upgrade_qualification_idempotently(): void
    {
        Carbon::setTestNow('2026-08-01 00:05:00');
        [$distributorLevel, $agentLevel, $resellerLevel] = $this->levels();
        $distributor = $this->member(1, 'DST-001', $distributorLevel);
        $agent = $this->member(2, 'AGT-001', $agentLevel, $distributor);
        $reseller = $this->member(3, 'RSL-001', $resellerLevel, $agent);

        foreach (['2026-05-10', '2026-06-10', '2026-07-10'] as $index => $date) {
            $this->purchase($reseller, $agent, 50, $date, "PO-RSL-{$index}");
            $this->retailSale($reseller, 100 + $index, $date, "RTL-RSL-{$index}");
        }
        $this->retailSale($reseller, 998, '2026-07-11', 'RTL-RSL-PAID', 'processing');
        $this->retailSale($reseller, 999, '2026-07-11', 'RTL-RSL-UNPAID', 'waiting_payment');

        foreach (['2026-05', '2026-06', '2026-07'] as $period) {
            $this->assertSame(0, Artisan::call('rewards:close-month', ['--period' => $period]));
        }

        $this->assertDatabaseHas('member_achievement', [
            'member_achievement_member_id' => $reseller->getKey(),
            'member_achievement_year' => 2026,
            'member_achievement_month' => 7,
            'member_achievement_point' => 50,
            'member_achievement_customer_count' => 2,
            'member_achievement_total_trx_amount' => 5_000_000,
        ]);
        $this->assertDatabaseHas('reward_point_monthly', [
            'reward_point_monthly_member_id' => $reseller->getKey(),
            'reward_point_monthly_upline_id' => $agent->getKey(),
            'reward_point_monthly_member_level_id' => $resellerLevel->getKey(),
            'reward_point_monthly_year' => 2026,
            'reward_point_monthly_month' => 7,
            'reward_point_monthly_total_qty' => 50,
            'reward_point_monthly_bonus_value' => 150_000,
            'reward_point_monthly_is_processed' => 0,
        ]);
        $this->assertDatabaseHas('member_upgrade_qualified', [
            'member_upgrade_qualified_member_id' => $reseller->getKey(),
            'member_upgrade_qualified_from_level_id' => $resellerLevel->getKey(),
            'member_upgrade_qualified_to_level_id' => $agentLevel->getKey(),
            'member_upgrade_qualified_from_year_month' => 2605,
            'member_upgrade_qualified_to_year_month' => 2607,
            'member_upgrade_qualified_status' => 'requested',
        ]);
        $this->assertDatabaseCount('trx_spread_payment', 0);

        $this->assertSame(0, Artisan::call('rewards:close-month', ['--period' => '2026-07']));
        $this->assertDatabaseCount('member_upgrade_qualified', 1);
        $this->assertDatabaseCount('trx_spread_payment', 0);
    }

    public function test_agent_upgrade_uses_three_month_purchases_buying_patients_and_recruited_resellers(): void
    {
        Carbon::setTestNow('2026-08-01 00:05:00');
        [$distributorLevel, $agentLevel, $resellerLevel] = $this->levels();
        $distributor = $this->member(1, 'DST-001', $distributorLevel);
        $agent = $this->member(2, 'AGT-001', $agentLevel, $distributor);

        foreach ([3 => '2026-05-15', 4 => '2026-06-15', 5 => '2026-07-15'] as $id => $joinedAt) {
            $this->member($id, "RSL-00{$id}", $resellerLevel, $agent, "{$joinedAt} 00:00:00");
        }

        foreach (['2026-05-10', '2026-06-10', '2026-07-10'] as $index => $date) {
            $this->purchase($agent, $distributor, 270, $date, "PO-AGT-{$index}");
        }

        foreach (range(1, 10) as $patient) {
            $month = 5 + (($patient - 1) % 3);
            $this->retailSale(
                $agent,
                200 + $patient,
                "2026-0{$month}-15",
                "RTL-AGT-{$patient}",
            );
        }

        foreach (['2026-05', '2026-06', '2026-07'] as $period) {
            $this->assertSame(0, Artisan::call('rewards:close-month', ['--period' => $period]));
        }

        $this->assertDatabaseHas('member_achievement', [
            'member_achievement_member_id' => $agent->getKey(),
            'member_achievement_year' => 2026,
            'member_achievement_month' => 7,
            'member_achievement_point' => 270,
            'member_achievement_total_trx_amount' => 5_000_000,
        ]);
        $this->assertDatabaseHas('member_upgrade_qualified', [
            'member_upgrade_qualified_member_id' => $agent->getKey(),
            'member_upgrade_qualified_from_level_id' => $agentLevel->getKey(),
            'member_upgrade_qualified_to_level_id' => $distributorLevel->getKey(),
            'member_upgrade_qualified_from_year_month' => 2605,
            'member_upgrade_qualified_to_year_month' => 2607,
            'member_upgrade_qualified_status' => 'requested',
        ]);
    }

    public function test_monthly_closing_uses_grouped_json_configuration(): void
    {
        Carbon::setTestNow('2026-08-01 00:05:00');
        [$distributorLevel, $agentLevel, $resellerLevel] = $this->levels();
        $distributor = $this->member(1, 'DST-001', $distributorLevel);
        $agent = $this->member(2, 'AGT-001', $agentLevel, $distributor);
        $reseller = $this->member(3, 'RSL-001', $resellerLevel, $agent);
        $distributorChild = $this->member(4, 'DST-CHILD', $distributorLevel, $distributor);
        $this->stockist($distributor);

        BusinessConfig::put('reward_monthly', [
            'reward_monthly_min_qty_reseller' => 60,
        ]);
        BusinessConfig::put('partnership', [
            'spread_payment_percentage' => 2,
        ]);
        $this->purchase($reseller, $agent, 50, '2026-07-10', 'PO-RSL-CONFIG');
        $this->purchase($distributorChild, $distributor, 50, '2026-07-10', 'PO-DST-CONFIG');

        $this->assertSame(0, Artisan::call('rewards:close-month', ['--period' => '2026-07']));

        $this->assertDatabaseHas('reward_point_monthly', [
            'reward_point_monthly_member_id' => $reseller->getKey(),
            'reward_point_monthly_total_qty' => 50,
            'reward_point_monthly_bonus_value' => 0,
        ]);
        $this->assertDatabaseHas('trx_spread_payment', [
            'trx_spread_payment_upline_id' => $distributor->getKey(),
            'trx_spread_payment_member_id' => $distributorChild->getKey(),
            'trx_spread_payment_percentage' => 2,
            'trx_spread_payment_amount' => 100_000,
        ]);
    }

    public function test_monthly_closing_uses_product_point_snapshots_instead_of_item_quantity(): void
    {
        Carbon::setTestNow('2026-08-01 00:05:00');
        [$distributorLevel, $agentLevel, $resellerLevel] = $this->levels();
        $distributor = $this->member(1, 'DST-001', $distributorLevel);
        $agent = $this->member(2, 'AGT-001', $agentLevel, $distributor);
        $reseller = $this->member(3, 'RSL-001', $resellerLevel, $agent);
        $this->purchase($reseller, $agent, 10, '2026-07-10', 'POINT-SNAPSHOT');
        $trx = Trx::query()->where('trx_code', 'POINT-SNAPSHOT')->sole();
        $trx->details()->update([
            'trx_detail_product_monthly_points' => 3,
            'trx_detail_product_annual_points' => 7,
        ]);

        $this->assertSame(0, Artisan::call('rewards:close-month', ['--period' => '2026-07']));
        $this->assertDatabaseHas('member_achievement', [
            'member_achievement_member_id' => $reseller->getKey(),
            'member_achievement_point' => 30,
        ]);
        $this->assertDatabaseHas('reward_point_monthly', [
            'reward_point_monthly_member_id' => $reseller->getKey(),
            'reward_point_monthly_total_qty' => 30,
            'reward_point_monthly_bonus_value' => 90_000,
        ]);
    }

    public function test_approved_ledger_records_separate_points_and_cancellation_reverses_annual_snapshot(): void
    {
        Carbon::setTestNow('2026-07-10 12:00:00');
        [$distributorLevel, $agentLevel, $resellerLevel] = $this->levels();
        $distributor = $this->member(1, 'DST-001', $distributorLevel);
        $agent = $this->member(2, 'AGT-001', $agentLevel, $distributor);
        $reseller = $this->member(3, 'RSL-001', $resellerLevel, $agent);
        $this->purchase($reseller, $agent, 10, '2026-07-10', 'POINT-LEDGER');
        $trx = Trx::query()->where('trx_code', 'POINT-LEDGER')->sole();
        $trx->details()->update([
            'trx_detail_product_monthly_points' => 3,
            'trx_detail_product_annual_points' => 7,
        ]);
        $service = app(MemberPointService::class);
        $service->recordForApprovedPayment($trx);
        $service->recordForApprovedPayment($trx);

        $this->assertDatabaseCount('member_point_transaction', 1);
        $this->assertDatabaseHas('member_point_transaction', [
            'member_point_transaction_trx_id' => $trx->getKey(),
            'member_point_transaction_quantity' => 10,
            'member_point_transaction_monthly_points' => 30,
            'member_point_transaction_annual_points' => 70,
        ]);
        $this->assertDatabaseHas('reward_point_annual', [
            'reward_point_annual_member_id' => $reseller->getKey(),
            'reward_point_annual_total_points' => 70,
        ]);
        Carbon::setTestNow('2026-08-01 00:05:00');
        $this->assertSame(0, Artisan::call('rewards:close-month', ['--period' => '2026-07']));
        $this->assertDatabaseHas('reward_point_monthly', [
            'reward_point_monthly_member_id' => $reseller->getKey(),
            'reward_point_monthly_total_qty' => 30,
        ]);
        $trx->details()->update(['trx_detail_product_annual_points' => 99]);
        $service->reverseForCancelledTransaction($trx);
        $service->reverseForCancelledTransaction($trx);
        $this->assertDatabaseHas('reward_point_annual', [
            'reward_point_annual_member_id' => $reseller->getKey(),
            'reward_point_annual_total_points' => 0,
        ]);
        $this->assertDatabaseHas('reward_point_annual_log', [
            'reward_point_annual_log_trx_id' => $trx->getKey(),
            'reward_point_annual_log_type' => 'out',
            'reward_point_annual_log_points' => 70,
        ]);
    }

    public function test_monthly_closing_rejects_rewards_beyond_exact_api_number_capacity(): void
    {
        Carbon::setTestNow('2026-08-01 00:05:00');
        [$distributorLevel] = $this->levels();
        $distributor = $this->member(1, 'DST-001', $distributorLevel);
        MemberPointTransaction::query()->create([
            'member_point_transaction_member_id' => $distributor->getKey(),
            'member_point_transaction_trx_id' => 100,
            'member_point_transaction_quantity' => 10,
            'member_point_transaction_monthly_points' => 2_000_000_000_000,
            'member_point_transaction_annual_points' => 10,
            'member_point_transaction_year' => 2026,
            'member_point_transaction_month' => 7,
            'member_point_transaction_approved_datetime' => '2026-07-10 12:00:00',
        ]);

        $this->expectException(ProcessException::class);
        app(MonthlyPartnershipClosingService::class)->close(Carbon::parse('2026-07-01'));
    }

    public function test_transaction_points_reject_aggregate_values_that_javascript_cannot_represent_exactly(): void
    {
        $trx = new Trx;
        $trx->setRelation('details', collect(range(1, 23))->map(fn (): TrxDetail => new TrxDetail([
            'trx_detail_qty' => 99999,
            'trx_detail_product_monthly_points' => 4294967295,
            'trx_detail_product_annual_points' => 1,
        ])));

        $this->expectException(ProcessException::class);
        $trx->pointTotals();
    }

    #[DataProvider('stockistEligibilityCases')]
    public function test_monthly_closing_creates_vouchers_only_for_active_distributor_stockists(
        int $levelIndex,
        ?int $active,
        int $deleted,
        bool $eligible,
    ): void {
        Carbon::setTestNow('2026-08-01 00:05:00');
        $levels = $this->levels();
        $supplier = $this->member(1, 'SUPPLIER', $levels[0]);
        $member = $this->member(2, 'OWNER', $levels[$levelIndex], $supplier);
        if ($active !== null) {
            $this->stockist($member, $active, $deleted);
        }
        $this->transaction([
            'trx_code' => 'VOUCHER-PURCHASE',
            'trx_seller_type' => 'warehouse',
            'trx_seller_id' => 1,
            'trx_buyer_type' => $this->memberType($member),
            'trx_buyer_id' => $member->getKey(),
            'trx_type' => 'stock',
            'trx_status' => 'completed',
            'trx_grand_total_price' => 50000000,
            'trx_datetime' => '2026-07-10 10:00:00',
        ]);
        $historical = RewardStockist::query()->create([
            'reward_stockist_member_id' => $member->getKey(),
            'reward_stockist_year' => 2025,
            'reward_stockist_month' => 1,
            'reward_stockist_total_trx_amount' => 50000000,
            'reward_stockist_bonus_value' => 1250000,
            'reward_stockist_used_value' => 0,
            'reward_stockist_used_trx_id' => 0,
        ]);

        $result = app(MonthlyPartnershipClosingService::class)->close(Carbon::parse('2026-07-01'));

        $this->assertSame($eligible ? 1 : 0, $result['stockist_rewards']);
        $this->assertSame($eligible, RewardStockist::query()
            ->where('reward_stockist_member_id', $member->getKey())
            ->where('reward_stockist_year', 2026)
            ->where('reward_stockist_month', 7)
            ->exists());
        $this->assertSame(1250000, (int) $historical->fresh()->reward_stockist_bonus_value);
    }

    public static function stockistEligibilityCases(): array
    {
        return [
            'non-stockist distributor' => [0, null, 0, false],
            'inactive stockist' => [0, 0, 0, false],
            'deleted stockist' => [0, 1, 1, false],
            'stockist agent' => [1, 1, 0, false],
            'stockist reseller' => [2, 1, 0, false],
            'eligible distributor stockist' => [0, 1, 0, true],
        ];
    }

    public function test_monthly_closing_sharing_profit_requires_eligible_direct_distributor_parent(): void
    {
        Carbon::setTestNow('2026-08-01 00:05:00');
        [$distributorLevel, $agentLevel] = $this->levels();
        $stockist = $this->member(1, 'STOCKIST', $distributorLevel);
        $this->stockist($stockist);
        $child = $this->member(2, 'DIRECT-DST', $distributorLevel, $stockist);
        $agent = $this->member(3, 'AGENT', $agentLevel, $stockist);
        $unrelated = $this->member(4, 'UNRELATED-DST', $distributorLevel);
        $plainDistributor = $this->member(5, 'PLAIN-DST', $distributorLevel);
        $plainChild = $this->member(6, 'PLAIN-CHILD', $distributorLevel, $plainDistributor);
        $this->purchase($child, $stockist, 50, '2026-07-10', 'ELIGIBLE-SPREAD');
        $this->purchase($agent, $stockist, 50, '2026-07-10', 'AGENT-BUYER');
        $this->purchase($unrelated, $stockist, 50, '2026-07-10', 'UNRELATED-BUYER');
        $this->purchase($plainChild, $plainDistributor, 50, '2026-07-10', 'NONSTOCKIST-PARENT');
        $historical = TrxSpreadPayment::query()->create([
            'trx_spread_payment_trx_id' => Trx::query()->where('trx_code', 'NONSTOCKIST-PARENT')->value('trx_id'),
            'trx_spread_payment_upline_id' => $plainDistributor->getKey(),
            'trx_spread_payment_member_id' => $plainChild->getKey(),
            'trx_spread_payment_amount' => 123456,
            'trx_spread_payment_status' => 'paid',
            'trx_spread_payment_created_datetime' => now(),
        ]);

        $result = app(MonthlyPartnershipClosingService::class)->close(Carbon::parse('2026-07-01'));

        $this->assertSame(1, $result['spread_payments']);
        $this->assertDatabaseCount('trx_spread_payment', 2);
        $this->assertDatabaseHas('trx_spread_payment', [
            'trx_spread_payment_upline_id' => $stockist->getKey(),
            'trx_spread_payment_member_id' => $child->getKey(),
            'trx_spread_payment_amount' => 50000,
        ]);
        $this->assertSame(123456, (int) $historical->fresh()->trx_spread_payment_amount);
        $this->assertSame('paid', $historical->fresh()->trx_spread_payment_status);

        $repeated = app(MonthlyPartnershipClosingService::class)->close(Carbon::parse('2026-07-01'));
        $this->assertSame(0, $repeated['spread_payments']);
        $this->assertDatabaseCount('trx_spread_payment', 2);
    }

    private function stockist(Member $member, int $active = 1, int $deleted = 0): void
    {
        Stockist::query()->create([
            'stockist_member_id' => $member->getKey(),
            'stockist_name' => $member->member_name,
            'stockist_note' => '',
            'stockist_is_active' => $active,
            'stockist_is_deleted' => $deleted,
            'stockist_input_datetime' => now(),
        ]);
    }

    /** @return array{MemberLevel, MemberLevel, MemberLevel} */
    private function levels(): array
    {
        return [
            $this->level(1, 'DST', 5_000, 1),
            $this->level(2, 'AGT', 4_000, 2),
            $this->level(3, 'RSL', 3_000, 3),
        ];
    }

    private function level(int $id, string $code, int $pointValue, int $sortOrder): MemberLevel
    {
        return MemberLevel::query()->updateOrCreate(
            ['member_level_code' => $code],
            [
                'member_level_name' => $code,
                'member_level_description' => $code,
                'member_level_min_order' => 0,
                'member_level_point_value' => $pointValue,
                'member_level_sort_order' => $sortOrder,
                'member_level_is_active' => 1,
            ],
        );
    }

    private function member(
        int $id,
        string $code,
        MemberLevel $level,
        ?Member $parent = null,
        string $joinedAt = '2026-01-01 00:00:00',
    ): Member {
        return Member::query()->create([
            'member_id' => $id,
            'member_code' => $code,
            'member_member_level_id' => $level->getKey(),
            'member_parent_member_id' => $parent?->getKey() ?? 0,
            'member_name' => $code,
            'member_email' => strtolower($code).'@example.test',
            'member_mobilephone' => '08120000000'.$id,
            'member_identity_no' => 'ID-'.$id,
            'member_join_datetime' => $joinedAt,
            'member_status' => 1,
        ]);
    }

    private function purchase(Member $buyer, Member $seller, int $quantity, string $date, string $code): void
    {
        $trx = $this->transaction([
            'trx_code' => $code,
            'trx_seller_type' => $this->memberType($seller),
            'trx_seller_id' => $seller->getKey(),
            'trx_buyer_type' => $this->memberType($buyer),
            'trx_buyer_id' => $buyer->getKey(),
            'trx_type' => 'stock',
            'trx_grand_total_price' => 5_000_000,
            'trx_status' => 'completed',
            'trx_datetime' => "{$date} 10:00:00",
        ]);
        $trx->details()->create([
            'trx_detail_product_id' => 1,
            'trx_detail_product_code' => 'DNY-001',
            'trx_detail_product_name' => 'Produk DNY',
            'trx_detail_qty' => $quantity,
        ]);
    }

    private function retailSale(
        Member $seller,
        int $customerId,
        string $date,
        string $code,
        string $status = 'completed',
    ): void {
        $trx = $this->transaction([
            'trx_code' => $code,
            'trx_seller_type' => $this->memberType($seller),
            'trx_seller_id' => $seller->getKey(),
            'trx_buyer_type' => 'customer',
            'trx_buyer_id' => $customerId,
            'trx_type' => 'retail',
            'trx_grand_total_price' => 6_000_000,
            'trx_status' => $status,
            'trx_datetime' => "{$date} 12:00:00",
        ]);
        $trx->details()->create([
            'trx_detail_product_id' => 1,
            'trx_detail_product_code' => 'DNY-001',
            'trx_detail_product_name' => 'Produk DNY',
            'trx_detail_qty' => 60,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function transaction(array $attributes): Trx
    {
        return Trx::query()->create([
            'trx_parent_trx_id' => 0,
            'trx_is_preorder' => 0,
            'trx_reference_id' => 0,
            'trx_total_price' => $attributes['trx_grand_total_price'] ?? 0,
            'trx_discount' => 0,
            'trx_discount_value' => 0,
            'trx_grand_total_price' => $attributes['trx_grand_total_price'] ?? 0,
            'trx_shipping_cost' => 0,
            'trx_payment_charge' => 0,
            'trx_grand_total_nett_price' => $attributes['trx_grand_total_price'] ?? 0,
            'trx_bill_remaining' => 0,
            'trx_bill_augment' => 0,
            'trx_bill_amount' => $attributes['trx_grand_total_price'] ?? 0,
            'trx_payment_method' => 'cash',
            'trx_shipping_method' => 'pickup',
            'trx_status_datetime' => $attributes['trx_datetime'],
            ...$attributes,
        ]);
    }

    private function memberType(Member $member): string
    {
        return match ($member->level->member_level_code) {
            'DST' => 'distributor',
            'AGT' => 'agent',
            'RSL' => 'reseller',
        };
    }
}
