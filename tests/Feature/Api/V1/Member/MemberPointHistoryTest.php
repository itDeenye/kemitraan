<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\MemberPointTransaction;
use App\Models\Trx;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MemberPointHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_point_history_requires_authentication(): void
    {
        $this->getJson('/api/v1/member/rewards/points')->assertUnauthorized();
    }

    public function test_history_is_scoped_to_member_and_period_and_matches_realtime_points(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 16)->startOfDay());
        $account = $this->account('SELF');
        $other = $this->account('OTHER');
        $first = $this->record($account, 'FIRST', '2026-10-01 09:00:00', 3, 6, 9);
        $this->record($account, 'ZERO', '2026-10-02 10:00:00', 5, 0, 2);
        $this->record($account, 'LAST-MONTH', '2026-09-01 09:00:00', 100, 100, 200);
        $this->record($other, 'PRIVATE', '2026-10-03 09:00:00', 50, 50, 50);
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/rewards/points?year=2026&month=10')
            ->assertOk()
            ->assertJsonPath('summary.monthly_points', 6)
            ->assertJsonPath('summary.annual_points', 11)
            ->assertJsonPath('summary.transaction_count', 2)
            ->assertJsonPath('data.pagination.total_data', 2)
            ->assertJsonCount(2, 'data.results')
            ->assertJsonPath('data.results.0.transaction.code', 'ZERO')
            ->assertJsonPath('data.results.0.monthly_points', 0)
            ->assertJsonPath('data.results.1.transaction.id', $first->trx_id)
            ->assertJsonPath('data.results.1.quantity', 3)
            ->assertJsonPath('data.results.1.monthly_points', 6)
            ->assertJsonPath('data.results.1.annual_points', 9)
            ->assertJsonPath('available_years', [2026]);

        $this->getJson('/api/v1/member/rewards/monthly/growth?year=2026&month=10')
            ->assertOk()
            ->assertJsonPath('data.summary.total_points', 6);
    }

    public function test_legacy_points_use_recorded_quantity_and_summary_covers_all_pages(): void
    {
        $account = $this->account('SELF');
        $first = $this->record($account, 'LEGACY', '2025-12-01 09:00:00', 4, null, null);
        $second = $this->record($account, 'SECOND', '2026-01-01 09:00:00', 3, 6, 7);
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/rewards/points?limit=1')
            ->assertOk()
            ->assertJsonPath('data.results.0.transaction.id', $second->trx_id)
            ->assertJsonPath('data.pagination.total_data', 2)
            ->assertJsonPath('summary.monthly_points', 10)
            ->assertJsonPath('summary.annual_points', 11)
            ->assertJsonPath('available_years', [2026, 2025]);

        $this->getJson('/api/v1/member/rewards/points?limit=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.results.0.transaction.id', $first->trx_id)
            ->assertJsonPath('data.results.0.monthly_points', 4)
            ->assertJsonPath('data.results.0.annual_points', 4)
            ->assertJsonPath('summary.monthly_points', 10);
    }

    public function test_empty_history_returns_zero_summary_and_rejects_invalid_filters(): void
    {
        $this->actingAs($this->account('SELF'), 'member_api');
        $this->getJson('/api/v1/member/rewards/points')
            ->assertOk()
            ->assertJsonCount(0, 'data.results')
            ->assertJsonPath('summary.monthly_points', 0)
            ->assertJsonPath('summary.annual_points', 0)
            ->assertJsonPath('summary.transaction_count', 0);

        $this->getJson('/api/v1/member/rewards/points?month=13&limit=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['month', 'limit']);
    }

    public function test_removed_point_ledger_no_longer_appears_in_realtime_history(): void
    {
        $account = $this->account('SELF');
        $order = $this->record($account, 'CANCELLED', '2026-10-01 09:00:00', 3, 6, 9);
        MemberPointTransaction::query()->where('member_point_transaction_trx_id', $order->trx_id)->delete();
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/rewards/points')
            ->assertOk()
            ->assertJsonCount(0, 'data.results')
            ->assertJsonPath('summary.monthly_points', 0);
    }

    private function account(string $code): MemberAccount
    {
        $member = Member::query()->create([
            'member_code' => $code,
            'member_member_level_id' => 1,
            'member_name' => $code,
            'member_email' => strtolower($code).'@example.test',
            'member_mobilephone' => '08123456789',
            'member_status' => 1,
            'member_join_datetime' => now(),
        ]);
        $group = MemberGroup::query()->firstOrCreate(['member_group_name' => 'History'], ['member_group_is_active' => 1]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(),
            'member_account_member_group_id' => $group->getKey(),
            'member_account_username' => strtolower($code),
            'member_account_password' => 'secret',
            'member_account_pin' => '',
        ]);
    }

    private function record(MemberAccount $account, string $code, string $approvedAt, int $quantity, ?int $monthly, ?int $annual): Trx
    {
        $trx = Trx::query()->create([
            'trx_code' => $code,
            'trx_buyer_id' => $account->member_account_member_id,
            'trx_buyer_type' => 'distributor',
            'trx_seller_type' => 'warehouse',
            'trx_seller_id' => 1,
            'trx_type' => 'stock',
            'trx_total_price' => 100000,
            'trx_grand_total_price' => 100000,
            'trx_grand_total_nett_price' => 100000,
            'trx_status' => 'processing',
            'trx_datetime' => $approvedAt,
            'trx_status_datetime' => $approvedAt,
        ]);
        MemberPointTransaction::query()->create([
            'member_point_transaction_member_id' => $account->member_account_member_id,
            'member_point_transaction_trx_id' => $trx->getKey(),
            'member_point_transaction_quantity' => $quantity,
            'member_point_transaction_monthly_points' => $monthly,
            'member_point_transaction_annual_points' => $annual,
            'member_point_transaction_year' => (int) substr($approvedAt, 0, 4),
            'member_point_transaction_month' => (int) substr($approvedAt, 5, 2),
            'member_point_transaction_approved_datetime' => $approvedAt,
        ]);

        return $trx;
    }
}
