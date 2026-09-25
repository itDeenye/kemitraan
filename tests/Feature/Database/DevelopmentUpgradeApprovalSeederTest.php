<?php

namespace Tests\Feature\Database;

use App\Models\Member;
use App\Models\MemberAchievement;
use App\Models\MemberLevel;
use App\Models\MemberUpgradeQualified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DevelopmentUpgradeApprovalSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_prepares_an_idempotent_reseller_to_agent_approval_scenario(): void
    {
        Carbon::setTestNow('2026-09-16 10:00:00');
        [$distributorLevel, $agentLevel, $resellerLevel] = $this->levels();
        $distributor = $this->member(
            '0001/0000/0000',
            'Distributor Development',
            $distributorLevel,
            '+6281200000001',
        );
        $agent = $this->member(
            '0001/0001/0000',
            'Agent Development',
            $agentLevel,
            '+6281200000101',
            $distributor,
        );
        $reseller = $this->member(
            '0001/0001/0001',
            'Reseller Development',
            $resellerLevel,
            '+6281200000201',
            $agent,
        );
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'development');

        try {
            $this->assertSame(0, Artisan::call('development:seed-upgrade-approval', [
                'member_id' => $reseller->getKey(),
            ]));
            $this->assertSame(0, Artisan::call('development:seed-upgrade-approval', [
                'member_id' => $reseller->getKey(),
            ]));
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }

        $qualification = MemberUpgradeQualified::query()->firstOrFail();
        $achievements = MemberAchievement::query()
            ->where('member_achievement_member_id', $reseller->getKey())
            ->orderBy('member_achievement_year')
            ->orderBy('member_achievement_month')
            ->get();

        $this->assertSame(1, MemberUpgradeQualified::query()->count());
        $this->assertSame($reseller->getKey(), $qualification->member_upgrade_qualified_member_id);
        $this->assertSame($resellerLevel->getKey(), $qualification->member_upgrade_qualified_from_level_id);
        $this->assertSame($agentLevel->getKey(), $qualification->member_upgrade_qualified_to_level_id);
        $this->assertSame(2606, $qualification->member_upgrade_qualified_from_year_month);
        $this->assertSame(2608, $qualification->member_upgrade_qualified_to_year_month);
        $this->assertSame('requested', $qualification->member_upgrade_qualified_status);
        $this->assertCount(3, $achievements);
        $this->assertSame([50, 55, 60], $achievements
            ->pluck('member_achievement_point')
            ->all());
        $this->assertSame([3, 3, 3], $achievements
            ->pluck('member_achievement_customer_count')
            ->all());
    }

    /** @return array{MemberLevel, MemberLevel, MemberLevel} */
    private function levels(): array
    {
        return [
            $this->level('DST', 'Distributor', 1),
            $this->level('AGT', 'Agent', 2),
            $this->level('RSL', 'Reseller', 3),
        ];
    }

    private function level(string $code, string $name, int $sortOrder): MemberLevel
    {
        return MemberLevel::query()->updateOrCreate([
            'member_level_code' => $code,
        ], [
            'member_level_name' => $name,
            'member_level_description' => $name,
            'member_level_min_order' => 0,
            'member_level_point_value' => 5000,
            'member_level_sort_order' => $sortOrder,
            'member_level_is_active' => 1,
        ]);
    }

    private function member(
        string $code,
        string $name,
        MemberLevel $level,
        string $mobilePhone,
        ?Member $parent = null,
    ): Member {
        return Member::query()->create([
            'member_code' => $code,
            'member_member_level_id' => $level->getKey(),
            'member_parent_member_id' => $parent?->getKey() ?? 0,
            'member_name' => $name,
            'member_email' => str_replace('/', '.', $code).'@example.test',
            'member_mobilephone' => $mobilePhone,
            'member_identity_no' => 'ID-'.str_replace('/', '-', $code),
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
    }
}
