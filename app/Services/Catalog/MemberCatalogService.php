<?php

namespace App\Services\Catalog;

use App\Libraries\DataTable;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\WarehouseStock;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MemberCatalogService
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, Product>, pagination: array<string, mixed>}
     */
    public function list(MemberAccount $account, array $params): array
    {
        $levelId = $this->memberLevelId($account);
        $sellerStock = $this->sellerStockQuery($account, $params);

        $catalog = DataTable::select($this->catalogColumns())
            ->selectRaw(
                'COALESCE((SELECT product_price_value FROM product_price WHERE product_price_product_id = product.product_id AND product_price_member_level_id = ? LIMIT 1), product.product_customer_price) as price',
                [$levelId],
            )
            ->selectRaw('COALESCE(seller_stock.available_stock, 0) as available_stock')
            ->selectRaw('COALESCE(seller_stock.direct_available_stock, 0) as direct_available_stock')
            ->from('product')
            ->join(
                'left',
                'product_category',
                'product_category.product_category_id = product.product_product_category_id'
            )
            ->leftJoinSub(
                $sellerStock,
                'seller_stock',
                'seller_stock.product_id = product.product_id'
            )
            ->where('product.product_is_publish', 1)
            ->where('product.product_is_active', 1)
            ->where('product.product_is_deleted', 0)
            ->where('product_category.product_category_is_active', 1)
            ->orderByRaw('CASE
                WHEN COALESCE(seller_stock.direct_available_stock, 0) > 0 THEN 0
                WHEN COALESCE(seller_stock.available_stock, 0) > 0 THEN 1
                ELSE 2
            END')
            ->orderByDesc('available_stock')
            ->search(['code', 'name'])
            ->defaultSort('-id')
            ->get($params);

        $this->attachVisibleLevelPrices(
            $catalog['results'],
            $this->memberLevelSortOrder($account),
        );

        return $catalog;
    }

    /** @return Collection<int, ProductCategory> */
    public function categories(): Collection
    {
        return ProductCategory::query()
            ->active()
            ->orderBy('product_category_name')
            ->get();
    }

    public function product(MemberAccount $account, Product $product, ?int $warehouseId = null): Product
    {
        $levelId = $this->memberLevelId($account);
        $catalogProduct = Product::query()
            ->with(['category', 'levelPrices.level'])
            ->availableInCatalog()
            ->findOrFail($product->getKey());

        $price = $catalogProduct->levelPrices
            ->firstWhere('product_price_member_level_id', $levelId)
            ?->product_price_value;
        $catalogProduct->setAttribute('catalog_price', $price ?? $catalogProduct->product_customer_price);
        $catalogProduct->setAttribute(
            'member_prices',
            $this->formatLevelPrices(
                $catalogProduct->levelPrices,
                $this->memberLevelSortOrder($account),
                (int) $catalogProduct->product_customer_price,
            ),
        );
        $stock = $this->sellerStockQuery($account, [
            'warehouse_id' => $warehouseId,
            'product_id' => $catalogProduct->getKey(),
        ])->first();
        $catalogProduct->setAttribute('available_stock', (int) ($stock?->available_stock ?? 0));
        $catalogProduct->setAttribute('direct_available_stock', (int) ($stock?->direct_available_stock ?? 0));

        return $catalogProduct;
    }

    private function memberLevelId(MemberAccount $account): int
    {
        $account->loadMissing('member.level');

        return (int) ($account->member?->member_member_level_id ?? 0);
    }

    private function memberLevelSortOrder(MemberAccount $account): int
    {
        $account->loadMissing('member.level');

        return (int) ($account->member?->level?->member_level_sort_order ?? 0);
    }

    /** @param Collection<int, object> $products */
    private function attachVisibleLevelPrices(Collection $products, int $currentSortOrder): void
    {
        $prices = ProductPrice::query()
            ->with('level')
            ->whereIn('product_price_product_id', $products->pluck('product_id')->all())
            ->get()
            ->groupBy('product_price_product_id');

        foreach ($products as $product) {
            $product->member_prices = $this->formatLevelPrices(
                $prices->get($product->product_id, collect()),
                $currentSortOrder,
                (int) $product->product_customer_price,
            );
        }
    }

    /**
     * @param  Collection<int, ProductPrice>  $prices
     * @return list<array{id: int, level: string, price: int}>
     */
    private function formatLevelPrices(
        Collection $prices,
        int $currentSortOrder,
        int $customerPrice,
    ): array {
        return $prices
            ->filter(fn (ProductPrice $price): bool => (bool) $price->level?->member_level_is_active
                && (int) $price->level?->member_level_sort_order >= $currentSortOrder)
            ->sortBy(fn (ProductPrice $price): int => (int) $price->level?->member_level_sort_order)
            ->map(fn (ProductPrice $price): array => [
                'id' => (int) $price->product_price_member_level_id,
                'level' => $price->level?->member_level_code === 'AGT'
                    ? 'Agen Utama'
                    : (string) $price->level?->member_level_name,
                'price' => (int) $price->product_price_value,
            ])
            ->values()
            ->push(['id' => 4, 'level' => 'Customer', 'price' => $customerPrice])
            ->all();
    }

    /** @param array<string, mixed> $params */
    private function sellerStockQuery(MemberAccount $account, array $params): Builder
    {
        $account->loadMissing('member.level');
        $member = $account->member;
        $productTable = (new Product)->getTable();
        $memberStockTable = (new MemberStock)->getTable();
        $warehouseStockTable = (new WarehouseStock)->getTable();
        $stockQuery = DB::table("{$productTable} as catalog_stock_product")
            ->select('catalog_stock_product.product_id as product_id');
        $availableStockCases = [];
        $directAvailableStock = null;

        foreach ($this->sellerMemberIds($member) as $index => $memberId) {
            $alias = "chain_member_stock_{$index}";
            $memberStock = DB::table($memberStockTable)
                ->select("{$memberStockTable}.member_stock_product_id as product_id")
                ->selectRaw("SUM({$memberStockTable}.member_stock_balance) as available_stock")
                ->where("{$memberStockTable}.member_stock_member_id", $memberId)
                ->groupBy("{$memberStockTable}.member_stock_product_id");

            $stockQuery->leftJoinSub(
                $memberStock,
                $alias,
                "{$alias}.product_id",
                '=',
                'catalog_stock_product.product_id',
            );
            $availableStockCases[] = "WHEN COALESCE({$alias}.available_stock, 0) > 0 THEN {$alias}.available_stock";
            $directAvailableStock ??= "{$alias}.available_stock";
        }

        $warehouseId = (int) ($params['warehouse_id'] ?? 1);
        $warehouseStock = DB::table($warehouseStockTable)
            ->select("{$warehouseStockTable}.warehouse_stock_product_id as product_id")
            ->selectRaw("SUM({$warehouseStockTable}.warehouse_stock_balance) as available_stock")
            ->where("{$warehouseStockTable}.warehouse_stock_warehouse_id", $warehouseId)
            ->groupBy("{$warehouseStockTable}.warehouse_stock_product_id");
        $stockQuery->leftJoinSub(
            $warehouseStock,
            'chain_warehouse_stock',
            'chain_warehouse_stock.product_id',
            '=',
            'catalog_stock_product.product_id',
        );
        $availableStockCases[] = 'WHEN COALESCE(chain_warehouse_stock.available_stock, 0) > 0 THEN chain_warehouse_stock.available_stock';
        $directAvailableStock ??= 'chain_warehouse_stock.available_stock';

        $stockQuery->selectRaw(
            'CASE '.implode(' ', $availableStockCases).' ELSE 0 END as available_stock',
        );
        $stockQuery->selectRaw(
            "COALESCE({$directAvailableStock}, 0) as direct_available_stock",
        );

        if (isset($params['product_id'])) {
            $stockQuery->where('catalog_stock_product.product_id', (int) $params['product_id']);
        }

        return $stockQuery;
    }

    /** @return list<int> */
    private function sellerMemberIds(?Member $member): array
    {
        if (! $member || $member->level?->member_level_code === 'DST') {
            return [];
        }

        $memberIds = [];
        $visitedIds = [];
        $parentId = (int) $member->member_parent_member_id;

        while ($parentId > 0 && count($memberIds) < 10) {
            if (isset($visitedIds[$parentId])) {
                break;
            }
            $visitedIds[$parentId] = true;

            $parent = Member::query()
                ->select(['member_id', 'member_parent_member_id'])
                ->whereKey($parentId)
                ->where('member_status', 1)
                ->first();
            if (! $parent) {
                break;
            }

            $memberIds[] = (int) $parent->getKey();
            $parentId = (int) $parent->member_parent_member_id;
        }

        return $memberIds;
    }

    /** @return list<string> */
    private function catalogColumns(): array
    {
        return [
            'product.product_id',
            'product.product_id as id',
            'product.product_product_category_id',
            'product.product_code as code',
            'product.product_name as name',
            'product.product_description',
            'product.product_bpom_number',
            'product.product_image',
            'product.product_image_filename',
            'product.product_customer_price',
            'product.product_weight',
            'product.product_length',
            'product.product_height',
            'product.product_width',
            'product.product_unit',
            'product.product_is_package',
            'product_category.product_category_id as category_id',
            'product_category.product_category_name as category_name',
            'product_category.product_category_description as category_description',
        ];
    }
}
