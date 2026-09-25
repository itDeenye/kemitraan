<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_notification_requires_an_authenticated_member(): void
    {
        $this->getJson('/api/v1/member/notifications')->assertUnauthorized();
    }

    public function test_member_notification_returns_expiring_products_and_replenishment_needed(): void
    {
        $account = $this->createMemberAccount('distributor');
        $this->actingAs($account, 'member_api');
        $memberId = $account->member_account_member_id;

        $category = $this->createCategory('Skincare');
        $product1 = $this->createProduct($category, ['product_code' => 'SERUM-01', 'product_name' => 'Brightening Serum']);
        $product2 = $this->createProduct($category, ['product_code' => 'TONER-01', 'product_name' => 'Hydrating Toner']);
        $product3 = $this->createProduct($category, ['product_code' => 'CREAM-01', 'product_name' => 'Day Cream']);
        $notificationId = DB::table('notification')->insertGetId([
            'notification_user_type' => 'member',
            'notification_user_id' => $memberId,
            'notification_title' => 'Pre-Order ke Upline Diperlukan',
            'notification_content' => 'Pesanan kekurangan stok.',
            'notification_category' => 'stock',
            'notification_ref_table' => 'trx',
            'notification_ref_id' => 99,
            'notification_is_read' => 0,
            'notification_created_datetime' => now(),
        ]);

        // Set up member stock
        DB::table('member_stock')->insert([
            [
                'member_stock_member_id' => $memberId,
                'member_stock_product_id' => $product1->product_id, // Expiring, has stock
                'member_stock_balance' => 10,
                'member_stock_transfer_in' => 0,
                'member_stock_transfer_out' => 0,
            ],
            [
                'member_stock_member_id' => $memberId,
                'member_stock_product_id' => $product2->product_id, // Need replenishment, 0 stock
                'member_stock_balance' => 0,
                'member_stock_transfer_in' => 0,
                'member_stock_transfer_out' => 0,
            ],
            [
                'member_stock_member_id' => $memberId,
                'member_stock_product_id' => $product3->product_id, // Expiring but no stock (should not appear in expiring)
                'member_stock_balance' => 0,
                'member_stock_transfer_in' => 0,
                'member_stock_transfer_out' => 0,
            ],
        ]);

        // Set up goods receive & details to mock expiration
        $receiveId = DB::table('goods_receive')->insertGetId([
            'goods_receive_number' => 'GR-001',
            'goods_receive_trx_id' => 1,
            'goods_receive_buyer_type' => 'distributor',
            'goods_receive_buyer_id' => $memberId,
            'goods_receive_seller_type' => 'warehouse',
            'goods_receive_seller_id' => 1,
            'goods_receive_status' => 'completed',
            'goods_receive_created_datetime' => now(),
        ]);

        DB::table('goods_receive_detail')->insert([
            [
                'goods_receive_detail_receive_id' => $receiveId,
                'goods_receive_detail_product_id' => $product1->product_id,
                'goods_receive_detail_batch_number' => 'B001',
                'goods_receive_detail_expire_date' => now()->addDays(30)->toDateString(),
                'goods_receive_detail_qty' => 10,
                'goods_receive_detail_created_datetime' => now(),
            ],
            [
                'goods_receive_detail_receive_id' => $receiveId,
                'goods_receive_detail_product_id' => $product3->product_id,
                'goods_receive_detail_batch_number' => 'B003',
                'goods_receive_detail_expire_date' => now()->addDays(15)->toDateString(),
                'goods_receive_detail_qty' => 10,
                'goods_receive_detail_created_datetime' => now(),
            ],
        ]);

        $this->getJson('/api/v1/member/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonCount(1, 'data.transaction_notifications')
            ->assertJsonPath('data.transaction_notifications.0.read_at', null)
            ->assertJsonPath('data.transaction_notifications.0.action.type', 'open_sale_order')
            ->assertJsonPath('data.transaction_notifications.0.action.transaction_id', 99)
            ->assertJsonCount(1, 'data.expiring_products')
            ->assertJsonPath('data.expiring_products.0.product_id', $product1->product_id)
            ->assertJsonCount(2, 'data.replenishment_needed') // product 2 and product 3 have balance 0
            ->assertJsonPath('data.replenishment_needed.0.product_id', $product2->product_id)
            ->assertJsonPath('data.replenishment_needed.0.current_stock', 0)
            ->assertJsonPath('data.replenishment_needed.1.product_id', $product3->product_id);

        $this->postJson("/api/v1/member/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('data.unread_count', 0);
        $this->assertDatabaseHas('notification', [
            'notification_id' => $notificationId,
            'notification_is_read' => 1,
        ]);
    }

    public function test_member_notifications_have_destination_actions(): void
    {
        $account = $this->createMemberAccount('agent');
        $this->actingAs($account, 'member_api');

        DB::table('notification')->insert([
            'notification_user_type' => 'member',
            'notification_user_id' => $account->member_account_member_id,
            'notification_title' => 'Stok produk perlu diperiksa',
            'notification_content' => 'Stok produk telah habis.',
            'notification_category' => 'stock',
            'notification_ref_table' => 'member_stock',
            'notification_ref_id' => 21,
            'notification_is_read' => 0,
            'notification_created_datetime' => now(),
        ]);
        DB::table('notification')->insert([
            [
                'notification_user_type' => 'member',
                'notification_user_id' => $account->member_account_member_id,
                'notification_title' => 'Pembayaran Disetujui',
                'notification_content' => 'Pembayaran pesanan disetujui.',
                'notification_category' => 'payment',
                'notification_ref_table' => 'trx_purchase',
                'notification_ref_id' => 22,
                'notification_is_read' => 0,
                'notification_created_datetime' => now()->subSecond(),
            ],
            [
                'notification_user_type' => 'member',
                'notification_user_id' => $account->member_account_member_id,
                'notification_title' => 'Retur Disetujui',
                'notification_content' => 'Pengajuan retur disetujui.',
                'notification_category' => 'return',
                'notification_ref_table' => 'return',
                'notification_ref_id' => 23,
                'notification_is_read' => 0,
                'notification_created_datetime' => now()->subSeconds(2),
            ],
        ]);

        $this->getJson('/api/v1/member/notifications')
            ->assertOk()
            ->assertJsonPath('data.transaction_notifications.0.action.type', 'open_stock')
            ->assertJsonPath('data.transaction_notifications.0.action.stock_id', 21)
            ->assertJsonPath('data.transaction_notifications.1.action.type', 'open_purchase_order')
            ->assertJsonPath('data.transaction_notifications.1.action.transaction_id', 22)
            ->assertJsonPath('data.transaction_notifications.2.action.type', 'open_return')
            ->assertJsonPath('data.transaction_notifications.2.action.return_id', 23);
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
            'member_code' => 'MEMBER-'.strtoupper($level),
            'member_member_level_id' => $levelId,
            'member_name' => 'Test Member',
            'member_email' => "{$level}@example.test",
            'member_mobilephone' => '08123456789',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->member_id,
            'member_account_member_group_id' => $group->member_group_id,
            'member_account_username' => "test.{$level}",
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
    }

    private function createCategory(string $name, bool $active = true): ProductCategory
    {
        return ProductCategory::query()->create([
            'product_category_name' => $name,
            'product_category_description' => "{$name} products",
            'product_category_is_active' => $active,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createProduct(ProductCategory $category, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'product_product_category_id' => $category->product_category_id,
            'product_code' => 'PRODUCT-01',
            'product_name' => 'Test Product',
            'product_description' => 'Product description',
            'product_customer_price' => 200000,
            'product_weight' => 100,
            'product_unit' => 'pcs',
            'product_is_publish' => 1,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
            'product_input_datetime' => now(),
        ], $attributes));
    }
}
