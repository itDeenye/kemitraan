<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\MemberLevel;
use App\Models\RewardPointAnnual;
use App\Models\RewardPointAnnualLog;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminAnnualRewardManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_annual_reward_schema_only_keeps_yearly_point_reports(): void
    {
        $this->assertFalse(Schema::hasTable('reward_annual'));
        $this->assertFalse(Schema::hasTable('reward_annual_qualified'));
        $this->assertTrue(Schema::hasColumns('reward_point_annual', [
            'reward_point_annual_member_id',
            'reward_point_annual_year',
            'reward_point_annual_total_points',
            'reward_point_annual_last_updated_datetime',
        ]));
        $this->assertFalse(Schema::hasColumn('reward_point_annual', 'reward_point_annual_acc'));
        $this->assertFalse(Schema::hasColumn('reward_point_annual', 'reward_point_annual_paid'));
    }

    public function test_administrator_can_view_yearly_point_report_and_monthly_detail(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        [$member, $annualReward] = $this->createAnnualRewardData();

        $this->getJson('/api/v1/admin/rewards/annual?filter[year]=2026&search=DST-001')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.member.id', $member->getKey())
            ->assertJsonPath('data.results.0.year', 2026)
            ->assertJsonPath('data.results.0.total_points', 32);

        $this->getJson("/api/v1/admin/rewards/annual/{$annualReward->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.total_points', 32)
            ->assertJsonPath('data.months.0.month', 8)
            ->assertJsonPath('data.months.0.total_points', 12)
            ->assertJsonPath('data.months.1.month', 7)
            ->assertJsonPath('data.months.1.total_points', 20);
    }

    public function test_annual_reward_report_has_no_crud_or_qualification_endpoint(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $this->postJson('/api/v1/admin/rewards/annual', [])->assertMethodNotAllowed();
        $this->getJson('/api/v1/admin/rewards/annual/qualifications')->assertNotFound();
    }

    public function test_member_can_only_view_own_annual_point_report(): void
    {
        [, , $account] = $this->createAnnualRewardData(true);
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/rewards/annual?year=2026')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.total_points', 32)
            ->assertJsonPath('data.months.0.month', 8)
            ->assertJsonPath('data.months.0.total_points', 12);
    }

    public function test_member_annual_reward_year_validation_is_in_indonesian(): void
    {
        [, , $account] = $this->createAnnualRewardData(true);
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/rewards/annual?year=1900')
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonValidationErrors('year');
    }

    /** @return array{Member, RewardPointAnnual, MemberAccount|null} */
    private function createAnnualRewardData(bool $withAccount = false): array
    {
        $level = MemberLevel::query()->firstOrCreate(
            ['member_level_code' => 'DST'],
            [
                'member_level_name' => 'Distributor',
                'member_level_sort_order' => 1,
                'member_level_is_active' => 1,
            ],
        );
        $member = Member::query()->create([
            'member_code' => 'DST-001',
            'member_member_level_id' => $level->getKey(),
            'member_name' => 'Distributor Utama',
            'member_join_datetime' => '2026-01-01 08:00:00',
            'member_status' => 1,
        ]);
        $annualReward = RewardPointAnnual::query()->create([
            'reward_point_annual_member_id' => $member->getKey(),
            'reward_point_annual_year' => 2026,
            'reward_point_annual_total_points' => 32,
            'reward_point_annual_last_updated_datetime' => '2026-08-31 23:59:59',
        ]);

        foreach ([
            ['points' => 20, 'type' => 'in', 'datetime' => '2026-07-10 08:00:00'],
            ['points' => 15, 'type' => 'in', 'datetime' => '2026-08-10 08:00:00'],
            ['points' => 3, 'type' => 'out', 'datetime' => '2026-08-11 08:00:00'],
        ] as $log) {
            RewardPointAnnualLog::query()->create([
                'reward_point_annual_log_member_id' => $member->getKey(),
                'reward_point_annual_log_type' => $log['type'],
                'reward_point_annual_log_points' => $log['points'],
                'reward_point_annual_log_note' => 'Poin transaksi',
                'reward_point_annual_log_datetime' => $log['datetime'],
            ]);
        }

        $account = null;
        if ($withAccount) {
            $group = MemberGroup::query()->create([
                'member_group_name' => 'Distributor',
                'member_group_description' => 'Grup Distributor',
                'member_group_is_active' => 1,
            ]);
            $account = MemberAccount::query()->create([
                'member_account_member_id' => $member->getKey(),
                'member_account_member_group_id' => $group->getKey(),
                'member_account_username' => 'annual.member',
                'member_account_password' => Hash::make('Secret123'),
                'member_account_pin' => '',
            ]);
        }

        return [$member, $annualReward, $account];
    }

    private function createAdministrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Super Administrator',
            'administrator_group_type' => 'superuser',
            'administrator_group_is_active' => 1,
        ]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->getKey(),
            'administrator_username' => 'annual-reward.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Annual Reward Admin',
            'administrator_email' => 'annual-reward.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
