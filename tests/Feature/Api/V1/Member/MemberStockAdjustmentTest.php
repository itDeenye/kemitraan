<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\MemberLevel;
use App\Models\MemberStock;
use App\Models\MemberStockAdjustment;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberStockAdjustmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_stock_adjustment_requires_authentication(): void
    {
        $this->getJson('/api/v1/member/inventory/adjustments')->assertUnauthorized();
        $this->postJson('/api/v1/member/inventory/adjustments')->assertUnauthorized();
    }

    public function test_agent_and_reseller_use_the_same_stock_adjustment_api(): void
    {
        foreach (['agent', 'reseller'] as $level) {
            $account = $this->createMemberAccount($level);
            $product = $this->createProduct("PRODUCT-{$level}");
            $stock = MemberStock::query()->create([
                'member_stock_member_id' => $account->member_account_member_id,
                'member_stock_product_id' => $product->getKey(),
                'member_stock_balance' => 10,
                'member_stock_transfer_in' => 0,
                'member_stock_transfer_out' => 0,
            ]);
            $this->actingAs($account, 'member_api');

            $response = $this->postJson('/api/v1/member/inventory/adjustments', [
                'product_id' => $product->getKey(),
                'qty' => 3,
                'reason' => 'Kemasan rusak saat penyimpanan.',
            ])->assertCreated()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.total_items', 1)
                ->assertJsonPath('data.total_quantity', 3)
                ->assertJsonPath('data.details.0.product.id', $product->getKey())
                ->assertJsonPath('data.details.0.type', 'out');

            $adjustmentId = $response->json('data.id');
            $this->assertSame(7, (int) $stock->refresh()->member_stock_balance);

            $this->getJson('/api/v1/member/inventory/adjustments')
                ->assertOk()
                ->assertJsonCount(1, 'data.results')
                ->assertJsonPath('data.results.0.id', $adjustmentId)
                ->assertJsonPath('data.results.0.total_quantity', 3);

            $this->getJson("/api/v1/member/inventory/adjustments/{$adjustmentId}")
                ->assertOk()
                ->assertJsonPath('data.details.0.note', 'Kemasan rusak saat penyimpanan.');
        }
    }

    public function test_distributor_cannot_use_member_stock_adjustment_api(): void
    {
        $this->actingAs($this->createMemberAccount('distributor'), 'member_api');

        $this->getJson('/api/v1/member/inventory/adjustments')->assertForbidden();
        $this->postJson('/api/v1/member/inventory/adjustments', [
            'product_id' => 1,
            'qty' => 1,
            'reason' => 'Rusak',
        ])->assertForbidden();
    }

    public function test_member_cannot_adjust_more_than_available_stock_or_view_another_members_adjustment(): void
    {
        $agent = $this->createMemberAccount('agent');
        $reseller = $this->createMemberAccount('reseller');
        $product = $this->createProduct('PRODUCT-LIMIT');
        MemberStock::query()->create([
            'member_stock_member_id' => $agent->member_account_member_id,
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 2,
            'member_stock_transfer_in' => 0,
            'member_stock_transfer_out' => 0,
        ]);

        $this->actingAs($agent, 'member_api');
        $this->postJson('/api/v1/member/inventory/adjustments', [
            'product_id' => $product->getKey(),
            'qty' => 3,
            'reason' => 'Barang hilang.',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Saldo stok tidak mencukupi untuk penyesuaian.');

        $otherAdjustment = MemberStockAdjustment::query()->create([
            'stock_adjustment_administrator_id' => 0,
            'stock_adjustment_member_id' => $reseller->member_account_member_id,
            'stock_adjustment_code' => 'ADJ/999999/TEST01',
            'stock_adjustment_note' => 'Milik member lain.',
            'stock_adjustment_datetime' => now(),
        ]);

        $this->getJson("/api/v1/member/inventory/adjustments/{$otherAdjustment->getKey()}")
            ->assertNotFound();
    }

    private function createMemberAccount(string $level): MemberAccount
    {
        $levelId = match ($level) {
            'distributor' => 1,
            'agent' => 2,
            'reseller' => 3,
        };
        $levelCode = match ($level) {
            'distributor' => 'DST',
            'agent' => 'AGT',
            'reseller' => 'RSL',
        };
        $memberLevel = MemberLevel::query()->updateOrCreate(
            ['member_level_id' => $levelId],
            [
                'member_level_code' => $levelCode,
                'member_level_name' => ucfirst($level),
                'member_level_description' => ucfirst($level),
                'member_level_min_order' => 0,
                'member_level_point_value' => 0,
                'member_level_sort_order' => $levelId,
                'member_level_is_active' => 1,
            ],
        );
        $group = MemberGroup::query()->updateOrCreate(
            ['member_group_name' => ucfirst($level)],
            [
                'member_group_description' => 'Test group',
                'member_group_is_active' => 1,
            ],
        );
        $member = Member::query()->create([
            'member_code' => 'MEMBER-'.$levelCode.'-'.fake()->unique()->numerify('###'),
            'member_member_level_id' => $memberLevel->getKey(),
            'member_name' => 'Test '.ucfirst($level),
            'member_email' => fake()->unique()->safeEmail(),
            'member_mobilephone' => fake()->unique()->numerify('0812#######'),
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(),
            'member_account_member_group_id' => $group->getKey(),
            'member_account_username' => fake()->unique()->userName(),
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
    }

    private function createProduct(string $code): Product
    {
        $category = ProductCategory::query()->firstOrCreate(
            ['product_category_name' => 'Skincare'],
            [
                'product_category_description' => 'Produk skincare',
                'product_category_is_active' => 1,
            ],
        );

        return Product::query()->create([
            'product_product_category_id' => $category->getKey(),
            'product_code' => $code,
            'product_name' => 'Produk Uji '.$code,
            'product_description' => 'Produk untuk pengujian penyesuaian.',
            'product_customer_price' => 150000,
            'product_weight' => 100,
            'product_unit' => 'pcs',
            'product_is_publish' => 1,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
            'product_input_datetime' => now(),
        ]);
    }
}
