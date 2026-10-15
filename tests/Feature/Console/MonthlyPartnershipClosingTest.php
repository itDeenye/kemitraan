<?php

namespace Tests\Feature\Console;

use App\Exceptions\ProcessException;
use App\Models\Member;
use App\Models\MemberLevel;
use App\Models\MemberPointTransaction;
use App\Models\Trx;
use App\Models\TrxDetail;
use App\Services\Partnership\MonthlyPartnershipClosingService;
use App\Services\Reward\MemberPointService;
use App\Support\BusinessConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
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
        $this->assertDatabaseHas('trx_spread_payment', [
            'trx_spread_payment_upline_id' => $agent->getKey(),
            'trx_spread_payment_member_id' => $reseller->getKey(),
            'trx_spread_payment_percentage' => 1,
            'trx_spread_payment_amount' => 50_000,
            'trx_spread_payment_status' => 'pending',
        ]);

        $this->assertSame(0, Artisan::call('rewards:close-month', ['--period' => '2026-07']));
        $this->assertDatabaseCount('member_upgrade_qualified', 1);
        $this->assertDatabaseCount('trx_spread_payment', 3);
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

        BusinessConfig::put('reward_monthly', [
            'reward_monthly_min_qty_reseller' => 60,
        ]);
        BusinessConfig::put('partnership', [
            'spread_payment_percentage' => 2,
        ]);
        $this->purchase($reseller, $agent, 50, '2026-07-10', 'PO-RSL-CONFIG');

        $this->assertSame(0, Artisan::call('rewards:close-month', ['--period' => '2026-07']));

        $this->assertDatabaseHas('reward_point_monthly', [
            'reward_point_monthly_member_id' => $reseller->getKey(),
            'reward_point_monthly_total_qty' => 50,
            'reward_point_monthly_bonus_value' => 0,
        ]);
        $this->assertDatabaseHas('trx_spread_payment', [
            'trx_spread_payment_upline_id' => $agent->getKey(),
            'trx_spread_payment_member_id' => $reseller->getKey(),
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
