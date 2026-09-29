<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\MemberStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\WarehouseStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_requires_an_authenticated_member(): void
    {
        $this->getJson('/api/v1/member/purchases/catalog/products')->assertUnauthorized();
        $this->getJson('/api/v1/member/purchases/catalog/categories')->assertUnauthorized();
    }

    public function test_catalog_only_returns_available_products_with_the_members_price(): void
    {
        $account = $this->createMemberAccount('agent');
        $this->actingAs($account, 'member_api');

        $category = $this->createCategory('Skincare');
        $available = $this->createProduct($category, [
            'product_code' => 'SERUM-01',
            'product_name' => 'Brightening Serum',
            'product_bpom_number' => 'NA18260100001',
            'product_customer_price' => 200000,
            'member_prices' => [
                ['member_level_id' => 1, 'price' => 130000],
                ['member_level_id' => 2, 'price' => 150000],
                ['member_level_id' => 3, 'price' => 170000],
            ],
        ]);
        $this->createProduct($category, [
            'product_code' => 'HIDDEN-01',
            'product_name' => 'Hidden Product',
            'product_is_publish' => 0,
        ]);
        $this->createProduct($category, [
            'product_code' => 'DELETED-01',
            'product_name' => 'Deleted Product',
            'product_is_deleted' => 1,
        ]);
        $archivedCategory = $this->createCategory('Archived', false);
        $this->createProduct($archivedCategory, [
            'product_code' => 'ARCHIVED-01',
            'product_name' => 'Archived Category Product',
        ]);

        $this->getJson('/api/v1/member/purchases/catalog/products')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $available->product_id)
            ->assertJsonPath('data.results.0.bpom_number', 'NA18260100001')
            ->assertJsonPath('data.results.0.price', 150000)
            ->assertJsonPath('data.results.0.customer_price', 200000)
            ->assertJsonMissingPath('data.results.0.pv')
            ->assertJsonPath('data.results.0.category.name', 'Skincare')
            ->assertJsonPath('data.pagination.total_data', 1)
            ->assertJsonMissingPath('data.results.0.product_agent_price');

        $this->getJson('/api/v1/member/purchases/catalog/products?pagination=false')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonMissingPath('data.pagination');
    }

    public function test_catalog_can_be_filtered_and_validates_query_parameters(): void
    {
        $this->actingAs($this->createMemberAccount('reseller'), 'member_api');

        $skincare = $this->createCategory('Skincare');
        $makeup = $this->createCategory('Makeup');
        $this->createProduct($skincare, [
            'product_code' => 'SERUM-01',
            'product_name' => 'Brightening Serum',
        ]);
        $this->createProduct($makeup, [
            'product_code' => 'LIP-01',
            'product_name' => 'Matte Lip Cream',
        ]);

        $this->getJson("/api/v1/member/purchases/catalog/products?filter[category_id]={$skincare->product_category_id}&search=serum&field_search=name&limit=10&sort=name")
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.code', 'SERUM-01')
            ->assertJsonPath('data.pagination.current', 1);

        $this->getJson('/api/v1/member/purchases/catalog/products?name[eq]=Brightening%20Serum')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.code', 'SERUM-01');

        $this->getJson('/api/v1/member/purchases/catalog/products?product_name[eq]=Matte%20Lip%20Cream')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.code', 'LIP-01');

        $this->getJson('/api/v1/member/purchases/catalog/products?search=lip&field_search=product_name')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.code', 'LIP-01');

        $this->getJson('/api/v1/member/purchases/catalog/products?search=SERUM-01')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.code', 'SERUM-01');

        $this->getJson('/api/v1/member/purchases/catalog/products?search=LIP-01&field_search=code')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.code', 'LIP-01');

        $this->getJson('/api/v1/member/purchases/catalog/products?sort=-customer_price,name')
            ->assertOk()
            ->assertJsonPath('data.results.0.code', 'SERUM-01')
            ->assertJsonPath('data.results.1.code', 'LIP-01');

        $this->getJson('/api/v1/member/purchases/catalog/products?sort=-id')
            ->assertOk()
            ->assertJsonPath('data.results.0.code', 'LIP-01')
            ->assertJsonPath('data.results.1.code', 'SERUM-01');

        $this->getJson('/api/v1/member/purchases/catalog/products?sort=-unknown')
            ->assertOk()
            ->assertJsonPath('data.results.0.code', 'LIP-01');

        $this->getJson('/api/v1/member/purchases/catalog/products?limit=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');

        $this->getJson('/api/v1/member/purchases/catalog/products?filter[category_id]=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.category_id');
    }

    public function test_catalog_exposes_stock_value_and_business_status(): void
    {
        $this->actingAs($this->createMemberAccount('distributor'), 'member_api');
        $category = $this->createCategory('Status Stok');
        $available = $this->createProduct($category, [
            'product_code' => 'STOCK-AVAILABLE',
            'product_name' => 'Produk Tersedia',
        ]);
        $lowStock = $this->createProduct($category, [
            'product_code' => 'STOCK-LOW',
            'product_name' => 'Produk Sisa Sedikit',
        ]);
        $preorder = $this->createProduct($category, [
            'product_code' => 'STOCK-PO',
            'product_name' => 'Produk PO',
        ]);
        DB::table('warehouse_stock')->insert([
            [
                'warehouse_stock_warehouse_id' => 1,
                'warehouse_stock_product_id' => $available->getKey(),
                'warehouse_stock_balance' => 6,
            ],
            [
                'warehouse_stock_warehouse_id' => 1,
                'warehouse_stock_product_id' => $lowStock->getKey(),
                'warehouse_stock_balance' => 5,
            ],
            [
                'warehouse_stock_warehouse_id' => 1,
                'warehouse_stock_product_id' => $preorder->getKey(),
                'warehouse_stock_balance' => 0,
            ],
        ]);

        $this->getJson('/api/v1/member/purchases/catalog/products?search=STOCK-AVAILABLE')
            ->assertOk()
            ->assertJsonPath('data.results.0.stock.available', 6)
            ->assertJsonPath('data.results.0.stock.status.code', 'available')
            ->assertJsonPath('data.results.0.stock.status.label', 'Tersedia')
            ->assertJsonPath('data.results.0.stock.requires_preorder', false);

        $this->getJson('/api/v1/member/purchases/catalog/products?search=STOCK-LOW')
            ->assertOk()
            ->assertJsonPath('data.results.0.stock.available', 5)
            ->assertJsonPath('data.results.0.stock.status.code', 'low_stock')
            ->assertJsonPath('data.results.0.stock.status.label', 'Sisa Sedikit')
            ->assertJsonPath('data.results.0.stock.requires_preorder', false);

        $this->getJson('/api/v1/member/purchases/catalog/products?search=STOCK-PO')
            ->assertOk()
            ->assertJsonPath('data.results.0.stock.available', 0)
            ->assertJsonPath('data.results.0.stock.status.code', 'out_of_stock')
            ->assertJsonPath('data.results.0.stock.status.label', 'Habis')
            ->assertJsonPath('data.results.0.stock.requires_preorder', false);

        $this->getJson('/api/v1/member/purchases/catalog/products?sort=-id')
            ->assertOk()
            ->assertJsonPath('data.results.0.code', 'STOCK-AVAILABLE')
            ->assertJsonPath('data.results.1.code', 'STOCK-LOW')
            ->assertJsonPath('data.results.2.code', 'STOCK-PO');
    }

    public function test_catalog_uses_the_nearest_available_stock_in_the_seller_chain(): void
    {
        $account = $this->createMemberAccount('reseller');
        $distributor = Member::query()->create([
            'member_code' => 'CATALOG-DISTRIBUTOR',
            'member_member_level_id' => 1,
            'member_parent_member_id' => 0,
            'member_name' => 'Catalog Distributor',
            'member_email' => 'catalog.distributor@example.test',
            'member_mobilephone' => '08123456781',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
        $agent = Member::query()->create([
            'member_code' => 'CATALOG-AGENT',
            'member_member_level_id' => 2,
            'member_parent_member_id' => $distributor->getKey(),
            'member_name' => 'Catalog Agent',
            'member_email' => 'catalog.agent@example.test',
            'member_mobilephone' => '08123456782',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
        $account->member()->update(['member_parent_member_id' => $agent->getKey()]);
        $this->actingAs($account, 'member_api');

        $category = $this->createCategory('Stok Chain');
        $agentProduct = $this->createProduct($category, [
            'product_code' => 'CHAIN-AGENT',
            'product_name' => 'Stok Agent',
        ]);
        $distributorProduct = $this->createProduct($category, [
            'product_code' => 'CHAIN-DISTRIBUTOR',
            'product_name' => 'Stok Distributor',
        ]);
        $warehouseProduct = $this->createProduct($category, [
            'product_code' => 'CHAIN-WAREHOUSE',
            'product_name' => 'Stok Warehouse',
        ]);
        $emptyProduct = $this->createProduct($category, [
            'product_code' => 'CHAIN-EMPTY',
            'product_name' => 'Stok Kosong',
        ]);

        DB::table((new MemberStock)->getTable())->insert([
            [
                'member_stock_member_id' => $agent->getKey(),
                'member_stock_product_id' => $agentProduct->getKey(),
                'member_stock_balance' => 2,
            ],
            [
                'member_stock_member_id' => $distributor->getKey(),
                'member_stock_product_id' => $agentProduct->getKey(),
                'member_stock_balance' => 8,
            ],
            [
                'member_stock_member_id' => $distributor->getKey(),
                'member_stock_product_id' => $distributorProduct->getKey(),
                'member_stock_balance' => 7,
            ],
        ]);
        DB::table((new WarehouseStock)->getTable())->insert([
            [
                'warehouse_stock_warehouse_id' => 1,
                'warehouse_stock_product_id' => $agentProduct->getKey(),
                'warehouse_stock_balance' => 12,
            ],
            [
                'warehouse_stock_warehouse_id' => 1,
                'warehouse_stock_product_id' => $distributorProduct->getKey(),
                'warehouse_stock_balance' => 11,
            ],
            [
                'warehouse_stock_warehouse_id' => 1,
                'warehouse_stock_product_id' => $warehouseProduct->getKey(),
                'warehouse_stock_balance' => 6,
            ],
            [
                'warehouse_stock_warehouse_id' => 1,
                'warehouse_stock_product_id' => $emptyProduct->getKey(),
                'warehouse_stock_balance' => 0,
            ],
        ]);

        $this->getJson('/api/v1/member/purchases/catalog/products?search=CHAIN-AGENT')
            ->assertOk()
            ->assertJsonPath('data.results.0.stock.available', 2)
            ->assertJsonPath('data.results.0.stock.status.code', 'low_stock')
            ->assertJsonPath('data.results.0.stock.requires_preorder', false);
        $this->getJson('/api/v1/member/purchases/catalog/products?search=CHAIN-DISTRIBUTOR')
            ->assertOk()
            ->assertJsonPath('data.results.0.stock.available', 7)
            ->assertJsonPath('data.results.0.stock.status.code', 'preorder')
            ->assertJsonPath('data.results.0.stock.requires_preorder', true);
        $this->getJson('/api/v1/member/purchases/catalog/products?search=CHAIN-WAREHOUSE')
            ->assertOk()
            ->assertJsonPath('data.results.0.stock.available', 6)
            ->assertJsonPath('data.results.0.stock.status.code', 'preorder')
            ->assertJsonPath('data.results.0.stock.requires_preorder', true);
        $this->getJson('/api/v1/member/purchases/catalog/products?search=CHAIN-EMPTY')
            ->assertOk()
            ->assertJsonPath('data.results.0.stock.available', 0)
            ->assertJsonPath('data.results.0.stock.status.code', 'out_of_stock')
            ->assertJsonPath('data.results.0.stock.requires_preorder', false);
        $this->getJson('/api/v1/member/purchases/catalog/products?sort=-id')
            ->assertOk()
            ->assertJsonPath('data.results.0.code', 'CHAIN-AGENT')
            ->assertJsonPath('data.results.0.stock.requires_preorder', false)
            ->assertJsonPath('data.results.1.code', 'CHAIN-DISTRIBUTOR')
            ->assertJsonPath('data.results.1.stock.requires_preorder', true)
            ->assertJsonPath('data.results.2.code', 'CHAIN-WAREHOUSE')
            ->assertJsonPath('data.results.2.stock.requires_preorder', true)
            ->assertJsonPath('data.results.3.code', 'CHAIN-EMPTY');
        $this->getJson("/api/v1/member/purchases/catalog/products/{$distributorProduct->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.stock.available', 7);
    }

    public function test_categories_and_product_detail_only_expose_active_catalog_data(): void
    {
        $this->actingAs($this->createMemberAccount('distributor'), 'member_api');

        $activeCategory = $this->createCategory('Sun Care');
        $this->createCategory('Archived', false);
        $available = $this->createProduct($activeCategory, [
            'product_code' => 'SUN-01',
            'product_name' => 'Daily Sunscreen',
            'product_bpom_number' => 'NA18260100002',
            'member_prices' => [
                ['member_level_id' => 1, 'price' => 90000],
            ],
        ]);
        $inactive = $this->createProduct($activeCategory, [
            'product_code' => 'OLD-01',
            'product_name' => 'Old Sunscreen',
            'product_is_active' => 0,
        ]);

        $categoryResponse = $this->getJson('/api/v1/member/purchases/catalog/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sun Care');
        $this->assertStringStartsWith(
            '{"success":true,"message":"Kategori produk berhasil dimuat.","data":',
            (string) $categoryResponse->getContent()
        );

        $this->getJson("/api/v1/member/purchases/catalog/products/{$available->product_id}")
            ->assertOk()
            ->assertJsonPath('data.price', 90000)
            ->assertJsonPath('data.bpom_number', 'NA18260100002')
            ->assertJsonMissingPath('data.pv');

        $this->getJson("/api/v1/member/purchases/catalog/products/{$inactive->product_id}")
            ->assertNotFound();
    }

    private function createMemberAccount(string $level): MemberAccount
    {
        $group = MemberGroup::query()->create([
            'member_group_name' => ucfirst($level),
            'member_group_description' => 'Catalog test group',
            'member_group_is_active' => 1,
        ]);
        $member = Member::query()->create([
            'member_code' => 'MEMBER-'.strtoupper($level),
            'member_member_level_id' => $this->levelId($level),
            'member_name' => 'Catalog Test Member',
            'member_email' => "{$level}@example.test",
            'member_mobilephone' => '08123456789',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->member_id,
            'member_account_member_group_id' => $group->member_group_id,
            'member_account_username' => "catalog.{$level}",
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
        $productAttributes = array_merge([
            'product_product_category_id' => $category->product_category_id,
            'product_code' => 'PRODUCT-01',
            'product_name' => 'Catalog Product',
            'product_description' => 'Product description',
            'product_customer_price' => 200000,
            'product_weight' => 100,
            'product_unit' => 'pcs',
            'product_is_publish' => 1,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
            'product_input_datetime' => now(),
        ], $attributes);
        $prices = $productAttributes['member_prices'] ?? [
            ['member_level_id' => 1, 'price' => 130000],
            ['member_level_id' => 2, 'price' => 150000],
            ['member_level_id' => 3, 'price' => 170000],
        ];
        unset($productAttributes['member_prices']);

        $product = Product::query()->create($productAttributes);

        foreach ($prices as $price) {
            ProductPrice::query()->create([
                'product_price_product_id' => $product->product_id,
                'product_price_member_level_id' => $price['member_level_id'],
                'product_price_value' => $price['price'],
            ]);
        }

        return $product;
    }

    private function levelId(string $level): int
    {
        return match ($level) {
            'distributor' => 1,
            'agent' => 2,
            'reseller' => 3,
        };
    }
}
