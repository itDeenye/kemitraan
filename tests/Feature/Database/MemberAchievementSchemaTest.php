<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MemberAchievementSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_achievement_stores_one_monthly_snapshot_per_member(): void
    {
        $this->assertTrue(Schema::hasColumns('member_achievement', [
            'member_achievement_id',
            'member_achievement_member_id',
            'member_achievement_year',
            'member_achievement_month',
            'member_achievement_point',
            'member_achievement_customer_count',
            'member_achievement_total_trx_amount',
            'member_achievement_create_datetime',
        ]));

        $this->assertTrue(Schema::hasIndex('member_achievement', [
            'member_achievement_member_id',
            'member_achievement_year',
            'member_achievement_month',
        ], 'unique'));
        $this->assertTrue(Schema::hasIndex('member_achievement', [
            'member_achievement_year',
            'member_achievement_month',
        ]));
    }

    public function test_member_upgrade_qualified_stores_approval_and_period_information(): void
    {
        $this->assertTrue(Schema::hasColumns('member_upgrade_qualified', [
            'member_upgrade_qualified_id',
            'member_upgrade_qualified_member_id',
            'member_upgrade_qualified_from_level_id',
            'member_upgrade_qualified_to_level_id',
            'member_upgrade_qualified_from_year_month',
            'member_upgrade_qualified_to_year_month',
            'member_upgrade_qualified_status',
            'member_upgrade_qualified_admin_id',
            'member_upgrade_qualified_approved_datetime',
            'member_upgrade_qualified_effective_date',
            'member_upgrade_qualified_applied_datetime',
            'member_upgrade_qualified_last_update_datetime',
            'member_upgrade_qualified_created_datetime',
        ]));

        $this->assertTrue(Schema::hasIndex('member_upgrade_qualified', [
            'member_upgrade_qualified_member_id',
            'member_upgrade_qualified_from_level_id',
            'member_upgrade_qualified_to_level_id',
            'member_upgrade_qualified_from_year_month',
            'member_upgrade_qualified_to_year_month',
        ], 'unique'));
        $this->assertTrue(Schema::hasIndex(
            'member_upgrade_qualified',
            ['member_upgrade_qualified_status']
        ));

        $statusDefinition = DB::getDriverName() === 'sqlite'
            ? DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'member_upgrade_qualified'")->sql
            : Schema::getColumnType('member_upgrade_qualified', 'member_upgrade_qualified_status', true);

        $this->assertStringContainsString('requested', $statusDefinition);
        $this->assertStringContainsString('approved', $statusDefinition);
        $this->assertStringContainsString('rejected', $statusDefinition);
        $this->assertStringContainsString('scheduled', $statusDefinition);
        $this->assertStringContainsString('applied', $statusDefinition);
        $this->assertStringContainsString('cancelled', $statusDefinition);
    }

    public function test_network_switch_schedules_parent_and_level_changes(): void
    {
        $this->assertTrue(Schema::hasColumns('member_network_switch', [
            'network_switch_transfer_id',
            'network_switch_upgrade_qualified_id',
            'network_switch_member_id',
            'network_switch_from_parent_member_id',
            'network_switch_to_parent_member_id',
            'network_switch_from_level_id',
            'network_switch_to_level_id',
            'network_switch_type',
            'network_switch_status',
            'network_switch_admin_id',
            'network_switch_approved_datetime',
            'network_switch_effective_date',
            'network_switch_applied_datetime',
            'network_switch_transfer_note',
            'network_switch_created_datetime',
        ]));

        $this->assertFalse(Schema::hasColumn(
            'member_network_switch',
            'network_switch_group_code'
        ));
        $this->assertFalse(Schema::hasColumn(
            'member_network_switch',
            'network_switch_qualified_id'
        ));
        $this->assertTrue(Schema::hasIndex('member_network_switch', [
            'network_switch_status',
            'network_switch_effective_date',
        ]));

        $statusDefinition = DB::getDriverName() === 'sqlite'
            ? DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'member_network_switch'")->sql
            : Schema::getColumnType('member_network_switch', 'network_switch_status', true);

        $this->assertStringContainsString('scheduled', $statusDefinition);
        $this->assertStringContainsString('approved', $statusDefinition);
        $this->assertStringContainsString('rejected', $statusDefinition);
    }

    public function test_member_history_only_references_applied_changes(): void
    {
        $this->assertTrue(Schema::hasColumns('member_history', [
            'member_history_upgrade_qualified_id',
            'member_history_network_transfer_id',
        ]));
        $this->assertFalse(Schema::hasColumn('member_history', 'member_history_status'));
        $this->assertTrue(Schema::hasIndex(
            'member_history',
            ['member_history_upgrade_qualified_id']
        ));
        $this->assertTrue(Schema::hasIndex(
            'member_history',
            ['member_history_network_transfer_id']
        ));
    }
}
