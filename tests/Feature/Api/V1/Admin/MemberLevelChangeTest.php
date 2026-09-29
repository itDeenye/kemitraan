<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Member;
use App\Models\MemberAchievement;
use App\Models\MemberLevel;
use App\Models\MemberNetworkSwitch;
use App\Models\MemberUpgradeQualified;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberLevelChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_approves_upgrade_for_next_month_and_schedules_network_transfer(): void
    {
        Carbon::setTestNow('2026-07-29 10:00:00');
        [$distributorLevel, $agentLevel, $resellerLevel] = $this->levels();
        $distributor = $this->member('0001/0000/0000', 'Distributor', $distributorLevel);
        $agent = $this->member('0001/0001/0000', 'Agent', $agentLevel, $distributor);
        $reseller = $this->member('0001/0001/0001', 'Reseller', $resellerLevel, $agent);
        $administrator = $this->administrator();
        $this->actingAs($administrator, 'admin_api');

        foreach ([4 => 50, 5 => 55, 6 => 60] as $month => $point) {
            MemberAchievement::query()->create([
                'member_achievement_member_id' => $reseller->getKey(),
                'member_achievement_year' => 2026,
                'member_achievement_month' => $month,
                'member_achievement_point' => $point,
                'member_achievement_customer_count' => 3,
                'member_achievement_total_trx_amount' => 10000000,
                'member_achievement_create_datetime' => now(),
            ]);
        }

        $upgrade = MemberUpgradeQualified::query()->create([
            'member_upgrade_qualified_member_id' => $reseller->getKey(),
            'member_upgrade_qualified_from_level_id' => $resellerLevel->getKey(),
            'member_upgrade_qualified_to_level_id' => $agentLevel->getKey(),
            'member_upgrade_qualified_from_year_month' => 2604,
            'member_upgrade_qualified_to_year_month' => 2606,
            'member_upgrade_qualified_status' => 'requested',
            'member_upgrade_qualified_admin_id' => 0,
            'member_upgrade_qualified_created_datetime' => now(),
        ]);

        $this->postJson("/api/v1/admin/partnership/upgrades/{$upgrade->getKey()}/approve", [
            'note' => 'Target tiga bulan terpenuhi.',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.code', 'scheduled')
            ->assertJsonPath('data.effective_date', '2026-08-01')
            ->assertJsonCount(3, 'data.achievements')
            ->assertJsonPath('data.achievements.0.sequence', 1)
            ->assertJsonPath('data.achievements.0.label', 'Bulan ke-1')
            ->assertJsonPath('data.achievements.0.period', '2026-04')
            ->assertJsonPath('data.achievements.0.point', 50)
            ->assertJsonPath('data.achievements.0.minimum_point', 50)
            ->assertJsonPath('data.achievements.0.is_point_requirement_met', true)
            ->assertJsonPath('data.achievements.1.label', 'Bulan ke-2')
            ->assertJsonPath('data.achievements.1.period', '2026-05')
            ->assertJsonPath('data.achievements.1.point', 55)
            ->assertJsonPath('data.achievements.2.label', 'Bulan ke-3')
            ->assertJsonPath('data.achievements.2.period', '2026-06')
            ->assertJsonPath('data.achievements.2.point', 60)
            ->assertJsonPath('data.network_transfers.0.to_parent.id', $distributor->getKey());

        $this->assertDatabaseHas('member_network_switch', [
            'network_switch_upgrade_qualified_id' => $upgrade->getKey(),
            'network_switch_member_id' => $reseller->getKey(),
            'network_switch_from_parent_member_id' => $agent->getKey(),
            'network_switch_to_parent_member_id' => $distributor->getKey(),
            'network_switch_status' => 'approved',
            'network_switch_effective_date' => '2026-08-01 00:00:00',
        ]);
        $this->getJson('/api/v1/admin/partnership/upgrades?filter[status]=scheduled')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $upgrade->getKey());

        $this->postJson("/api/v1/admin/partnership/upgrades/{$upgrade->getKey()}/approve")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        Carbon::setTestNow('2026-08-01 00:05:00');
        $this->assertSame(0, Artisan::call('members:apply-upgrade-downgrade'));
        $this->assertDatabaseHas('member', [
            'member_id' => $reseller->getKey(),
            'member_member_level_id' => $resellerLevel->getKey(),
            'member_parent_member_id' => $agent->getKey(),
        ]);
        $this->assertSame(0, Artisan::call('rewards:close-month', ['--period' => '2026-07']));
        $this->assertSame(0, Artisan::call('members:apply-upgrade-downgrade'));
        $this->assertDatabaseHas('member', [
            'member_id' => $reseller->getKey(),
            'member_code' => '0001/0001/0001',
            'member_member_level_id' => $agentLevel->getKey(),
            'member_parent_member_id' => $distributor->getKey(),
        ]);
        $this->assertDatabaseHas('member_upgrade_qualified', [
            'member_upgrade_qualified_id' => $upgrade->getKey(),
            'member_upgrade_qualified_status' => 'applied',
        ]);
        $this->assertDatabaseHas('member_history', [
            'member_history_member_id' => $reseller->getKey(),
            'member_history_action' => 'upgrade',
            'member_history_to_level_id' => $agentLevel->getKey(),
        ]);
    }

    public function test_agent_upgrade_to_distributor_remains_under_its_distributor_parent(): void
    {
        Carbon::setTestNow('2026-07-29 10:00:00');
        [$distributorLevel, $agentLevel, $resellerLevel] = $this->levels();
        $mainDistributor = $this->member('0001/0000/0000', 'Distributor Utama', $distributorLevel);
        $agent = $this->member('0001/0001/0000', 'Ayu Agent', $agentLevel, $mainDistributor);
        $reseller = $this->member('0001/0001/0001', 'Reseller Bawahan', $resellerLevel, $agent);
        $upgrade = MemberUpgradeQualified::query()->create([
            'member_upgrade_qualified_member_id' => $agent->getKey(),
            'member_upgrade_qualified_from_level_id' => $agentLevel->getKey(),
            'member_upgrade_qualified_to_level_id' => $distributorLevel->getKey(),
            'member_upgrade_qualified_from_year_month' => 2604,
            'member_upgrade_qualified_to_year_month' => 2606,
            'member_upgrade_qualified_status' => 'requested',
            'member_upgrade_qualified_admin_id' => 0,
            'member_upgrade_qualified_created_datetime' => now(),
        ]);
        $this->actingAs($this->administrator(), 'admin_api');

        $this->postJson("/api/v1/admin/partnership/upgrades/{$upgrade->getKey()}/approve")
            ->assertOk()
            ->assertJsonCount(1, 'data.network_transfers')
            ->assertJsonPath('data.network_transfers.0.to_parent.id', $mainDistributor->getKey());

        $this->assertDatabaseHas('member_network_switch', [
            'network_switch_upgrade_qualified_id' => $upgrade->getKey(),
            'network_switch_to_parent_member_id' => $mainDistributor->getKey(),
        ]);
        $this->assertDatabaseHas('member_upgrade_qualified', [
            'member_upgrade_qualified_id' => $upgrade->getKey(),
            'member_upgrade_qualified_status' => 'scheduled',
        ]);

        Carbon::setTestNow('2026-08-01 00:05:00');
        $this->assertSame(0, Artisan::call('rewards:close-month', ['--period' => '2026-07']));
        $this->assertSame(0, Artisan::call('members:apply-upgrade-downgrade'));
        $this->assertDatabaseHas('member', [
            'member_id' => $agent->getKey(),
            'member_code' => '0001/0001/0000',
            'member_member_level_id' => $distributorLevel->getKey(),
            'member_parent_member_id' => $mainDistributor->getKey(),
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $reseller->getKey(),
            'member_member_level_id' => $resellerLevel->getKey(),
            'member_parent_member_id' => $agent->getKey(),
        ]);
    }

    public function test_downgrade_automatically_moves_direct_downlines_that_need_a_new_parent(): void
    {
        Carbon::setTestNow('2026-07-29 10:00:00');
        [$distributorLevel, $agentLevel, $resellerLevel] = $this->levels();
        $distributor = $this->member('0001/0000/0000', 'Distributor', $distributorLevel);
        $agent = $this->member('0001/0001/0000', 'Agent Lama', $agentLevel, $distributor);
        $replacementAgent = $this->member('0001/0002/0000', 'Agent Baru', $agentLevel, $distributor);
        $reseller = $this->member('0001/0001/0001', 'Reseller', $resellerLevel, $agent);
        $this->actingAs($this->administrator(), 'admin_api');

        $payload = [
            'to_level_id' => $resellerLevel->getKey(),
            'to_parent_member_id' => $replacementAgent->getKey(),
            'note' => 'Hasil evaluasi dan negosiasi manajemen.',
        ];

        $response = $this->postJson("/api/v1/admin/partnership/members/{$agent->getKey()}/downgrade", $payload)
            ->assertOk()
            ->assertJsonPath('data.status.code', 'scheduled')
            ->assertJsonPath('data.member.id', $agent->getKey())
            ->assertJsonPath('data.to_level.code', 'RSL')
            ->assertJsonPath('data.to_parent.id', $replacementAgent->getKey())
            ->assertJsonPath('data.effective_date', '2026-08-01');

        $this->assertDatabaseCount('member_network_switch', 2);
        $this->assertDatabaseHas('member_network_switch', [
            'network_switch_member_id' => $reseller->getKey(),
            'network_switch_to_parent_member_id' => $replacementAgent->getKey(),
            'network_switch_from_level_id' => $resellerLevel->getKey(),
            'network_switch_to_level_id' => $resellerLevel->getKey(),
            'network_switch_type' => 'downgrade',
            'network_switch_status' => 'scheduled',
        ]);
        $batchUuid = DB::table('member_network_switch')
            ->where('network_switch_member_id', $agent->getKey())
            ->value('network_switch_batch_uuid');
        $this->assertNotEmpty($batchUuid);
        $this->assertSame(2, DB::table('member_network_switch')
            ->where('network_switch_batch_uuid', $batchUuid)
            ->count());
        $this->getJson('/api/v1/admin/partnership/downgrades?filter[status]=scheduled')
            ->assertOk()
            ->assertJsonCount(2, 'data.results');

        $this->assertDatabaseCount('member_network_switch', 2);
        $this->assertSame(2, DB::table('member_network_switch')
            ->where('network_switch_status', 'scheduled')
            ->count());

        Carbon::setTestNow('2026-08-01 00:05:00');
        $this->assertSame(0, Artisan::call('members:apply-upgrade-downgrade'));
        $this->assertDatabaseHas('member', [
            'member_id' => $agent->getKey(),
            'member_member_level_id' => $agentLevel->getKey(),
            'member_parent_member_id' => $distributor->getKey(),
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $reseller->getKey(),
            'member_parent_member_id' => $agent->getKey(),
        ]);

        $this->assertSame(0, Artisan::call('rewards:close-month', ['--period' => '2026-07']));
        $this->assertSame(0, Artisan::call('members:apply-upgrade-downgrade'));
        $this->assertDatabaseHas('member', [
            'member_id' => $agent->getKey(),
            'member_member_level_id' => $resellerLevel->getKey(),
            'member_parent_member_id' => $replacementAgent->getKey(),
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $reseller->getKey(),
            'member_parent_member_id' => $replacementAgent->getKey(),
        ]);
    }

    public function test_distributor_downgrade_preserves_valid_agent_and_reseller_hierarchy(): void
    {
        Carbon::setTestNow('2026-07-29 10:00:00');
        [$distributorLevel, $agentLevel, $resellerLevel] = $this->levels();
        $distributor = $this->member('0001/0000/0000', 'Distributor Lama', $distributorLevel);
        $replacementDistributor = $this->member('0002/0000/0000', 'Distributor Baru', $distributorLevel);
        $agent = $this->member('0001/0001/0000', 'Agent Bawahan', $agentLevel, $distributor);
        $resellerUnderAgent = $this->member('0001/0001/0001', 'Reseller Agent', $resellerLevel, $agent);
        $directReseller = $this->member('0001/0000/0001', 'Reseller Langsung', $resellerLevel, $distributor);
        $existingAgent = $this->member('0002/0001/0000', 'Agent Sponsor Baru', $agentLevel, $replacementDistributor);
        $existingReseller = $this->member('0002/0001/0001', 'Reseller Sponsor Baru', $resellerLevel, $existingAgent);
        $this->actingAs($this->administrator(), 'admin_api');

        $this->postJson("/api/v1/admin/partnership/members/{$distributor->getKey()}/downgrade", [
            'to_level_id' => $agentLevel->getKey(),
            'to_parent_member_id' => $replacementDistributor->getKey(),
            'note' => 'Penyesuaian jaringan otomatis.',
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'scheduled');

        $this->assertDatabaseCount('member_network_switch', 2);
        $this->assertDatabaseHas('member_network_switch', [
            'network_switch_member_id' => $agent->getKey(),
            'network_switch_to_parent_member_id' => $replacementDistributor->getKey(),
        ]);
        $this->assertDatabaseMissing('member_network_switch', [
            'network_switch_member_id' => $directReseller->getKey(),
        ]);
        $this->assertDatabaseMissing('member_network_switch', [
            'network_switch_member_id' => $resellerUnderAgent->getKey(),
        ]);

        Carbon::setTestNow('2026-08-01 00:05:00');
        $this->assertSame(0, Artisan::call('rewards:close-month', ['--period' => '2026-07']));
        $this->assertSame(0, Artisan::call('members:apply-upgrade-downgrade'));

        $this->assertDatabaseHas('member', [
            'member_id' => $distributor->getKey(),
            'member_member_level_id' => $agentLevel->getKey(),
            'member_parent_member_id' => $replacementDistributor->getKey(),
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $agent->getKey(),
            'member_parent_member_id' => $replacementDistributor->getKey(),
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $directReseller->getKey(),
            'member_parent_member_id' => $distributor->getKey(),
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $resellerUnderAgent->getKey(),
            'member_parent_member_id' => $agent->getKey(),
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $existingAgent->getKey(),
            'member_parent_member_id' => $replacementDistributor->getKey(),
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $existingReseller->getKey(),
            'member_parent_member_id' => $existingAgent->getKey(),
        ]);
    }

    public function test_downgrade_batch_rolls_back_every_transfer_when_one_downline_has_changed(): void
    {
        Carbon::setTestNow('2026-07-29 10:00:00');
        [$distributorLevel, $agentLevel] = $this->levels();
        $distributor = $this->member('0001/0000/0000', 'Distributor Lama', $distributorLevel);
        $replacementDistributor = $this->member('0002/0000/0000', 'Distributor Baru', $distributorLevel);
        $otherDistributor = $this->member('0003/0000/0000', 'Distributor Lain', $distributorLevel);
        $agent = $this->member('0001/0001/0000', 'Agent Bawahan', $agentLevel, $distributor);
        $this->actingAs($this->administrator(), 'admin_api');

        $this->postJson("/api/v1/admin/partnership/members/{$distributor->getKey()}/downgrade", [
            'to_level_id' => $agentLevel->getKey(),
            'to_parent_member_id' => $replacementDistributor->getKey(),
            'note' => 'Penyesuaian jaringan otomatis.',
        ])->assertOk();

        foreach ([$distributor, $agent] as $member) {
            MemberAchievement::query()->create([
                'member_achievement_member_id' => $member->getKey(),
                'member_achievement_year' => 2026,
                'member_achievement_month' => 7,
                'member_achievement_point' => 0,
                'member_achievement_customer_count' => 0,
                'member_achievement_total_trx_amount' => 0,
                'member_achievement_create_datetime' => now(),
            ]);
        }

        $agent->update(['member_parent_member_id' => $otherDistributor->getKey()]);

        Carbon::setTestNow('2026-08-01 00:05:00');
        $this->assertSame(0, Artisan::call('members:apply-upgrade-downgrade'));

        $this->assertDatabaseHas('member', [
            'member_id' => $distributor->getKey(),
            'member_member_level_id' => $distributorLevel->getKey(),
            'member_parent_member_id' => 0,
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $agent->getKey(),
            'member_parent_member_id' => $otherDistributor->getKey(),
        ]);
        $this->assertSame(2, DB::table('member_network_switch')
            ->whereNull('network_switch_applied_datetime')
            ->count());
        $this->assertDatabaseCount('member_history', 0);
    }

    public function test_downgrade_sponsor_options_only_return_eligible_active_members(): void
    {
        Carbon::setTestNow('2026-07-29 10:00:00');
        [$distributorLevel, $agentLevel, $resellerLevel] = $this->levels();
        $distributor = $this->member('DNY000001', 'Distributor Utama', $distributorLevel);
        $targetAgent = $this->member('DNY000002', 'Bima Agent', $agentLevel, $distributor);
        $eligibleAgent = $this->member('DNY000003', 'Ayu Agent', $agentLevel, $distributor);
        $pendingAgent = $this->member('DNY000004', 'Citra Agent', $agentLevel, $distributor);
        $inactiveAgent = $this->member('DNY000005', 'Dewi Agent', $agentLevel, $distributor);
        $inactiveAgent->update(['member_status' => 0]);

        MemberNetworkSwitch::query()->create([
            'network_switch_upgrade_qualified_id' => 0,
            'network_switch_member_id' => $pendingAgent->getKey(),
            'network_switch_from_parent_member_id' => $distributor->getKey(),
            'network_switch_to_parent_member_id' => $distributor->getKey(),
            'network_switch_from_level_id' => $agentLevel->getKey(),
            'network_switch_to_level_id' => $agentLevel->getKey(),
            'network_switch_type' => 'downgrade',
            'network_switch_status' => 'scheduled',
            'network_switch_admin_id' => 0,
            'network_switch_approved_datetime' => now(),
            'network_switch_effective_date' => '2026-08-01',
            'network_switch_transfer_note' => 'Perpindahan terjadwal.',
            'network_switch_created_datetime' => now(),
        ]);

        $this->actingAs($this->administrator(), 'admin_api');

        $url = '/api/v1/admin/partnership/downgrades/sponsor-options'
            .'?member_id='.$targetAgent->getKey()
            .'&to_level_id='.$resellerLevel->getKey()
            .'&limit=10';

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member.id', $targetAgent->getKey())
            ->assertJsonPath('data.to_level.code', 'RSL')
            ->assertJsonPath('data.required_sponsor_level', 'Agent')
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $eligibleAgent->getKey())
            ->assertJsonPath('data.results.0.label', 'DNY000003 - Ayu Agent');

        $this->getJson($url.'&search=tidak-ada')
            ->assertOk()
            ->assertJsonCount(0, 'data.results');

        $this->getJson(
            '/api/v1/admin/partnership/downgrades/sponsor-options'
            .'?member_id='.$targetAgent->getKey()
            .'&to_level_id='.$agentLevel->getKey()
        )->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath(
                'message',
                'Downgrade hanya dapat dilakukan satu tingkat: Distributor ke Agent atau Agent ke Reseller.'
            );
    }

    public function test_downgrade_has_no_separate_approval_endpoint(): void
    {
        Carbon::setTestNow('2026-07-29 10:00:00');
        [$distributorLevel, $agentLevel, $resellerLevel] = $this->levels();
        $distributor = $this->member('0002/0000/0000', 'Distributor', $distributorLevel);
        $agent = $this->member('0002/0001/0000', 'Agent Lama', $agentLevel, $distributor);
        $replacementAgent = $this->member('0002/0002/0000', 'Agent Baru', $agentLevel, $distributor);
        $this->actingAs($this->administrator(), 'admin_api');

        $response = $this->postJson("/api/v1/admin/partnership/members/{$agent->getKey()}/downgrade", [
            'to_level_id' => $resellerLevel->getKey(),
            'to_parent_member_id' => $replacementAgent->getKey(),
            'note' => 'Dijadwalkan akhir Juli.',
        ])->assertOk()
            ->assertJsonPath('data.effective_date', '2026-08-01');

        $this->postJson('/api/v1/admin/partnership/downgrades/'.$response->json('data.id').'/approve')
            ->assertNotFound();
        $this->assertDatabaseHas('member_network_switch', [
            'network_switch_transfer_id' => $response->json('data.id'),
            'network_switch_status' => 'scheduled',
            'network_switch_effective_date' => '2026-08-01 00:00:00',
        ]);
    }

    /** @return array{MemberLevel, MemberLevel, MemberLevel} */
    private function levels(): array
    {
        return [
            $this->level(1, 'DST', 'Distributor', 1),
            $this->level(2, 'AGT', 'Agent', 2),
            $this->level(3, 'RSL', 'Reseller', 3),
        ];
    }

    private function level(int $id, string $code, string $name, int $sortOrder): MemberLevel
    {
        return MemberLevel::query()->updateOrCreate(
            ['member_level_id' => $id],
            [
                'member_level_code' => $code,
                'member_level_name' => $name,
                'member_level_description' => $name,
                'member_level_min_order' => 0,
                'member_level_point_value' => 5000,
                'member_level_sort_order' => $sortOrder,
                'member_level_is_active' => 1,
            ]
        );
    }

    private function member(
        string $code,
        string $name,
        MemberLevel $level,
        ?Member $parent = null
    ): Member {
        return Member::query()->create([
            'member_code' => $code,
            'member_member_level_id' => $level->getKey(),
            'member_parent_member_id' => $parent?->getKey() ?? 0,
            'member_name' => $name,
            'member_email' => str_replace('/', '.', $code).'@example.test',
            'member_mobilephone' => '08'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
            'member_identity_no' => 'ID-'.str_replace('/', '-', $code),
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
    }

    private function administrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Super Administrator',
            'administrator_group_type' => 'superuser',
            'administrator_group_is_active' => 1,
        ]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->getKey(),
            'administrator_username' => 'level.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Level Admin',
            'administrator_email' => 'level.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
