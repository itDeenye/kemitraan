<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\MemberLevel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberGenealogyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_genealogy_requires_authenticated_member(): void
    {
        $this->getJson('/api/v1/member/network/genealogy')->assertUnauthorized();
        $this->getJson('/api/v1/member/network/total-downlines')->assertUnauthorized();
    }

    public function test_distributor_can_browse_direct_downlines_and_drill_down_to_resellers(): void
    {
        [$distributorLevel, $agentLevel, $resellerLevel] = $this->createLevels();
        $distributor = $this->createMember('9001/0000/0000', 'Distributor Utama', $distributorLevel);
        $agentAlpha = $this->createMember('9001/0001/0000', 'Agent Alpha', $agentLevel, $distributor);
        $agentBeta = $this->createMember('9001/0002/0000', 'Agent Beta', $agentLevel, $distributor);
        $resellerAlpha = $this->createMember('9001/0001/0001', 'Reseller Alpha', $resellerLevel, $agentAlpha);
        $this->createMember('9001/0001/0002', 'Reseller Dihapus', $resellerLevel, $agentAlpha, 3);
        $otherDistributor = $this->createMember('9002/0000/0000', 'Distributor Lain', $distributorLevel);
        $otherAgent = $this->createMember('9002/0001/0000', 'Agent Lain', $agentLevel, $otherDistributor);
        $account = $this->createAccount($distributor, 'distributor.test');

        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/network/total-downlines')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Total jaringan mitra berhasil dimuat.')
            ->assertJsonPath('data.total_downlines', 3);

        $this->getJson('/api/v1/member/network/genealogy?limit=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.context.root_member.id', $distributor->getKey())
            ->assertJsonPath('data.context.viewing_member.id', $distributor->getKey())
            ->assertJsonPath('data.context.can_register', true)
            ->assertJsonCount(1, 'data.context.breadcrumbs')
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $agentAlpha->getKey())
            ->assertJsonPath('data.results.0.total_direct_downlines', 1)
            ->assertJsonPath('data.results.0.has_downlines', true)
            ->assertJsonPath('data.pagination.total_data', 2)
            ->assertJsonPath('data.pagination.next', 2);

        $this->getJson("/api/v1/member/network/genealogy?parent_id={$agentAlpha->getKey()}&limit=10")
            ->assertOk()
            ->assertJsonPath('data.context.viewing_member.id', $agentAlpha->getKey())
            ->assertJsonCount(2, 'data.context.breadcrumbs')
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $resellerAlpha->getKey())
            ->assertJsonPath('data.results.0.level.code', 'RSL');

        $this->getJson('/api/v1/member/network/genealogy?search=Beta&limit=10')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $agentBeta->getKey());

        $this->getJson('/api/v1/member/network/genealogy?search=Reseller%20Alpha&sort=name&page=1&limit=20')
            ->assertOk()
            ->assertJsonPath('data.context.viewing_member.id', $distributor->getKey())
            ->assertJsonCount(1, 'data.context.breadcrumbs')
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $resellerAlpha->getKey())
            ->assertJsonMissingPath('data.results.0.parent_id');

        $this->getJson("/api/v1/member/network/genealogy?parent_id={$otherAgent->getKey()}")
            ->assertNotFound();
    }

    public function test_reseller_cannot_access_member_genealogy(): void
    {
        [, , $resellerLevel] = $this->createLevels();
        $reseller = $this->createMember('9003/0001/0001', 'Reseller', $resellerLevel);
        $account = $this->createAccount($reseller, 'reseller.test');

        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/network/genealogy')->assertForbidden();
        $this->getJson('/api/v1/member/network/total-downlines')->assertForbidden();
    }

    /** @return array{MemberLevel, MemberLevel, MemberLevel} */
    private function createLevels(): array
    {
        return [
            $this->createLevel('DST', 'Distributor', 1),
            $this->createLevel('AGT', 'Agent', 2),
            $this->createLevel('RSL', 'Reseller', 3),
        ];
    }

    private function createLevel(string $code, string $name, int $sortOrder): MemberLevel
    {
        return MemberLevel::query()->updateOrCreate([
            'member_level_code' => $code,
        ], [
            'member_level_name' => $name,
            'member_level_description' => $name,
            'member_level_min_order' => 0,
            'member_level_point_value' => 0,
            'member_level_sort_order' => $sortOrder,
            'member_level_is_active' => 1,
        ]);
    }

    private function createMember(
        string $code,
        string $name,
        MemberLevel $level,
        ?Member $parent = null,
        int $status = 1,
    ): Member {
        return Member::query()->create([
            'member_code' => $code,
            'member_member_level_id' => $level->getKey(),
            'member_parent_member_id' => $parent?->getKey() ?? 0,
            'member_name' => $name,
            'member_join_datetime' => now(),
            'member_status' => $status,
        ]);
    }

    private function createAccount(Member $member, string $username): MemberAccount
    {
        $group = MemberGroup::query()->updateOrCreate([
            'member_group_name' => $member->level?->member_level_name ?? 'Member',
        ], [
            'member_group_description' => 'Group pengujian jaringan',
            'member_group_is_active' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(),
            'member_account_member_group_id' => $group->getKey(),
            'member_account_username' => $username,
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
    }
}
