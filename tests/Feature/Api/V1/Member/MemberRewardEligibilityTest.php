<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\RewardStockist;
use App\Models\Stockist;
use App\Models\TrxSpreadPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MemberRewardEligibilityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('rewardAccessCases')]
    public function test_reward_list_and_detail_enforce_current_eligibility(
        int $levelId,
        ?int $stockistActive,
        int $stockistDeleted,
        ?int $downlineLevelId,
        int $downlineStatus,
        bool $directDownline,
        bool $sharingAllowed,
        bool $voucherAllowed,
    ): void {
        $member = $this->member('OWNER', $levelId);
        if ($stockistActive !== null) {
            Stockist::query()->create([
                'stockist_member_id' => $member->getKey(),
                'stockist_name' => 'Stokis Owner',
                'stockist_is_active' => $stockistActive,
                'stockist_is_deleted' => $stockistDeleted,
                'stockist_note' => '',
                'stockist_input_datetime' => now(),
            ]);
        }
        if ($downlineLevelId !== null) {
            $parent = $directDownline ? $member : $this->member('INTERMEDIATE', 2, $member);
            $this->member('DOWNLINE', $downlineLevelId, $parent, $downlineStatus);
        }
        $group = MemberGroup::query()->create([
            'member_group_name' => 'Mitra',
            'member_group_is_active' => 1,
        ]);
        $account = MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(),
            'member_account_member_group_id' => $group->getKey(),
            'member_account_username' => 'reward.owner',
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
        $sharing = TrxSpreadPayment::query()->create([
            'trx_spread_payment_trx_id' => 999,
            'trx_spread_payment_upline_id' => $member->getKey(),
            'trx_spread_payment_member_id' => 998,
            'trx_spread_payment_percentage' => 1,
            'trx_spread_payment_amount' => 100000,
            'trx_spread_payment_status' => 'paid',
            'trx_spread_payment_created_datetime' => now(),
        ]);
        $voucher = RewardStockist::query()->create([
            'reward_stockist_member_id' => $member->getKey(),
            'reward_stockist_year' => 2024,
            'reward_stockist_month' => 1,
            'reward_stockist_total_trx_amount' => 50000000,
            'reward_stockist_bonus_value' => 1250000,
            'reward_stockist_used_value' => 0,
            'reward_stockist_used_trx_id' => 0,
            'reward_stockist_expiry_date' => today()->addMonth(),
        ]);
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/rewards/sharing-profits')->assertStatus($sharingAllowed ? 200 : 403);
        $this->getJson("/api/v1/member/rewards/sharing-profits/{$sharing->getKey()}")
            ->assertStatus($sharingAllowed ? 200 : 403);
        $this->getJson('/api/v1/member/rewards/stockists')->assertStatus($voucherAllowed ? 200 : 403);
        $this->getJson("/api/v1/member/rewards/stockists/{$voucher->getKey()}")
            ->assertStatus($voucherAllowed ? 200 : 403);
        $this->getJson('/api/v1/member/auth/me')
            ->assertOk()
            ->assertJsonPath('data.member.reward_access.sharing_profit', $sharingAllowed)
            ->assertJsonPath('data.member.reward_access.stockist_voucher', $voucherAllowed);
        $this->getJson('/api/v1/member/rewards/monthly/growth')
            ->assertOk()
            ->assertJsonPath('data.reward_access.sharing_profit', $sharingAllowed)
            ->assertJsonPath('data.reward_access.stockist_voucher', $voucherAllowed);

        $this->assertSame(100000, (int) $sharing->fresh()->trx_spread_payment_amount);
        $this->assertSame('paid', $sharing->fresh()->trx_spread_payment_status);
        $this->assertSame(1250000, (int) $voucher->fresh()->reward_stockist_bonus_value);
    }

    public static function rewardAccessCases(): array
    {
        return [
            'non-stockist distributor' => [1, null, 0, 1, 1, true, false, false],
            'stockist agent with distributor child' => [2, 1, 0, 1, 1, true, false, false],
            'stockist reseller with distributor child' => [3, 1, 0, 1, 1, true, false, false],
            'inactive stockist' => [1, 0, 0, 1, 1, true, false, false],
            'deleted stockist' => [1, 1, 1, 1, 1, true, false, false],
            'stockist distributor without downlines' => [1, 1, 0, null, 1, true, false, true],
            'stockist distributor with agent child' => [1, 1, 0, 2, 1, true, false, true],
            'stockist distributor with indirect distributor' => [1, 1, 0, 1, 1, false, false, true],
            'stockist distributor with inactive distributor child' => [1, 1, 0, 1, 0, true, false, true],
            'stockist distributor with deleted distributor child' => [1, 1, 0, 1, 3, true, false, true],
            'eligible stockist distributor' => [1, 1, 0, 1, 1, true, true, true],
        ];
    }

    private function member(string $code, int $levelId, ?Member $parent = null, int $status = 1): Member
    {
        return Member::query()->create([
            'member_code' => $code,
            'member_member_level_id' => $levelId,
            'member_parent_member_id' => $parent?->getKey() ?? 0,
            'member_name' => $code,
            'member_email' => strtolower($code).'@example.test',
            'member_mobilephone' => '081234567890',
            'member_status' => $status,
            'member_join_datetime' => now(),
        ]);
    }
}
