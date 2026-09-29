<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberBankAccount;
use App\Models\MemberGroup;
use App\Models\MemberLevel;
use App\Models\MemberPointTransaction;
use App\Models\RewardPointMonthly;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberMonthlyRewardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_member_monthly_reward_requires_authentication(): void
    {
        $this->getJson('/api/v1/member/rewards/monthly')->assertUnauthorized();
        $this->getJson('/api/v1/member/rewards/monthly/downlines')->assertUnauthorized();
        $this->getJson('/api/v1/member/rewards/monthly/growth')->assertUnauthorized();
        $this->postJson('/api/v1/member/rewards/monthly/downlines/1/approve')->assertUnauthorized();
    }

    public function test_member_can_view_monthly_rewards(): void
    {
        $account = $this->createMemberAccount('distributor');
        $this->actingAs($account, 'member_api');
        $memberId = $account->member_account_member_id;

        RewardPointMonthly::query()->create([
            'reward_point_monthly_member_id' => $memberId,
            'reward_point_monthly_member_level_id' => 1,
            'reward_point_monthly_year' => 2024,
            'reward_point_monthly_month' => 5,
            'reward_point_monthly_total_qty' => 100,
            'reward_point_monthly_bonus_value' => 500000,
            'reward_point_monthly_is_processed' => 1,
            'reward_point_monthly_processed_datetime' => now(),
        ]);

        RewardPointMonthly::query()->create([
            'reward_point_monthly_member_id' => $memberId,
            'reward_point_monthly_member_level_id' => 1,
            'reward_point_monthly_year' => 2024,
            'reward_point_monthly_month' => 6,
            'reward_point_monthly_total_qty' => 50,
            'reward_point_monthly_bonus_value' => 250000,
            'reward_point_monthly_is_processed' => 0,
        ]);

        RewardPointMonthly::query()->create([
            'reward_point_monthly_member_id' => $memberId,
            'reward_point_monthly_member_level_id' => 1,
            'reward_point_monthly_year' => 2023,
            'reward_point_monthly_month' => 12,
            'reward_point_monthly_total_qty' => 20,
            'reward_point_monthly_bonus_value' => 100000,
            'reward_point_monthly_is_processed' => 1,
            'reward_point_monthly_processed_datetime' => now(),
        ]);

        // Reward for other member
        RewardPointMonthly::query()->create([
            'reward_point_monthly_member_id' => 999,
            'reward_point_monthly_member_level_id' => 1,
            'reward_point_monthly_year' => 2024,
            'reward_point_monthly_month' => 6,
            'reward_point_monthly_total_qty' => 100,
            'reward_point_monthly_bonus_value' => 500000,
        ]);

        $this->getJson('/api/v1/member/rewards/monthly?year=2024')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.results')
            ->assertJsonPath('summary.total_akumulasi', 750000)
            ->assertJsonPath('summary.total_dibayarkan', 500000)
            ->assertJsonPath('summary.total_belum_dibayarkan', 250000)
            ->assertJsonPath('summary_total.total_akumulasi', 850000)
            ->assertJsonPath('summary_total.total_dibayarkan', 600000)
            ->assertJsonPath('summary_total.total_belum_dibayarkan', 250000)
            ->assertJsonPath('available_years.0', 2024)
            ->assertJsonPath('available_years.1', 2023);
    }

    public function test_member_monthly_reward_shows_the_sponsor_responsible_for_payment(): void
    {
        $sponsor = $this->createMember('distributor');
        $member = $this->createMember('agent', $sponsor);
        $account = $this->createAccount($member);
        $reward = RewardPointMonthly::query()->create([
            'reward_point_monthly_upline_id' => $sponsor->getKey(),
            'reward_point_monthly_upline_level_id' => $sponsor->member_member_level_id,
            'reward_point_monthly_member_id' => $member->getKey(),
            'reward_point_monthly_member_level_id' => $member->member_member_level_id,
            'reward_point_monthly_year' => 2026,
            'reward_point_monthly_month' => 8,
            'reward_point_monthly_total_qty' => 60,
            'reward_point_monthly_bonus_value' => 300000,
            'reward_point_monthly_is_processed' => 0,
        ]);
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/rewards/monthly')
            ->assertOk()
            ->assertJsonPath('data.results.0.id', $reward->getKey())
            ->assertJsonPath('data.results.0.payment_responsibility.type', 'sponsor')
            ->assertJsonPath('data.results.0.payment_responsibility.responsible_sponsor.id', $sponsor->getKey())
            ->assertJsonPath('data.results.0.payment_responsibility.responsible_sponsor.level.code', 'DST');

        $this->getJson("/api/v1/member/rewards/monthly/{$reward->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.payment_responsibility.responsible_sponsor.code', $sponsor->member_code);
    }

    public function test_member_can_view_monthly_reward_growth_summary(): void
    {
        $account = $this->createMemberAccount('distributor');
        $this->actingAs($account, 'member_api');
        $memberId = $account->member_account_member_id;

        foreach ([
            5 => [100, 500000],
            6 => [120, 600000],
            7 => [150, 900000],
        ] as $month => [$points, $reward]) {
            RewardPointMonthly::query()->create([
                'reward_point_monthly_member_id' => $memberId,
                'reward_point_monthly_member_level_id' => 1,
                'reward_point_monthly_year' => 2024,
                'reward_point_monthly_month' => $month,
                'reward_point_monthly_total_qty' => $points,
                'reward_point_monthly_bonus_value' => $reward,
                'reward_point_monthly_is_processed' => 1,
                'reward_point_monthly_processed_datetime' => now(),
            ]);
        }

        RewardPointMonthly::query()->create([
            'reward_point_monthly_member_id' => 999,
            'reward_point_monthly_member_level_id' => 1,
            'reward_point_monthly_year' => 2024,
            'reward_point_monthly_month' => 7,
            'reward_point_monthly_total_qty' => 999,
            'reward_point_monthly_bonus_value' => 999999,
        ]);

        $this->getJson('/api/v1/member/rewards/monthly/growth')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period.year', 2024)
            ->assertJsonPath('data.period.month', 7)
            ->assertJsonPath('data.period.range_label', 'Januari–Juli 2024')
            ->assertJsonPath('data.growth.percent', 50)
            ->assertJsonPath('data.growth.status', 'up')
            ->assertJsonPath('data.growth.label', 'Naik')
            ->assertJsonPath('data.series.4.month', 5)
            ->assertJsonPath('data.series.4.reward_value', 500000)
            ->assertJsonPath('data.series.6.month', 7)
            ->assertJsonPath('data.series.6.reward_value', 900000);

        $this->getJson('/api/v1/member/rewards/monthly/growth?year=2024')
            ->assertOk()
            ->assertJsonPath('data.period.year', 2024)
            ->assertJsonPath('data.period.month', 7)
            ->assertJsonPath('data.summary.monthly_reward', 900000)
            ->assertJsonPath('data.summary.total_points', 150)
            ->assertJsonPath('data.summary.member_level.code', 'DST')
            ->assertJsonPath('data.summary.minimum_points', 50)
            ->assertJsonPath('data.summary.remaining_points', 0)
            ->assertJsonPath('data.summary.is_qualified', true)
            ->assertJsonPath('data.summary.source', 'snapshot')
            ->assertJsonPath('data.summary.is_realtime', false);
    }

    public function test_monthly_reward_growth_card_uses_realtime_current_month_points(): void
    {
        $account = $this->createMemberAccount('distributor');
        $this->actingAs($account, 'member_api');
        $memberId = $account->member_account_member_id;

        RewardPointMonthly::query()->create([
            'reward_point_monthly_member_id' => $memberId,
            'reward_point_monthly_member_level_id' => 1,
            'reward_point_monthly_year' => now()->subMonth()->year,
            'reward_point_monthly_month' => now()->subMonth()->month,
            'reward_point_monthly_total_qty' => 100,
            'reward_point_monthly_bonus_value' => 500000,
            'reward_point_monthly_is_processed' => 1,
            'reward_point_monthly_processed_datetime' => now()->subMonth(),
        ]);

        MemberPointTransaction::query()->create([
            'member_point_transaction_member_id' => $memberId,
            'member_point_transaction_trx_id' => 1001,
            'member_point_transaction_quantity' => 55,
            'member_point_transaction_year' => now()->year,
            'member_point_transaction_month' => now()->month,
            'member_point_transaction_approved_datetime' => now(),
        ]);

        $this->getJson('/api/v1/member/rewards/monthly/growth')
            ->assertOk()
            ->assertJsonPath('data.summary.year', now()->year)
            ->assertJsonPath('data.summary.month', now()->month)
            ->assertJsonPath('data.summary.total_points', 55)
            ->assertJsonPath('data.summary.monthly_reward', 275000)
            ->assertJsonPath('data.summary.member_level.code', 'DST')
            ->assertJsonPath('data.summary.minimum_points', 50)
            ->assertJsonPath('data.summary.remaining_points', 0)
            ->assertJsonPath('data.summary.is_qualified', true)
            ->assertJsonPath('data.summary.source', 'realtime')
            ->assertJsonPath('data.summary.is_realtime', true);
    }

    public function test_monthly_reward_growth_card_exposes_minimum_points_progress(): void
    {
        $account = $this->createMemberAccount('agent');
        $this->actingAs($account, 'member_api');

        MemberPointTransaction::query()->create([
            'member_point_transaction_member_id' => $account->member_account_member_id,
            'member_point_transaction_trx_id' => 1002,
            'member_point_transaction_quantity' => 22,
            'member_point_transaction_year' => now()->year,
            'member_point_transaction_month' => now()->month,
            'member_point_transaction_approved_datetime' => now(),
        ]);

        $this->getJson('/api/v1/member/rewards/monthly/growth')
            ->assertOk()
            ->assertJsonPath('data.summary.total_points', 22)
            ->assertJsonPath('data.summary.monthly_reward', 0)
            ->assertJsonPath('data.summary.member_level.code', 'AGT')
            ->assertJsonPath('data.summary.minimum_points', 30)
            ->assertJsonPath('data.summary.remaining_points', 8)
            ->assertJsonPath('data.summary.is_qualified', false);
    }

    public function test_downline_report_uses_period_upline_snapshot_instead_of_current_parent(): void
    {
        $distributorAccount = $this->createMemberAccount('distributor');
        $otherDistributor = $this->createMember('distributor');
        $replacementAgent = $this->createMember('agent', $otherDistributor);
        $historicalAgent = $this->createMember(
            'agent',
            Member::query()->findOrFail($distributorAccount->member_account_member_id),
        );
        $historicalAgent->update([
            'member_member_level_id' => 3,
            'member_parent_member_id' => $replacementAgent->getKey(),
        ]);
        DB::table('ref_bank')->insert([
            'bank_id' => 1,
            'bank_code' => 'BCA',
            'bank_name' => 'Bank Central Asia',
        ]);
        MemberBankAccount::query()->create([
            'member_bank_account_member_id' => $historicalAgent->getKey(),
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Historical Agent',
            'member_bank_account_number' => '9876543210',
            'member_bank_account_is_active' => 1,
            'member_bank_account_is_default' => 1,
        ]);

        $agentReward = RewardPointMonthly::query()->create([
            'reward_point_monthly_upline_id' => $distributorAccount->member_account_member_id,
            'reward_point_monthly_upline_level_id' => 1,
            'reward_point_monthly_member_id' => $historicalAgent->getKey(),
            'reward_point_monthly_member_level_id' => 2,
            'reward_point_monthly_year' => 2024,
            'reward_point_monthly_month' => 6,
            'reward_point_monthly_total_qty' => 180,
            'reward_point_monthly_bonus_value' => 720000,
            'reward_point_monthly_is_processed' => 0,
        ]);
        RewardPointMonthly::query()->create([
            'reward_point_monthly_upline_id' => $replacementAgent->getKey(),
            'reward_point_monthly_upline_level_id' => 2,
            'reward_point_monthly_member_id' => $historicalAgent->getKey(),
            'reward_point_monthly_member_level_id' => 3,
            'reward_point_monthly_year' => 2024,
            'reward_point_monthly_month' => 7,
            'reward_point_monthly_total_qty' => 70,
            'reward_point_monthly_bonus_value' => 210000,
            'reward_point_monthly_is_processed' => 0,
        ]);

        $this->actingAs($distributorAccount, 'member_api');

        $this->getJson('/api/v1/member/rewards/monthly/downlines?year=2024&month=6')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $agentReward->getKey())
            ->assertJsonPath('data.results.0.member.id', $historicalAgent->getKey())
            ->assertJsonPath('data.results.0.member.level.code', 'AGT')
            ->assertJsonPath('data.results.0.bank.name', 'Bank Central Asia')
            ->assertJsonPath('data.results.0.bank.account_number', '9876543210')
            ->assertJsonPath('summary.total_akumulasi', 720000)
            ->assertJsonPath('summary.total_dibayarkan', 0)
            ->assertJsonPath('summary.total_belum_dibayarkan', 720000)
            ->assertJsonPath('action_count', 1);

        $agentAccount = $this->createAccount($replacementAgent);
        $this->actingAs($agentAccount, 'member_api');

        $this->getJson('/api/v1/member/rewards/monthly/downlines?year=2024&month=7')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.member.id', $historicalAgent->getKey())
            ->assertJsonPath('data.results.0.member.level.code', 'RSL');
    }

    public function test_reseller_cannot_access_downline_monthly_reward_report(): void
    {
        $this->actingAs($this->createMemberAccount('reseller'), 'member_api');

        $this->getJson('/api/v1/member/rewards/monthly/downlines')->assertForbidden();
    }

    public function test_upline_can_approve_qualified_direct_downline_monthly_reward_once(): void
    {
        $uplineAccount = $this->createMemberAccount('distributor');
        $upline = Member::query()->findOrFail($uplineAccount->member_account_member_id);
        $downline = $this->createMember('agent', $upline);
        DB::table('ref_bank')->insert([
            'bank_id' => 1,
            'bank_code' => 'BCA',
            'bank_name' => 'Bank Central Asia',
        ]);
        MemberBankAccount::query()->create([
            'member_bank_account_member_id' => $downline->getKey(),
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Test Member',
            'member_bank_account_number' => '1234567890',
            'member_bank_account_is_active' => 1,
            'member_bank_account_is_default' => 1,
        ]);
        $reward = RewardPointMonthly::query()->create([
            'reward_point_monthly_upline_id' => $upline->getKey(),
            'reward_point_monthly_upline_level_id' => $upline->member_member_level_id,
            'reward_point_monthly_member_id' => $downline->getKey(),
            'reward_point_monthly_member_level_id' => $downline->member_member_level_id,
            'reward_point_monthly_year' => 2026,
            'reward_point_monthly_month' => 8,
            'reward_point_monthly_total_qty' => 35,
            'reward_point_monthly_bonus_value' => 140000,
            'reward_point_monthly_is_processed' => 0,
        ]);

        $this->actingAs($uplineAccount, 'member_api');
        $endpoint = "/api/v1/member/rewards/monthly/downlines/{$reward->getKey()}/approve";

        $this->postJson($endpoint)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member.id', $downline->getKey())
            ->assertJsonPath('data.bank.name', 'Bank Central Asia')
            ->assertJsonPath('data.bank.account_number', '1234567890')
            ->assertJsonPath('data.is_processed', true);

        $this->assertTrue($reward->refresh()->reward_point_monthly_is_processed);
        $this->assertNotNull($reward->reward_point_monthly_processed_datetime);

        $this->postJson($endpoint)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Reward bulanan ini sudah disetujui sebelumnya.');
    }

    public function test_member_cannot_approve_another_uplines_or_unqualified_reward(): void
    {
        $account = $this->createMemberAccount('agent');
        $otherUpline = $this->createMember('agent');
        $downline = $this->createMember('reseller', $otherUpline);
        $otherReward = RewardPointMonthly::query()->create([
            'reward_point_monthly_upline_id' => $otherUpline->getKey(),
            'reward_point_monthly_upline_level_id' => $otherUpline->member_member_level_id,
            'reward_point_monthly_member_id' => $downline->getKey(),
            'reward_point_monthly_member_level_id' => $downline->member_member_level_id,
            'reward_point_monthly_year' => 2026,
            'reward_point_monthly_month' => 8,
            'reward_point_monthly_total_qty' => 25,
            'reward_point_monthly_bonus_value' => 75000,
        ]);
        $ownDownline = $this->createMember(
            'reseller',
            Member::query()->findOrFail($account->member_account_member_id),
        );
        $unqualifiedReward = RewardPointMonthly::query()->create([
            'reward_point_monthly_upline_id' => $account->member_account_member_id,
            'reward_point_monthly_upline_level_id' => 2,
            'reward_point_monthly_member_id' => $ownDownline->getKey(),
            'reward_point_monthly_member_level_id' => $ownDownline->member_member_level_id,
            'reward_point_monthly_year' => 2026,
            'reward_point_monthly_month' => 8,
            'reward_point_monthly_total_qty' => 10,
            'reward_point_monthly_bonus_value' => 0,
        ]);

        $this->actingAs($account, 'member_api');

        $this->postJson("/api/v1/member/rewards/monthly/downlines/{$otherReward->getKey()}/approve")
            ->assertNotFound();
        $this->postJson("/api/v1/member/rewards/monthly/downlines/{$unqualifiedReward->getKey()}/approve")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Reward tidak terkualifikasi dan tidak perlu dibayarkan.');
    }

    public function test_member_can_view_monthly_reward_detail(): void
    {
        $account = $this->createMemberAccount('distributor');
        $this->actingAs($account, 'member_api');
        $memberId = $account->member_account_member_id;

        $reward = RewardPointMonthly::query()->create([
            'reward_point_monthly_member_id' => $memberId,
            'reward_point_monthly_member_level_id' => 1,
            'reward_point_monthly_year' => 2024,
            'reward_point_monthly_month' => 5,
            'reward_point_monthly_total_qty' => 100,
            'reward_point_monthly_bonus_value' => 500000,
            'reward_point_monthly_is_processed' => 1,
        ]);

        $this->getJson("/api/v1/member/rewards/monthly/{$reward->reward_point_monthly_id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.year', 2024)
            ->assertJsonPath('data.month', 5);
    }

    public function test_member_cannot_view_others_monthly_reward(): void
    {
        $account = $this->createMemberAccount('distributor');
        $this->actingAs($account, 'member_api');

        $reward = RewardPointMonthly::query()->create([
            'reward_point_monthly_member_id' => 999,
            'reward_point_monthly_member_level_id' => 1,
            'reward_point_monthly_year' => 2024,
            'reward_point_monthly_month' => 5,
            'reward_point_monthly_total_qty' => 100,
        ]);

        $this->getJson("/api/v1/member/rewards/monthly/{$reward->reward_point_monthly_id}")
            ->assertNotFound();
    }

    private function createMemberAccount(string $level): MemberAccount
    {
        return $this->createAccount($this->createMember($level));
    }

    private function createMember(string $level, ?Member $parent = null): Member
    {
        $levelId = match ($level) {
            'distributor' => 1,
            'agent' => 2,
            'reseller' => 3,
        };
        $levelCode = match ($level) {
            'distributor' => 'DST',
            'agent' => 'AGT',
            'reseller' => 'RSL',
        };
        MemberLevel::query()->updateOrCreate(
            ['member_level_id' => $levelId],
            [
                'member_level_code' => $levelCode,
                'member_level_name' => ucfirst($level),
                'member_level_description' => ucfirst($level),
                'member_level_min_order' => 0,
                'member_level_point_value' => match ($level) {
                    'distributor' => 5000,
                    'agent' => 4000,
                    'reseller' => 3000,
                },
                'member_level_sort_order' => $levelId,
                'member_level_is_active' => 1,
            ],
        );

        return Member::query()->create([
            'member_code' => 'MEMBER-'.strtoupper($level).rand(100, 999),
            'member_member_level_id' => $levelId,
            'member_parent_member_id' => $parent?->getKey() ?? 0,
            'member_name' => 'Test Member',
            'member_email' => "{$level}".rand(100, 999).'@example.test',
            'member_mobilephone' => '08123456789',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
    }

    private function createAccount(Member $member): MemberAccount
    {
        $levelName = $member->level?->member_level_name ?? 'Member';
        $group = MemberGroup::query()->updateOrCreate([
            'member_group_name' => $levelName,
        ], [
            'member_group_description' => 'Test group',
            'member_group_is_active' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->member_id,
            'member_account_member_group_id' => $group->member_group_id,
            'member_account_username' => 'test.'.strtolower($levelName).rand(100, 999),
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
    }
}
