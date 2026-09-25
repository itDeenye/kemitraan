<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\RewardStockist;
use App\Models\Trx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberStockistRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_stockist_reward_requires_authentication(): void
    {
        $this->getJson('/api/v1/member/rewards/stockists')->assertUnauthorized();
    }

    public function test_member_can_view_stockist_rewards(): void
    {
        $account = $this->createMemberAccount('distributor');
        $this->actingAs($account, 'member_api');
        $memberId = $account->member_account_member_id;

        // Valid, unused voucher
        RewardStockist::query()->create([
            'reward_stockist_member_id' => $memberId,
            'reward_stockist_year' => 2024,
            'reward_stockist_month' => 5,
            'reward_stockist_total_trx_amount' => 100000000, // 100jt
            'reward_stockist_bonus_value' => 2500000, // 2.5%
            'reward_stockist_used_value' => 0,
            'reward_stockist_used_trx_id' => 0,
            'reward_stockist_expiry_date' => now()->addDays(30),
        ]);

        // Used voucher
        RewardStockist::query()->create([
            'reward_stockist_member_id' => $memberId,
            'reward_stockist_year' => 2024,
            'reward_stockist_month' => 6,
            'reward_stockist_total_trx_amount' => 50000000,
            'reward_stockist_bonus_value' => 1250000,
            'reward_stockist_used_value' => 1250000,
            'reward_stockist_used_trx_id' => 1,
            'reward_stockist_expiry_date' => now()->addDays(30),
        ]);

        // Expired voucher
        RewardStockist::query()->create([
            'reward_stockist_member_id' => $memberId,
            'reward_stockist_year' => 2024,
            'reward_stockist_month' => 4,
            'reward_stockist_total_trx_amount' => 50000000,
            'reward_stockist_bonus_value' => 1250000,
            'reward_stockist_used_value' => 0,
            'reward_stockist_used_trx_id' => 0,
            'reward_stockist_expiry_date' => now()->subDays(1),
        ]);

        $this->getJson('/api/v1/member/rewards/stockists?year=2024')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.total_pembelanjaan', 200000000)
            ->assertJsonPath('summary.total_voucher', 5000000)
            ->assertJsonPath('summary.persentase_voucher', 2.5)
            ->assertJsonPath('available_years.0', 2024)
            ->assertJsonCount(3, 'data.results')
            ->assertJsonPath('data.results.0.status', 'Digunakan') // month 6
            ->assertJsonPath('data.results.1.status', 'Aktif') // month 5
            ->assertJsonPath('data.results.2.status', 'Kedaluwarsa'); // month 4
    }

    public function test_member_can_view_stockist_reward_detail(): void
    {
        $account = $this->createMemberAccount('distributor');
        $this->actingAs($account, 'member_api');
        $memberId = $account->member_account_member_id;

        $trx = Trx::query()->create([
            'trx_code' => 'TRX-123',
            'trx_buyer_type' => 'distributor',
            'trx_buyer_id' => $memberId,
            'trx_seller_type' => 'warehouse',
            'trx_seller_id' => 1,
            'trx_type' => 'stock',
            'trx_is_preorder' => 0,
            'trx_total_price' => 100000,
            'trx_discount_value' => 0,
            'trx_grand_total_price' => 100000,
            'trx_shipping_cost' => 10000,
            'trx_payment_charge' => 0,
            'trx_grand_total_nett_price' => 110000,
            'trx_status' => 'completed',
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
            'trx_member_id' => $memberId,
        ]);

        $reward = RewardStockist::query()->create([
            'reward_stockist_member_id' => $memberId,
            'reward_stockist_year' => 2024,
            'reward_stockist_month' => 5,
            'reward_stockist_total_trx_amount' => 100000000,
            'reward_stockist_bonus_value' => 2500000,
            'reward_stockist_used_value' => 2500000,
            'reward_stockist_used_trx_id' => $trx->trx_id,
            'reward_stockist_expiry_date' => now()->addDays(30),
        ]);

        $this->getJson("/api/v1/member/rewards/stockists/{$reward->reward_stockist_id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.percentage', 2.5)
            ->assertJsonPath('data.remaining_value', 0)
            ->assertJsonPath('data.used_trx_code', 'TRX-123')
            ->assertJsonPath('data.status', 'Digunakan');
    }

    public function test_member_cannot_view_others_stockist_reward(): void
    {
        $account = $this->createMemberAccount('distributor');
        $this->actingAs($account, 'member_api');

        $reward = RewardStockist::query()->create([
            'reward_stockist_member_id' => 999,
            'reward_stockist_year' => 2024,
            'reward_stockist_month' => 5,
            'reward_stockist_total_trx_amount' => 100000000,
            'reward_stockist_bonus_value' => 2500000,
            'reward_stockist_used_value' => 0,
            'reward_stockist_used_trx_id' => 0,
            'reward_stockist_expiry_date' => now()->addDays(30),
        ]);

        $this->getJson("/api/v1/member/rewards/stockists/{$reward->reward_stockist_id}")
            ->assertNotFound();
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
