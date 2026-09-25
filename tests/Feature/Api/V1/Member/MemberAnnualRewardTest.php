<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\RewardPointAnnual;
use App\Models\RewardPointAnnualLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberAnnualRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_annual_reward_requires_authentication(): void
    {
        $this->getJson('/api/v1/member/rewards/annual')->assertUnauthorized();
    }

    public function test_member_can_view_annual_reward_summary(): void
    {
        $account = $this->createMemberAccount('distributor');
        $this->actingAs($account, 'member_api');
        $memberId = $account->member_account_member_id;

        RewardPointAnnual::query()->create([
            'reward_point_annual_member_id' => $memberId,
            'reward_point_annual_year' => 2024,
            'reward_point_annual_total_points' => 300,
        ]);

        RewardPointAnnualLog::query()->create([
            'reward_point_annual_log_member_id' => $memberId,
            'reward_point_annual_log_type' => 'in',
            'reward_point_annual_log_points' => 100,
            'reward_point_annual_log_note' => 'Poin bulan 1',
            'reward_point_annual_log_datetime' => '2024-01-15 10:00:00',
        ]);

        RewardPointAnnualLog::query()->create([
            'reward_point_annual_log_member_id' => $memberId,
            'reward_point_annual_log_type' => 'in',
            'reward_point_annual_log_points' => 200,
            'reward_point_annual_log_note' => 'Poin bulan 2',
            'reward_point_annual_log_datetime' => '2024-02-15 10:00:00',
        ]);

        // Reward log for another year should not be included if filtered by 2024
        RewardPointAnnualLog::query()->create([
            'reward_point_annual_log_member_id' => $memberId,
            'reward_point_annual_log_type' => 'in',
            'reward_point_annual_log_points' => 500,
            'reward_point_annual_log_note' => 'Poin bulan 1 tahun lalu',
            'reward_point_annual_log_datetime' => '2023-01-15 10:00:00',
        ]);

        $this->getJson('/api/v1/member/rewards/annual?year=2024')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.year', 2024)
            ->assertJsonPath('data.total_points', 300)
            ->assertJsonPath('available_years.0', 2024)
            ->assertJsonPath('available_years.1', 2023)
            ->assertJsonCount(2, 'data.months')
            ->assertJsonPath('data.months.0.month', 2)
            ->assertJsonPath('data.months.0.total_points', 200)
            ->assertJsonPath('data.months.1.month', 1)
            ->assertJsonPath('data.months.1.total_points', 100);
    }

    private function createMemberAccount(string $level): MemberAccount
    {
        $group = MemberGroup::query()->create([
            'member_group_name' => ucfirst($level),
            'member_group_description' => 'Test group',
            'member_group_is_active' => 1,
        ]);

        $levelId = match ($level) {
            'distributor' => 1,
            'agent' => 2,
            'reseller' => 3,
        };

        $member = Member::query()->create([
            'member_code' => 'MEMBER-'.strtoupper($level).rand(100, 999),
            'member_member_level_id' => $levelId,
            'member_name' => 'Test Member',
            'member_email' => "{$level}".rand(100, 999).'@example.test',
            'member_mobilephone' => '08123456789',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->member_id,
            'member_account_member_group_id' => $group->member_group_id,
            'member_account_username' => "test.{$level}".rand(100, 999),
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
    }
}
