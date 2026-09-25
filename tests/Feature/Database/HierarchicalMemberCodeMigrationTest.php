<?php

namespace Tests\Feature\Database;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HierarchicalMemberCodeMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_member_codes_are_converted_with_their_login_usernames(): void
    {
        $distributorLevel = $this->level('DST');
        $agentLevel = $this->level('AGT');
        $resellerLevel = $this->level('RSL');
        $distributor = $this->member('DNY000001', $distributorLevel);
        $agent = $this->member('DNY000002', $agentLevel, $distributor);
        $reseller = $this->member('DNY000003', $resellerLevel, $agent);
        $standaloneAgent = $this->member('DNY000004', $agentLevel);

        $this->account($distributor);
        $this->account($agent);
        $this->account($reseller);
        $this->account($standaloneAgent);

        $migration = require database_path('migrations/2026_09_01_151136_restore_hierarchical_member_codes.php');
        $migration->up();

        $this->assertDatabaseHas('member', [
            'member_id' => $distributor->getKey(),
            'member_code' => '0001/0000/0000',
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $agent->getKey(),
            'member_code' => '0001/0001/0000',
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $reseller->getKey(),
            'member_code' => '0001/0001/0001',
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $standaloneAgent->getKey(),
            'member_code' => '0000/0001/0000',
        ]);
        $this->assertDatabaseHas('member_account', [
            'member_account_member_id' => $reseller->getKey(),
            'member_account_username' => '0001/0001/0001',
        ]);
    }

    private function level(string $code): MemberLevel
    {
        return MemberLevel::query()->firstOrCreate(
            ['member_level_code' => $code],
            [
                'member_level_name' => $code,
                'member_level_is_active' => 1,
            ],
        );
    }

    private function member(string $code, MemberLevel $level, ?Member $parent = null): Member
    {
        $sequence = Member::query()->count() + 1;

        return Member::query()->create([
            'member_code' => $code,
            'member_member_level_id' => $level->getKey(),
            'member_parent_member_id' => $parent?->getKey() ?? 0,
            'member_name' => "Mitra {$sequence}",
            'member_email' => "mitra.{$sequence}@example.test",
            'member_mobilephone' => '+628'.str_pad((string) $sequence, 10, '0', STR_PAD_LEFT),
            'member_identity_no' => str_pad((string) $sequence, 16, '0', STR_PAD_LEFT),
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
    }

    private function account(Member $member): MemberAccount
    {
        return MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(),
            'member_account_member_group_id' => $member->member_member_level_id,
            'member_account_username' => $member->member_code,
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '123456',
        ]);
    }
}
