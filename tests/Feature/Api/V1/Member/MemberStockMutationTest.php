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

class MemberStockMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_stock_mutation_requires_an_authenticated_member(): void
    {
        $this->getJson('/api/v1/member/inventory/mutations')->assertUnauthorized();
    }

    public function test_member_stock_mutation_returns_only_authenticated_member_mutations(): void
    {
        $account = $this->createMemberAccount('agent');
        $this->actingAs($account, 'member_api');
        $memberId = $account->member_account_member_id;

        $category = $this->createCategory('Skincare');
        $product1 = $this->createProduct($category, ['product_code' => 'SERUM-01', 'product_name' => 'Brightening Serum']);
        $product2 = $this->createProduct($category, ['product_code' => 'TONER-01', 'product_name' => 'Hydrating Toner']);

        // Mutation for authenticated member
        DB::table('member_stock_log')->insert([
            'member_stock_log_member_id' => $memberId,
            'member_stock_log_product_id' => $product1->product_id,
            'member_stock_log_type' => 'in',
            'member_stock_log_quantity' => 100,
            'member_stock_log_unit_price' => 150000,
            'member_stock_log_balance' => 100,
            'member_stock_log_note' => 'Pembelian dari Upline',
            'member_stock_log_datetime' => now()->subDay(),
        ]);

        // Mutation for another member
        DB::table('member_stock_log')->insert([
            'member_stock_log_member_id' => 999, // Another member
            'member_stock_log_product_id' => $product2->product_id,
            'member_stock_log_type' => 'in',
            'member_stock_log_quantity' => 50,
            'member_stock_log_unit_price' => 150000,
            'member_stock_log_balance' => 50,
            'member_stock_log_note' => 'Stok awal upline lain',
            'member_stock_log_datetime' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/member/inventory/mutations')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.product.id', $product1->product_id)
            ->assertJsonPath('data.results.0.type', 'in')
            ->assertJsonPath('data.results.0.quantity', 100)
            ->assertJsonPath('data.results.0.balance', 100)
            ->assertJsonPath('data.results.0.note', 'Pembelian dari Upline');
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
