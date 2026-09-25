<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Member;
use App\Models\MemberLevel;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Models\Trx;
use App\Models\TrxSpreadPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSharingProfitHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function createAdministrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->firstOrCreate(
            ['administrator_group_id' => 1],
            ['administrator_group_title' => 'Super Admin', 'administrator_group_is_active' => 1]
        );

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->administrator_group_id,
            'administrator_username' => 'admin.test',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Admin Test',
            'administrator_email' => 'admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }

    private function createMitra(string $code, string $name): Member
    {
        MemberLevel::query()->firstOrCreate(
            ['member_level_id' => 1],
            ['member_level_code' => 'DST', 'member_level_name' => 'Distributor', 'member_level_is_active' => 1]
        );

        return Member::query()->create([
            'member_member_level_id' => 1,
            'member_code' => $code,
            'member_name' => $name,
            'member_email' => strtolower($code).'@example.com',
            'member_mobilephone' => '0812345',
            'member_status' => 1,
            'member_gender' => 'Perempuan',
            'member_join_datetime' => now(),
        ]);
    }

    private function createTrxAndSpreadPayment(Member $upline, Member $buyer, string $status = 'paid'): array
    {
        $trx = Trx::query()->create([
            'trx_code' => 'PO-'.uniqid(),
            'trx_buyer_type' => 'agent',
            'trx_buyer_id' => $buyer->member_id,
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
        ]);

        $spread = TrxSpreadPayment::query()->create([
            'trx_spread_payment_trx_id' => $trx->trx_id,
            'trx_spread_payment_upline_id' => $upline->member_id,
            'trx_spread_payment_member_id' => $buyer->member_id,
            'trx_spread_payment_percentage' => 10.00,
            'trx_spread_payment_amount' => 10000,
            'trx_spread_payment_status' => $status,
            'trx_spread_payment_paid_datetime' => $status === 'paid' ? now() : null,
            'trx_spread_payment_created_datetime' => now(),
        ]);

        return [$trx, $spread];
    }

    public function test_administrator_can_list_sharing_profit_history(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $upline = $this->createMitra('U-01', 'Upline 1');
        $buyer = $this->createMitra('B-01', 'Buyer 1');

        $this->createTrxAndSpreadPayment($upline, $buyer, 'paid');
        $this->createTrxAndSpreadPayment($upline, $buyer, 'pending'); // Should not be in history

        $this->getJson('/api/v1/admin/rewards/sharing-profits/history')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total_data', 1)
            ->assertJsonStructure([
                'data' => [
                    'results' => [
                        '*' => [
                            'id',
                            'paid_datetime',
                            'trx_code',
                            'mitra_name',
                            'trx_price',
                            'amount',
                            'note',
                            'status',
                        ],
                    ],
                    'pagination',
                ],
            ]);
    }

    public function test_administrator_can_view_sharing_profit_history_details(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $upline = $this->createMitra('U-01', 'Upline 1');
        $buyer = $this->createMitra('B-01', 'Buyer 1');

        [$trx, $spread] = $this->createTrxAndSpreadPayment($upline, $buyer, 'paid');
        $spread->update([
            'trx_spread_payment_receipt_file' => 'https://cdn.example.test/spread-payment-paid.webp',
        ]);

        $this->getJson("/api/v1/admin/rewards/sharing-profits/history/{$spread->trx_spread_payment_id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $spread->trx_spread_payment_id)
            ->assertJsonPath('data.receipt_url', 'https://cdn.example.test/spread-payment-paid.webp')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'paid_datetime',
                    'trx_code',
                    'mitra_name',
                    'trx_price',
                    'amount',
                    'receipt_url',
                    'note',
                    'status',
                ],
            ]);
    }
}
