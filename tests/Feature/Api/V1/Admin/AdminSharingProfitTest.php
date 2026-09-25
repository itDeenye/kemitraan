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

class AdminSharingProfitTest extends TestCase
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

    private function createTrxAndSpreadPayment(
        Member $upline,
        Member $buyer,
        string $status = 'completed',
        string $spreadStatus = 'submitted',
        ?string $receiptUrl = 'https://cdn.example.test/spread-payment.webp',
    ): array {
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
            'trx_status' => $status,
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);

        $spread = TrxSpreadPayment::query()->create([
            'trx_spread_payment_trx_id' => $trx->trx_id,
            'trx_spread_payment_upline_id' => $upline->member_id,
            'trx_spread_payment_member_id' => $buyer->member_id,
            'trx_spread_payment_percentage' => 10.00,
            'trx_spread_payment_amount' => 10000,
            'trx_spread_payment_receipt_file' => $receiptUrl,
            'trx_spread_payment_transfer_datetime' => $receiptUrl ? now() : null,
            'trx_spread_payment_status' => $spreadStatus,
            'trx_spread_payment_created_datetime' => now(),
        ]);

        return [$trx, $spread];
    }

    public function test_administrator_can_list_sharing_profits(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $upline = $this->createMitra('U-01', 'Upline 1');
        $buyer = $this->createMitra('B-01', 'Buyer 1');

        $this->createTrxAndSpreadPayment($upline, $buyer, 'completed');
        $this->createTrxAndSpreadPayment($upline, $buyer, 'rejected'); // Should not be summed

        $this->getJson('/api/v1/admin/rewards/sharing-profits')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.results.0.upline.id', $upline->member_id)
            ->assertJsonPath('data.results.0.total_amount', 10000)
            ->assertJsonPath('data.results.0.transaction_count', 1)
            ->assertJsonPath('data.results.0.submitted_count', 1)
            ->assertJsonPath('data.results.0.approved_count', 0)
            ->assertJsonPath('data.results.0.status', 'submitted')
            ->assertJsonPath('data.results.0.can_approve', true)
            ->assertJsonPath('data.results.0.can_transfer', false)
            ->assertJsonStructure([
                'data' => [
                    'results' => [
                        '*' => [
                            'upline' => ['id', 'name', 'code'],
                            'total_trx_price',
                            'total_amount',
                            'transaction_count',
                            'submitted_count',
                            'approved_count',
                            'status',
                            'can_approve',
                            'can_transfer',
                        ],
                    ],
                    'pagination',
                ],
            ]);
    }

    public function test_administrator_can_view_sharing_profit_details(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $upline = $this->createMitra('U-01', 'Upline 1');
        $buyer = $this->createMitra('B-01', 'Buyer 1');

        [$trx, $spread] = $this->createTrxAndSpreadPayment($upline, $buyer, 'completed');
        $spread->update([
            'trx_spread_payment_receipt_file' => 'https://cdn.example.test/spread-payment.webp',
        ]);

        $this->getJson("/api/v1/admin/rewards/sharing-profits/{$upline->member_id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.results.0.id', $spread->trx_spread_payment_id)
            ->assertJsonPath('data.results.0.receipt_url', 'https://cdn.example.test/spread-payment.webp')
            ->assertJsonStructure([
                'data' => [
                    'results' => [
                        '*' => [
                            'id',
                            'submitted_datetime',
                            'approved_datetime',
                            'paid_datetime',
                            'trx_code',
                            'buyer_name',
                            'trx_price',
                            'amount',
                            'receipt_url',
                            'note',
                            'status',
                        ],
                    ],
                ],
            ]);
    }

    public function test_administrator_can_bulk_approve_sharing_profits(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $upline1 = $this->createMitra('U-01', 'Upline 1');
        $upline2 = $this->createMitra('U-02', 'Upline 2');
        $buyer = $this->createMitra('B-01', 'Buyer 1');

        [$trx1, $spread1] = $this->createTrxAndSpreadPayment($upline1, $buyer, 'completed');
        [$trx2, $spread2] = $this->createTrxAndSpreadPayment($upline2, $buyer, 'completed');

        $this->postJson('/api/v1/admin/rewards/sharing-profits/approve', [
            'upline_ids' => [$upline1->member_id, $upline2->member_id],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.processed_count', 2);

        $this->assertDatabaseHas('trx_spread_payment', [
            'trx_spread_payment_id' => $spread1->trx_spread_payment_id,
            'trx_spread_payment_status' => 'approved',
        ]);

        $this->assertDatabaseHas('trx_spread_payment', [
            'trx_spread_payment_id' => $spread2->trx_spread_payment_id,
            'trx_spread_payment_status' => 'approved',
        ]);
    }

    public function test_submitted_sharing_profit_requires_separate_admin_approval(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $upline = $this->createMitra('U-01', 'Upline 1');
        $buyer = $this->createMitra('B-01', 'Buyer 1');
        [$trx, $spread] = $this->createTrxAndSpreadPayment($upline, $buyer, 'completed');
        $spread->update([
            'trx_spread_payment_status' => 'submitted',
            'trx_spread_payment_receipt_file' => 'https://cdn.example.test/spread.webp',
            'trx_spread_payment_transfer_datetime' => now(),
        ]);

        $this->getJson('/api/v1/admin/rewards/sharing-profits')
            ->assertOk()
            ->assertJsonPath('data.results.0.upline.id', $upline->getKey())
            ->assertJsonPath('data.results.0.total_amount', 10000);

        $this->postJson('/api/v1/admin/rewards/sharing-profits/approve', [
            'upline_ids' => [$upline->getKey()],
        ])->assertOk();

        $this->assertDatabaseHas('trx_spread_payment', [
            'trx_spread_payment_id' => $spread->getKey(),
            'trx_spread_payment_status' => 'approved',
        ]);

        $this->getJson('/api/v1/admin/rewards/sharing-profits')
            ->assertOk()
            ->assertJsonPath('data.results.0.upline.id', $upline->getKey())
            ->assertJsonPath('data.results.0.status', 'approved')
            ->assertJsonPath('data.results.0.can_approve', false)
            ->assertJsonPath('data.results.0.can_transfer', true);
    }

    public function test_administrator_can_record_direct_sharing_profit_transfer(): void
    {
        $administrator = $this->createAdministrator();
        $this->actingAs($administrator, 'admin_api');
        $upline = $this->createMitra('U-01', 'Upline 1');
        $buyer = $this->createMitra('B-01', 'Buyer 1');
        [$trx, $spread] = $this->createTrxAndSpreadPayment($upline, $buyer, 'completed');
        $spread->update([
            'trx_spread_payment_status' => 'approved',
            'trx_spread_payment_approved_by' => $administrator->getKey(),
            'trx_spread_payment_approved_datetime' => now(),
        ]);

        $this->postJson('/api/v1/admin/rewards/sharing-profits/transfer', [
            'upline_ids' => [$upline->getKey()],
            'note' => 'Transfer bank development',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.processed_count', 1);

        $this->assertDatabaseHas('trx_spread_payment', [
            'trx_spread_payment_id' => $spread->getKey(),
            'trx_spread_payment_status' => 'paid',
            'trx_spread_payment_paid_by' => $administrator->getKey(),
            'trx_spread_payment_note' => 'Transfer bank development',
        ]);
    }

    public function test_administrator_cannot_transfer_unapproved_sharing_profit(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $upline = $this->createMitra('U-01', 'Upline 1');
        $buyer = $this->createMitra('B-01', 'Buyer 1');
        $this->createTrxAndSpreadPayment($upline, $buyer, 'completed');

        $this->postJson('/api/v1/admin/rewards/sharing-profits/transfer', [
            'upline_ids' => [$upline->getKey()],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');
    }

    public function test_administrator_cannot_transfer_approved_profit_from_cancelled_transaction(): void
    {
        $administrator = $this->createAdministrator();
        $this->actingAs($administrator, 'admin_api');
        $upline = $this->createMitra('U-01', 'Upline 1');
        $buyer = $this->createMitra('B-01', 'Buyer 1');
        [, $spread] = $this->createTrxAndSpreadPayment(
            $upline,
            $buyer,
            'cancelled',
            'approved',
        );

        $this->postJson('/api/v1/admin/rewards/sharing-profits/transfer', [
            'upline_ids' => [$upline->getKey()],
            'note' => 'Tidak boleh ditransfer',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        $this->assertDatabaseHas('trx_spread_payment', [
            'trx_spread_payment_id' => $spread->getKey(),
            'trx_spread_payment_status' => 'approved',
        ]);
    }

    public function test_pending_spread_without_receipt_is_not_listed_or_approved(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $upline = $this->createMitra('U-01', 'Upline 1');
        $buyer = $this->createMitra('B-01', 'Buyer 1');
        [, $spread] = $this->createTrxAndSpreadPayment(
            $upline,
            $buyer,
            'completed',
            'pending',
            null,
        );

        $this->getJson('/api/v1/admin/rewards/sharing-profits')
            ->assertOk()
            ->assertJsonCount(0, 'data.results');

        $this->postJson('/api/v1/admin/rewards/sharing-profits/approve', [
            'upline_ids' => [$upline->getKey()],
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        $this->assertDatabaseHas('trx_spread_payment', [
            'trx_spread_payment_id' => $spread->getKey(),
            'trx_spread_payment_status' => 'pending',
        ]);
    }
}
