<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\MemberLevel;
use App\Models\Trx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberTransactionSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_summaries_require_authentication(): void
    {
        $this->getJson('/api/v1/member/purchases/orders/summary')->assertUnauthorized();
        $this->getJson('/api/v1/member/sales/orders/summary')->assertUnauthorized();
    }

    public function test_member_can_view_purchase_and_sales_status_summaries(): void
    {
        $account = $this->createMemberAccount();
        $memberId = $account->member_account_member_id;

        foreach (['waiting_payment', 'processing', 'shipped', 'completed'] as $index => $status) {
            Trx::query()->create($this->transactionData(
                "PURCHASE-{$index}",
                'warehouse',
                1,
                'distributor',
                $memberId,
                'stock',
                $status,
            ));
        }

        foreach (['waiting_payment', 'waiting_payment_approval', 'processing', 'shipped', 'completed'] as $index => $status) {
            Trx::query()->create($this->transactionData(
                "SALE-{$index}",
                'distributor',
                $memberId,
                'customer',
                900 + $index,
                'retail',
                $status,
            ));
        }

        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/purchases/orders/summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.purchases.total', 4)
            ->assertJsonPath('data.purchases.action_required', 1)
            ->assertJsonPath('data.purchases.waiting_payment', 1)
            ->assertJsonPath('data.purchases.processing', 1)
            ->assertJsonPath('data.purchases.delivery', 1)
            ->assertJsonPath('data.purchases.completed', 1)
            ->assertJsonPath('data.sales.total', 5)
            ->assertJsonPath('data.sales.action_required', 2)
            ->assertJsonPath('data.sales.waiting_payment', 1)
            ->assertJsonPath('data.sales.waiting_payment_approval', 1)
            ->assertJsonPath('data.sales.processing', 1)
            ->assertJsonPath('data.sales.delivery', 1)
            ->assertJsonPath('data.sales.completed', 1)
            ->assertJsonPath('data.goods_receipts.ready_to_receive', 0)
            ->assertJsonMissingPath('data.total')
            ->assertJsonMissingPath('data.goods_receipt');

        $this->getJson('/api/v1/member/sales/orders/summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.action_required', 2)
            ->assertJsonPath('data.waiting_payment', 1)
            ->assertJsonPath('data.waiting_payment_approval', 1)
            ->assertJsonPath('data.processing', 1)
            ->assertJsonPath('data.delivery', 1)
            ->assertJsonPath('data.completed', 1);
    }

    private function createMemberAccount(): MemberAccount
    {
        MemberLevel::query()->updateOrCreate(
            ['member_level_id' => 1],
            [
                'member_level_code' => 'DST',
                'member_level_name' => 'Distributor',
                'member_level_description' => 'Distributor',
                'member_level_min_order' => 0,
                'member_level_point_value' => 5000,
                'member_level_sort_order' => 1,
                'member_level_is_active' => 1,
            ],
        );
        $group = MemberGroup::query()->create([
            'member_group_name' => 'Distributor',
            'member_group_description' => 'Test group',
            'member_group_is_active' => 1,
        ]);
        $member = Member::query()->create([
            'member_code' => 'DNY-SUMMARY-001',
            'member_member_level_id' => 1,
            'member_parent_member_id' => 0,
            'member_name' => 'Summary Member',
            'member_email' => 'summary@example.test',
            'member_mobilephone' => '08123456789',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(),
            'member_account_member_group_id' => $group->getKey(),
            'member_account_username' => 'summary.member',
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
    }

    /** @return array<string, mixed> */
    private function transactionData(
        string $code,
        string $sellerType,
        int $sellerId,
        string $buyerType,
        int $buyerId,
        string $type,
        string $status,
    ): array {
        return [
            'trx_code' => $code,
            'trx_seller_type' => $sellerType,
            'trx_seller_id' => $sellerId,
            'trx_buyer_type' => $buyerType,
            'trx_buyer_id' => $buyerId,
            'trx_type' => $type,
            'trx_total_price' => 100000,
            'trx_grand_total_price' => 100000,
            'trx_grand_total_nett_price' => 100000,
            'trx_status' => $status,
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ];
    }
}
