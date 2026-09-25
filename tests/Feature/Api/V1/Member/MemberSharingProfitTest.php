<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\Trx;
use App\Models\TrxSpreadPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberSharingProfitTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_sharing_profit_requires_authentication(): void
    {
        $this->getJson('/api/v1/member/rewards/sharing-profits')->assertUnauthorized();
    }

    public function test_member_can_view_only_their_sharing_profit(): void
    {
        $account = $this->createMemberAccount('UPLINE', 'Upline');
        $otherAccount = $this->createMemberAccount('OTHER', 'Other Upline');
        $buyer = $this->createMember('BUYER', 'Buyer');
        $own = $this->spreadPayment($account->member, $buyer, 'OWN-SPREAD', 'approved');
        $this->spreadPayment($otherAccount->member, $buyer, 'OTHER-SPREAD', 'paid');
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/rewards/sharing-profits')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $own->getKey())
            ->assertJsonPath('data.results.0.status', 'approved')
            ->assertJsonPath('data.results.0.buyer.id', $buyer->getKey());

        $this->getJson("/api/v1/member/rewards/sharing-profits/{$own->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.transaction.code', 'OWN-SPREAD');
    }

    public function test_member_cannot_view_other_member_sharing_profit_detail(): void
    {
        $account = $this->createMemberAccount('UPLINE', 'Upline');
        $otherAccount = $this->createMemberAccount('OTHER', 'Other Upline');
        $buyer = $this->createMember('BUYER', 'Buyer');
        $other = $this->spreadPayment($otherAccount->member, $buyer, 'OTHER-SPREAD', 'paid');
        $this->actingAs($account, 'member_api');

        $this->getJson("/api/v1/member/rewards/sharing-profits/{$other->getKey()}")
            ->assertNotFound();
    }

    private function createMemberAccount(string $code, string $name): MemberAccount
    {
        $member = $this->createMember($code, $name);
        $group = MemberGroup::query()->create([
            'member_group_name' => $code,
            'member_group_is_active' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(),
            'member_account_member_group_id' => $group->getKey(),
            'member_account_username' => strtolower($code),
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
    }

    private function createMember(string $code, string $name): Member
    {
        return Member::query()->create([
            'member_code' => $code,
            'member_member_level_id' => 1,
            'member_name' => $name,
            'member_email' => strtolower($code).'@example.test',
            'member_mobilephone' => '08123456789',
            'member_status' => 1,
            'member_join_datetime' => now(),
        ]);
    }

    private function spreadPayment(
        Member $upline,
        Member $buyer,
        string $trxCode,
        string $status,
    ): TrxSpreadPayment {
        $trx = Trx::query()->create([
            'trx_code' => $trxCode,
            'trx_seller_type' => 'distributor',
            'trx_seller_id' => $upline->getKey(),
            'trx_buyer_type' => 'agent',
            'trx_buyer_id' => $buyer->getKey(),
            'trx_type' => 'stock',
            'trx_total_price' => 1000000,
            'trx_grand_total_price' => 1000000,
            'trx_grand_total_nett_price' => 1000000,
            'trx_status' => 'completed',
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);

        return TrxSpreadPayment::query()->create([
            'trx_spread_payment_trx_id' => $trx->getKey(),
            'trx_spread_payment_upline_id' => $upline->getKey(),
            'trx_spread_payment_member_id' => $buyer->getKey(),
            'trx_spread_payment_percentage' => 10,
            'trx_spread_payment_amount' => 100000,
            'trx_spread_payment_status' => $status,
            'trx_spread_payment_approved_datetime' => $status !== 'pending' ? now() : null,
            'trx_spread_payment_paid_datetime' => $status === 'paid' ? now() : null,
            'trx_spread_payment_created_datetime' => now(),
        ]);
    }
}
