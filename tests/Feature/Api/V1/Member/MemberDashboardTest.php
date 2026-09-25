<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\Trx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/member/dashboard')->assertUnauthorized();
    }

    public function test_member_dashboard_returns_summary_and_latest_downlines(): void
    {
        $account = $this->createMemberAccount();
        $memberId = $account->member_account_member_id;
        $downline = Member::query()->create([
            'member_code' => 'DEV-AGENT',
            'member_member_level_id' => 2,
            'member_parent_member_id' => $memberId,
            'member_name' => 'Agent Terbaru',
            'member_email' => 'agent@example.test',
            'member_mobilephone' => '081200000002',
            'member_status' => 1,
            'member_join_datetime' => now(),
        ]);
        $this->transaction($memberId, $downline->getKey(), 'agent', 'DASHBOARD-PARTNERSHIP-SALE', 1000000);
        $this->transaction($memberId, 1, 'customer', 'DASHBOARD-CUSTOMER-SALE', 500000);
        Trx::query()->create([
            'trx_code' => 'DASHBOARD-PURCHASE',
            'trx_seller_type' => 'warehouse',
            'trx_seller_id' => 1,
            'trx_buyer_type' => 'distributor',
            'trx_buyer_id' => $memberId,
            'trx_type' => 'stock',
            'trx_total_price' => 750000,
            'trx_grand_total_price' => 750000,
            'trx_shipping_cost' => 25000,
            'trx_grand_total_nett_price' => 775000,
            'trx_status' => 'completed',
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member.id', $memberId)
            ->assertJsonPath('data.member.level.code', 'DST')
            ->assertJsonPath('data.summary.gross_profit', 750000)
            ->assertJsonPath('data.summary.purchases.product_value', 750000)
            ->assertJsonPath('data.summary.sales.product_value', 1500000)
            ->assertJsonMissingPath('data.summary.stock_quantity')
            ->assertJsonMissingPath('data.summary.reward')
            ->assertJsonPath('data.latest_relationships.type', 'downline')
            ->assertJsonPath('data.latest_relationships.results.0.id', $downline->getKey());
    }

    public function test_member_dashboard_gross_profit_never_returns_negative_value(): void
    {
        $account = $this->createMemberAccount();
        $memberId = $account->member_account_member_id;
        $this->transaction($memberId, 1, 'customer', 'DASHBOARD-SMALL-SALE', 1500000);
        Trx::query()->create([
            'trx_code' => 'DASHBOARD-BIGGER-PURCHASE',
            'trx_seller_type' => 'warehouse',
            'trx_seller_id' => 1,
            'trx_buyer_type' => 'distributor',
            'trx_buyer_id' => $memberId,
            'trx_type' => 'stock',
            'trx_total_price' => 2250000,
            'trx_grand_total_price' => 2250000,
            'trx_shipping_cost' => 25000,
            'trx_grand_total_nett_price' => 2275000,
            'trx_status' => 'completed',
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.gross_profit', 0)
            ->assertJsonPath('data.summary.sales.product_value', 1500000)
            ->assertJsonPath('data.summary.purchases.product_value', 2250000);
    }

    private function createMemberAccount(): MemberAccount
    {
        $group = MemberGroup::query()->create([
            'member_group_name' => 'Distributor',
            'member_group_is_active' => 1,
        ]);
        $member = Member::query()->create([
            'member_code' => 'DEV-DISTRIBUTOR',
            'member_member_level_id' => 1,
            'member_name' => 'Distributor Dashboard',
            'member_email' => 'dashboard@example.test',
            'member_mobilephone' => '081200000001',
            'member_status' => 1,
            'member_join_datetime' => now(),
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(),
            'member_account_member_group_id' => $group->getKey(),
            'member_account_username' => 'member.dashboard',
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
    }

    private function transaction(
        int $sellerId,
        int $buyerId,
        string $buyerType,
        string $code,
        int $amount,
    ): Trx {
        return Trx::query()->create([
            'trx_code' => $code,
            'trx_seller_type' => 'distributor',
            'trx_seller_id' => $sellerId,
            'trx_buyer_type' => $buyerType,
            'trx_buyer_id' => $buyerId,
            'trx_type' => 'stock',
            'trx_total_price' => $amount,
            'trx_grand_total_price' => $amount,
            'trx_shipping_cost' => 20000,
            'trx_grand_total_nett_price' => $amount + 20000,
            'trx_status' => 'completed',
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);
    }
}
