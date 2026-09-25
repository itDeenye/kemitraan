<?php

namespace Tests\Feature\Database;

use App\Models\BankCompany;
use App\Models\Member;
use App\Models\MemberLevel;
use App\Models\Product;
use App\Models\RewardPointMonthly;
use App\Models\RewardStockist;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Models\Stockist;
use App\Models\Trx;
use App\Models\TrxSpreadPayment;
use App\Models\Warehouse;
use App\Services\Reward\AdminMonthlyRewardService;
use App\Services\Reward\MemberMonthlyRewardService;
use App\Services\Reward\MemberSharingProfitService;
use App\Services\Reward\MemberStockistRewardService;
use App\Support\BusinessConfig;
use Database\Seeders\DevelopmentRewardScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Tests\TestCase;

class DevelopmentRewardScenarioSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 18)->setTime(10, 0));
        Http::preventStrayRequests();
        Mail::fake();
    }

    public function test_one_id_creates_all_four_examples_with_a_distributor_downline_and_dummy_transactions(): void
    {
        $member = $this->member();
        $admin = $this->masterData();

        $this->runCommand($member);

        $downline = $member->downlines()->with('level')->sole();
        $this->assertSame('DST', $downline->level->member_level_code);
        $this->assertStringStartsWith('DUMMY', $downline->member_name);
        $this->assertDatabaseCount('member_account', 0);
        $this->assertDatabaseCount('trx', 3);
        $this->assertDatabaseCount('trx_detail', 3);
        $this->assertDatabaseCount('trx_payment_transfer', 3);
        $this->assertDatabaseCount('reward_point_monthly', 1);
        $this->assertDatabaseCount('reward_stockist', 1);
        $this->assertDatabaseCount('trx_spread_payment', 2);
        $this->assertDatabaseCount('member_stock', 0);
        $this->assertDatabaseCount('warehouse_stock', 0);
        $this->assertDatabaseCount('member_achievement', 0);
        $this->assertDatabaseCount('member_upgrade_qualified', 0);

        $reward = RewardPointMonthly::query()->sole();
        $this->assertSame(500, $reward->reward_point_monthly_total_qty);
        $this->assertSame(2_500_000, $reward->reward_point_monthly_bonus_value);
        $this->assertFalse($reward->reward_point_monthly_is_processed);
        $voucher = RewardStockist::query()->sole();
        $this->assertSame(50_000_000, $voucher->reward_stockist_total_trx_amount);
        $this->assertSame(1_250_000, $voucher->reward_stockist_bonus_value);
        $this->assertSame('2026-09-30', $voucher->reward_stockist_expiry_date->toDateString());
        $this->assertSame(Stockist::query()->sole()->getKey(), $member->fresh()->member_stockist_id);
        $this->assertFalse(Product::query()->sole()->product_is_publish);

        foreach (TrxSpreadPayment::query()->with('trx.paymentTransfer')->get() as $spread) {
            $this->assertSame($member->getKey(), $spread->trx_spread_payment_upline_id);
            $this->assertSame($downline->getKey(), $spread->trx_spread_payment_member_id);
            $this->assertSame('warehouse', $spread->trx->trx_seller_type);
            $this->assertSame('distributor', $spread->trx->trx_buyer_type);
            $this->assertSame(10_000, $spread->trx_spread_payment_amount);
            $this->assertSame('approved', $spread->trx->paymentTransfer->payment_transfer_approval_status);
            $this->assertFileExists(public_path(ltrim($spread->trx_spread_payment_receipt_file, '/')));
        }

        $this->actingAs($admin, 'admin_api');
        $this->getJson('/api/v1/admin/rewards/sharing-profits')->assertOk()
            ->assertJsonPath('data.results.0.upline.id', $member->getKey())
            ->assertJsonPath('data.results.0.can_approve', true)
            ->assertJsonPath('data.results.0.can_transfer', false);
        $this->getJson('/api/v1/admin/rewards/sharing-profits/'.$member->getKey())->assertOk()
            ->assertJsonPath('data.results.0.receipt_url', '/images/development/reward-receipt.svg');
        $this->getJson('/api/v1/admin/rewards/sharing-profits/history')->assertOk()->assertJsonCount(1, 'data.results');
        $this->postJson('/api/v1/admin/rewards/sharing-profits/approve', ['upline_ids' => [$member->getKey()]])->assertOk();
        $this->getJson('/api/v1/admin/rewards/sharing-profits')->assertOk()->assertJsonPath('data.results.0.can_transfer', true);
        $this->postJson('/api/v1/admin/rewards/sharing-profits/transfer', ['upline_ids' => [$member->getKey()], 'note' => 'DUMMY uji transfer'])->assertOk();
        $this->getJson('/api/v1/admin/rewards/sharing-profits/history')->assertOk()->assertJsonCount(2, 'data.results');

        $this->assertCount(1, app(AdminMonthlyRewardService::class)->monthlyRewards([])['results']);
        $this->assertCount(1, app(AdminMonthlyRewardService::class)->stockistRewards([])['results']);
        $this->assertCount(1, app(MemberMonthlyRewardService::class)->monthlyRewards($member->getKey(), [])['results']);
        $this->assertCount(1, app(MemberStockistRewardService::class)->stockistRewards($member->getKey(), [])['results']);
        $this->assertCount(2, app(MemberSharingProfitService::class)->sharingProfits($member->getKey(), [])['results']);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_rerunning_preserves_processed_examples_and_reuses_existing_direct_distributor(): void
    {
        $member = $this->member();
        $downline = $this->member('DST', $member);
        $this->masterData();
        $this->runCommand($member);
        RewardPointMonthly::query()->firstOrFail()->update(['reward_point_monthly_is_processed' => 1]);
        RewardStockist::query()->firstOrFail()->update(['reward_stockist_used_value' => 100, 'reward_stockist_used_trx_id' => Trx::query()->firstOrFail()->getKey()]);
        TrxSpreadPayment::query()->where('trx_spread_payment_status', 'submitted')->update(['trx_spread_payment_status' => 'paid', 'trx_spread_payment_note' => 'Sudah dicoba']);

        $this->runCommand($member);

        $this->assertDatabaseCount('member', 2);
        $this->assertDatabaseCount('trx', 3);
        $this->assertDatabaseCount('trx_detail', 3);
        $this->assertDatabaseCount('stockist', 1);
        $this->assertDatabaseCount('reward_point_monthly', 1);
        $this->assertDatabaseCount('reward_stockist', 1);
        $this->assertDatabaseCount('trx_spread_payment', 2);
        $this->assertTrue(RewardPointMonthly::query()->sole()->reward_point_monthly_is_processed);
        $this->assertSame(100, RewardStockist::query()->sole()->reward_stockist_used_value);
        $this->assertSame(2, TrxSpreadPayment::query()->where('trx_spread_payment_status', 'paid')->count());
        $this->assertSame([$downline->getKey()], TrxSpreadPayment::query()->distinct()->pluck('trx_spread_payment_member_id')->all());
        $this->assertDatabaseHas('trx_spread_payment', ['trx_spread_payment_note' => 'Sudah dicoba']);
    }

    public function test_existing_rewards_are_not_overwritten_and_do_not_generate_extra_purchases(): void
    {
        $member = $this->member();
        RewardPointMonthly::query()->create([
            'reward_point_monthly_member_id' => $member->getKey(), 'reward_point_monthly_member_level_id' => $member->member_member_level_id,
            'reward_point_monthly_upline_id' => 0, 'reward_point_monthly_upline_level_id' => 0,
            'reward_point_monthly_year' => 2026, 'reward_point_monthly_month' => 8,
            'reward_point_monthly_total_qty' => 99, 'reward_point_monthly_bonus_value' => 495_000,
            'reward_point_monthly_is_processed' => 1,
        ]);

        $this->runCommand($member, ['--only' => 'monthly']);

        $this->assertDatabaseCount('trx', 0);
        $this->assertSame(99, RewardPointMonthly::query()->sole()->reward_point_monthly_total_qty);
    }

    public function test_monthly_for_reseller_keeps_its_upline_as_payer_and_skips_distributor_features(): void
    {
        $distributor = $this->member();
        $agent = $this->member('AGT', $distributor);
        $reseller = $this->member('RSL', $agent);
        $this->masterData();
        BusinessConfig::put('reward_monthly', ['reward_monthly_min_qty_reseller' => 35]);

        $this->runCommand($reseller);

        $reward = RewardPointMonthly::query()->sole();
        $this->assertSame($agent->getKey(), $reward->reward_point_monthly_upline_id);
        $this->assertSame(35, $reward->reward_point_monthly_total_qty);
        $this->assertSame('agent', Trx::query()->sole()->trx_seller_type);
        $this->assertDatabaseCount('stockist', 0);
        $this->assertDatabaseCount('trx_spread_payment', 0);
        $this->assertDatabaseCount('member', 3);
    }

    public function test_custom_period_and_configured_percentages_are_used(): void
    {
        $member = $this->member();
        $this->masterData();
        BusinessConfig::put('reward_monthly', ['reward_monthly_min_qty_distributor' => 90]);
        BusinessConfig::put('reward_stockist', ['reward_stockist_min_amount' => 12_345_000, 'reward_stockist_percentage_basis_points' => 375]);
        BusinessConfig::put('partnership', ['spread_payment_percentage' => 1.25]);

        $this->runCommand($member, ['--period' => '2025-12']);

        $this->assertSame(124, RewardPointMonthly::query()->sole()->reward_point_monthly_total_qty);
        $this->assertSame(465_000, RewardStockist::query()->sole()->reward_stockist_bonus_value);
        $this->assertSame('2026-01-31', RewardStockist::query()->sole()->reward_stockist_expiry_date->toDateString());
        $this->assertSame(12_500, TrxSpreadPayment::query()->firstOrFail()->trx_spread_payment_amount);
        $this->assertSame(2025, RewardPointMonthly::query()->sole()->reward_point_monthly_year);
    }

    public function test_sharing_and_history_can_be_seeded_separately_without_other_rewards(): void
    {
        $member = $this->member();
        $this->masterData();
        $this->runCommand($member, ['--only' => 'sharing']);
        $this->assertSame('submitted', TrxSpreadPayment::query()->sole()->trx_spread_payment_status);
        $this->runCommand($member, ['--only' => 'history']);
        $this->assertDatabaseCount('trx_spread_payment', 2);
        $this->assertDatabaseCount('member', 2);
        $this->assertDatabaseCount('reward_point_monthly', 0);
        $this->assertDatabaseCount('reward_stockist', 0);
    }

    public function test_failure_rolls_back_all_dummy_changes(): void
    {
        $member = $this->member();
        $this->masterData();
        BankCompany::query()->delete();
        $this->assertSame(1, Artisan::call('development:seed-rewards', ['member_id' => $member->getKey()]));
        $this->assertStringContainsString('spread_payment', Artisan::output());
        $this->assertDatabaseCount('trx', 0);
        $this->assertDatabaseCount('product', 0);
        $this->assertDatabaseCount('stockist', 0);
        $this->assertDatabaseCount('reward_point_monthly', 0);
        $this->assertDatabaseCount('reward_stockist', 0);
        $this->assertDatabaseCount('member', 1);
        $this->assertNull($member->fresh()->member_stockist_id);
    }

    public function test_stockist_only_creates_an_available_voucher_without_other_examples(): void
    {
        $member = $this->member();
        $this->masterData();
        $this->runCommand($member, ['--only' => 'stockist']);
        $this->runCommand($member, ['--only' => 'stockist']);

        $this->assertDatabaseCount('reward_point_monthly', 0);
        $this->assertDatabaseCount('trx_spread_payment', 0);
        $this->assertDatabaseCount('member', 1);
        $this->assertDatabaseCount('trx', 1);
        $this->assertDatabaseCount('reward_stockist', 1);
        $this->assertSame(0, RewardStockist::query()->sole()->reward_stockist_used_value);
    }

    public function test_inactive_stockist_is_not_reactivated_and_other_changes_are_rolled_back(): void
    {
        $member = $this->member();
        $this->masterData();
        Stockist::query()->create([
            'stockist_member_id' => $member->getKey(), 'stockist_name' => 'Stokis Nonaktif',
            'stockist_note' => 'Jangan diubah', 'stockist_is_active' => 0,
            'stockist_input_datetime' => now(),
        ]);

        $this->assertSame(1, Artisan::call('development:seed-rewards', ['member_id' => $member->getKey()]));
        $this->assertStringContainsString('Stokis mitra ini tidak aktif', Artisan::output());
        $this->assertDatabaseHas('stockist', ['stockist_note' => 'Jangan diubah', 'stockist_is_active' => 0]);
        $this->assertDatabaseCount('trx', 0);
        $this->assertDatabaseCount('reward_point_monthly', 0);
    }

    public function test_unconfigured_point_value_does_not_create_a_zero_value_monthly_example(): void
    {
        $member = $this->member();
        $member->level->update(['member_level_point_value' => 0]);
        $this->masterData();

        $this->assertSame(1, Artisan::call('development:seed-rewards', ['member_id' => $member->getKey(), '--only' => 'monthly']));
        $this->assertStringContainsString('Nilai poin level belum diatur', Artisan::output());
        $this->assertDatabaseCount('trx', 0);
        $this->assertDatabaseCount('reward_point_monthly', 0);
    }

    public function test_invalid_arguments_missing_member_and_inactive_member_do_not_write_data(): void
    {
        foreach ([['member_id' => 'abc'], ['member_id' => 0], ['member_id' => 999], ['member_id' => 1, '--only' => 'unknown'], ['member_id' => 1, '--period' => '2026-13'], ['member_id' => 1, '--period' => '2026-09'], ['member_id' => 1, '--period' => '2027-01']] as $arguments) {
            $this->assertNotSame(0, Artisan::call('development:seed-rewards', $arguments));
        }
        $member = $this->member();
        $member->update(['member_status' => 0]);
        $this->assertSame(1, Artisan::call('development:seed-rewards', ['member_id' => $member->getKey()]));
        $this->assertDatabaseCount('trx', 0);
    }

    public function test_non_distributor_cannot_request_stockist_or_sharing_examples(): void
    {
        $member = $this->member('AGT', $this->member());
        foreach (['stockist', 'sharing', 'history'] as $only) {
            $this->assertSame(1, Artisan::call('development:seed-rewards', ['member_id' => $member->getKey(), '--only' => $only]));
            $this->assertStringContainsString('khusus Distributor', Artisan::output());
        }
        $this->assertDatabaseCount('trx', 0);
    }

    public function test_production_is_blocked_even_when_calling_the_seeder_directly(): void
    {
        app()->detectEnvironment(fn (): string => 'production');
        try {
            $this->assertSame(1, Artisan::call('development:seed-rewards', ['member_id' => 1]));
            $this->expectException(LogicException::class);
            app(DevelopmentRewardScenarioSeeder::class)->run(1);
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }
    }

    /** @param array<string, string> $options */
    private function runCommand(Member $member, array $options = []): void
    {
        $exitCode = Artisan::call('development:seed-rewards', ['member_id' => $member->getKey(), ...$options]);
        $this->assertSame(0, $exitCode, Artisan::output());
    }

    private function member(string $code = 'DST', ?Member $parent = null): Member
    {
        $level = MemberLevel::query()->updateOrCreate(['member_level_code' => $code], [
            'member_level_name' => match ($code) {
                'DST' => 'Distributor', 'AGT' => 'Agent', 'RSL' => 'Reseller'
            },
            'member_level_point_value' => 5_000, 'member_level_is_active' => 1,
        ]);

        return Member::query()->create([
            'member_code' => sprintf('%04d/0000/0000', Member::query()->count() + 1),
            'member_name' => 'Mitra Pengujian',
            'member_member_level_id' => $level->getKey(),
            'member_parent_member_id' => $parent?->getKey() ?? 0,
            'member_status' => 1,
            'member_join_datetime' => now()->subYear(),
        ]);
    }

    private function masterData(): SiteAdministrator
    {
        Warehouse::query()->create(['warehouse_name' => 'Gudang Test', 'warehouse_is_active' => 1]);
        BankCompany::query()->create([
            'bank_company_type' => 'spread_payment', 'bank_company_bank_id' => 1,
            'bank_company_bank_acc_name' => 'Rekening Test', 'bank_company_bank_acc_number' => '1234567890',
            'bank_company_bank_is_active' => 1,
        ]);
        $group = SiteAdministratorGroup::query()->create(['administrator_group_title' => 'Super Admin', 'administrator_group_is_active' => 1]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->getKey(),
            'administrator_username' => 'admin.reward.test', 'administrator_password' => Hash::make('test-password'),
            'administrator_name' => 'Admin Reward Test', 'administrator_email' => 'admin@example.test',
            'administrator_image' => '', 'administrator_is_active' => 1,
        ]);
    }
}
