<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Mail\StockScreeningApprovedMail;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\MemberStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminStockScreeningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_distributor_to_warehouse_order_requires_screening_before_payment(): void
    {
        Mail::fake();
        [$account, $administrator, $product] = $this->createReferences();
        $this->assertFalse(Schema::hasTable('trx_stock_screening'));

        $this->actingAs($account, 'member_api');
        $checkout = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_company_id' => 1,
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'waiting_stock_screening')
            ->assertJsonPath('data.status.label', 'Menunggu Screening Stok')
            ->assertJsonPath('data.stock_screening.status', 'pending')
            ->assertJsonPath('data.actions.can_upload_payment', false)
            ->assertJsonPath('data.actions.waiting_for_stock_screening', true);
        $trxId = (int) $checkout->json('data.id');

        $this->postJson("/api/v1/member/purchases/orders/{$trxId}/payment", [
            'receipt_url' => 'https://cdn.example.test/payment/screening.webp',
        ])->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Bukti pembayaran dapat dikirim setelah screening stok perusahaan disetujui.',
                'error_code' => 'process_error',
            ]);

        $this->actingAs($administrator, 'admin_api');
        $this->getJson('/api/v1/admin/transactions/orders?filter[status]=waiting_stock_screening&filter[can_approve_stock_screening]=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $trxId)
            ->assertJsonPath('data.results.0.status', 'waiting_stock_screening')
            ->assertJsonPath('data.results.0.actions.can_approve_stock_screening', true);
        $this->getJson("/api/v1/admin/transactions/orders/{$trxId}")
            ->assertOk()
            ->assertJsonPath('data.stock_screening.status.code', 'pending')
            ->assertJsonPath('data.stock_screening.member_stocks.0.balance', 5)
            ->assertJsonPath('data.stock_screening.member_stocks.0.transfer_in', 1)
            ->assertJsonPath('data.stock_screening.member_stocks.0.ordered_quantity', 1);

        $this->postJson("/api/v1/admin/transactions/orders/{$trxId}/stock-screening/approve", [
            'note' => 'Stok distributor masih sesuai.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'waiting_payment')
            ->assertJsonPath('data.stock_screening', null)
            ->assertJsonPath('data.actions.can_approve_stock_screening', false);

        Mail::assertSent(StockScreeningApprovedMail::class, function (StockScreeningApprovedMail $mail) use ($trxId): bool {
            $this->assertStringContainsString('Screening Stok Disetujui', $mail->render());
            $this->assertStringContainsString('Lihat dan Bayar Pesanan', $mail->render());

            return $mail->hasTo('distributor@example.test')
                && (int) $mail->trx->getKey() === $trxId;
        });
        $this->assertDatabaseHas('notification', [
            'notification_user_type' => 'member',
            'notification_user_id' => $account->member_account_member_id,
            'notification_title' => 'Screening Stok Disetujui',
            'notification_ref_table' => 'trx_purchase',
            'notification_ref_id' => $trxId,
            'notification_is_read' => 0,
        ]);

        $this->actingAs($account, 'member_api');
        $this->postJson("/api/v1/member/purchases/orders/{$trxId}/payment", [
            'receipt_url' => 'https://cdn.example.test/payment/screening.webp',
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'submitted');
    }

    public function test_rejected_screening_cancels_transaction_and_releases_warehouse_reservation(): void
    {
        Mail::fake();
        [$account, $administrator, $product] = $this->createReferences();

        $this->actingAs($account, 'member_api');
        $trxId = (int) $this->postJson('/api/v1/member/purchases/orders', [
            'bank_company_id' => 1,
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 1]],
        ])->assertOk()->json('data.id');

        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 1,
            'warehouse_stock_transfer_out' => 1,
        ]);

        $this->actingAs($administrator, 'admin_api');
        $this->postJson("/api/v1/admin/transactions/orders/{$trxId}/stock-screening/reject", [
            'note' => 'Stok distributor belum memenuhi hasil screening.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.stock_screening', null);

        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 2,
            'warehouse_stock_transfer_out' => 0,
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => 1,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 5,
            'member_stock_transfer_in' => 0,
        ]);
        $this->assertDatabaseHas('trx', ['trx_id' => $trxId, 'trx_status' => 'cancelled']);
        $this->assertDatabaseHas('notification', [
            'notification_user_type' => 'member',
            'notification_user_id' => $account->member_account_member_id,
            'notification_title' => 'Screening Stok Ditolak',
            'notification_ref_table' => 'trx_purchase',
            'notification_ref_id' => $trxId,
            'notification_is_read' => 0,
        ]);
        Mail::assertNothingSent();
    }

    /** @return array{MemberAccount, SiteAdministrator, Product} */
    private function createReferences(): array
    {
        DB::table('member_level')->updateOrInsert(
            ['member_level_id' => 1],
            [
                'member_level_code' => 'DST',
                'member_level_name' => 'Distributor',
                'member_level_description' => 'Distributor',
                'member_level_min_order' => 0,
                'member_level_point_value' => 1,
                'member_level_sort_order' => 1,
                'member_level_is_active' => 1,
            ],
        );
        $member = Member::query()->create([
            'member_id' => 1,
            'member_code' => '0001/0000/0000',
            'member_member_level_id' => 1,
            'member_parent_member_id' => 0,
            'member_name' => 'Distributor Screening',
            'member_email' => 'distributor@example.test',
            'member_mobilephone' => '081234567890',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
        $memberGroup = MemberGroup::query()->create([
            'member_group_name' => 'Mitra',
            'member_group_description' => 'Mitra',
            'member_group_is_active' => 1,
        ]);
        $account = MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(),
            'member_account_member_group_id' => $memberGroup->getKey(),
            'member_account_username' => 'distributor.screening',
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);

        DB::table('ref_bank')->insert([
            'bank_id' => 1,
            'bank_code' => 'DNY',
            'bank_name' => 'Bank DNY',
            'bank_is_active' => 1,
        ]);
        DB::table('bank_company')->insert([
            'bank_company_id' => 1,
            'bank_company_type' => 'company',
            'bank_company_bank_id' => 1,
            'bank_company_bank_acc_name' => 'PT DNY',
            'bank_company_bank_acc_number' => '1234567890',
            'bank_company_bank_is_active' => 1,
        ]);
        DB::table('warehouse')->insert([
            'warehouse_id' => 1,
            'warehouse_name' => 'Gudang Pusat',
            'warehouse_legal_name' => 'PT DNY',
            'warehouse_address' => 'Jakarta',
            'warehouse_phone' => '0211234567',
            'warehouse_is_active' => 1,
        ]);
        $category = ProductCategory::query()->create([
            'product_category_name' => 'Skincare',
            'product_category_description' => 'Produk screening',
            'product_category_is_active' => 1,
        ]);
        $product = Product::query()->create([
            'product_product_category_id' => $category->getKey(),
            'product_code' => 'SCR-001',
            'product_name' => 'Produk Screening',
            'product_bpom_number' => 'NA18250100999',
            'product_customer_price' => 150000,
            'product_weight' => 100,
            'product_unit' => 'pcs',
            'product_is_publish' => 1,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
        ]);
        ProductPrice::query()->create([
            'product_price_product_id' => $product->getKey(),
            'product_price_member_level_id' => 1,
            'product_price_value' => 100000,
        ]);
        DB::table('warehouse_stock')->insert([
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 2,
            'warehouse_stock_transfer_in' => 0,
            'warehouse_stock_transfer_out' => 0,
        ]);
        MemberStock::query()->create([
            'member_stock_member_id' => $member->getKey(),
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 5,
            'member_stock_transfer_in' => 0,
            'member_stock_transfer_out' => 0,
        ]);

        $administratorGroup = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Super Administrator',
            'administrator_group_type' => 'superuser',
            'administrator_group_is_active' => 1,
        ]);
        $administrator = SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $administratorGroup->getKey(),
            'administrator_username' => 'screening.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Admin Screening',
            'administrator_email' => 'screening.admin@example.test',
            'administrator_is_active' => 1,
        ]);

        return [$account, $administrator, $product];
    }
}
