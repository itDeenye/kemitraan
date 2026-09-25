<?php

namespace App\Services\Product;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminProductService
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function categories(array $params): array
    {
        return DataTable::select([
            'product_category.product_category_id as id',
            'product_category.product_category_name as name',
            'product_category.product_category_description as description',
            'product_category.product_category_is_active as is_active',
        ])
            ->selectRaw('(SELECT COUNT(*) FROM product WHERE product.product_product_category_id = product_category.product_category_id AND product.product_is_deleted = 0) as product_count')
            ->from('product_category')
            ->search(['name', 'description'])
            ->defaultSort('name')
            ->get($params);
    }

    public function category(ProductCategory $category): ProductCategory
    {
        return $category->loadCount([
            'products as products_count' => fn ($query) => $query->where('product_is_deleted', 0),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function createCategory(array $data): ProductCategory
    {
        $category = ProductCategory::query()->create($this->categoryAttributes($data) + [
            'product_category_is_active' => 1,
        ]);

        return $this->category($category);
    }

    /** @param array<string, mixed> $data */
    public function updateCategory(ProductCategory $category, array $data): ProductCategory
    {
        $category->update($this->categoryAttributes($data));

        return $this->category($category->refresh());
    }

    public function deleteCategory(ProductCategory $category): void
    {
        if ($category->products()->where('product_is_deleted', 0)->exists()) {
            throw new ProcessException('Kategori masih digunakan oleh produk aktif dan tidak dapat dihapus.');
        }

        $category->delete();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function products(array $params): array
    {
        $result = DataTable::select([
            'product.product_id as id',
            'product.product_code as code',
            'product.product_name as name',
            'product.product_bpom_number as bpom_number',
            'product.product_description as description',
            'product.product_image as image',
            'product.product_image_filename as image_filename',
            'product.product_customer_price as customer_price',
            'product.product_weight as weight',
            'product.product_length as length',
            'product.product_width as width',
            'product.product_height as height',
            'product.product_unit as unit',
            'product.product_is_package as is_package',
            'product.product_is_publish as is_publish',
            'product.product_is_active as is_active',
            'product_category.product_category_id as category_id',
            'product_category.product_category_name as category_name',
        ])
            ->from('product')
            ->join(
                'left',
                'product_category',
                'product_category.product_category_id = product.product_product_category_id'
            )
            ->where('product.product_is_deleted', 0)
            ->search(['code', 'name', 'bpom_number', 'description'])
            ->defaultSort('-id')
            ->get($params);

        $this->attachMemberPrices($result['results']);

        return $result;
    }

    public function product(Product $product): Product
    {
        return Product::query()
            ->with(['category', 'levelPrices.level'])
            ->where('product_is_deleted', 0)
            ->findOrFail($product->getKey());
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function prices(array $params): array
    {
        $result = DataTable::select([
            'product.product_id as id',
            'product.product_code as code',
            'product.product_name as name',
            'product.product_customer_price as customer_price',
            'product.product_is_active as is_active',
            'product_category.product_category_id as category_id',
            'product_category.product_category_name as category_name',
        ])
            ->from('product')
            ->leftJoin('product_category', 'product_category.product_category_id = product.product_product_category_id')
            ->where('product.product_is_deleted', 0)
            ->search(['code', 'name', 'category_name'])
            ->defaultSort('-id')
            ->get($params);

        $this->attachMemberPrices($result['results']);

        return $result;
    }

    public function price(Product $product): Product
    {
        return $this->product($product);
    }

    /** @param array<string, mixed> $data */
    public function updatePrices(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $this->product($product);
            $product->update(['product_customer_price' => $data['customer_price']]);
            $this->saveMemberPrices($product, $data);

            return $this->price($product->refresh());
        });
    }

    /**
     * @param  list<array{
     *     product_id: int,
     *     customer_price: int,
     *     member_prices: list<array{member_level_id: int, price: int}>
     * }>  $productsData
     */
    public function bulkUpdatePrices(array $productsData): int
    {
        return DB::transaction(function () use ($productsData): int {
            $productIds = collect($productsData)
                ->pluck('product_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
            $products = Product::query()
                ->whereIn('product_id', $productIds)
                ->where('product_is_deleted', 0)
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            foreach ($productsData as $productData) {
                /** @var Product $product */
                $product = $products->get((int) $productData['product_id']);
                $product->update([
                    'product_customer_price' => $productData['customer_price'],
                ]);
                $this->saveMemberPrices($product, $productData);
            }

            return count($productsData);
        });
    }

    /** @param array<string, mixed> $data */
    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $product = Product::query()->create($this->productAttributes($data) + [
                'product_image' => '',
                'product_image_filename' => '',
                'product_is_deleted' => 0,
                'product_input_datetime' => now(),
            ]);

            $this->saveMemberPrices($product, $data);

            return $this->product($product);
        });
    }

    /** @param array<string, mixed> $data */
    public function updateProduct(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $this->product($product);
            $product->update($this->productAttributes($data));
            $this->saveMemberPrices($product, $data);

            return $this->product($product->refresh());
        });
    }

    public function deleteProduct(Product $product): void
    {
        $this->product($product);
        $product->update([
            'product_is_deleted' => 1,
            'product_is_active' => 0,
            'product_is_publish' => 0,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function categoryAttributes(array $data): array
    {
        $attributes = [
            'product_category_name' => $data['name'],
            'product_category_description' => $data['description'] ?? '',
        ];

        if (array_key_exists('is_active', $data)) {
            $attributes['product_category_is_active'] = $data['is_active'];
        }

        return $attributes;
    }

    /** @param array<string, mixed> $data */
    private function productAttributes(array $data): array
    {
        $attributes = [
            'product_product_category_id' => $data['category_id'],
            'product_code' => $data['code'],
            'product_name' => $data['name'],
            'product_description' => $data['description'] ?? null,
            'product_customer_price' => $data['customer_price'],
        ];

        if (array_key_exists('bpom_number', $data)) {
            $attributes['product_bpom_number'] = filled($data['bpom_number'])
                ? strtoupper(trim((string) $data['bpom_number']))
                : null;
        }

        $optionalFields = [
            'weight' => 'product_weight',
            'length' => 'product_length',
            'width' => 'product_width',
            'height' => 'product_height',
            'unit' => 'product_unit',
            'is_package' => 'product_is_package',
            'is_publish' => 'product_is_publish',
            'is_active' => 'product_is_active',
        ];

        foreach ($optionalFields as $input => $column) {
            if (array_key_exists($input, $data)) {
                $attributes[$column] = $data[$input];
            }
        }

        if (array_key_exists('image_url', $data)) {
            $attributes += $this->productImageAttributes($data['image_url']);
        }

        return $attributes;
    }

    /** @param array<string, mixed> $data */
    private function saveMemberPrices(Product $product, array $data): void
    {
        $levelIds = [];

        foreach ($data['member_prices'] as $memberPrice) {
            $levelId = (int) $memberPrice['member_level_id'];
            $levelIds[] = $levelId;
            ProductPrice::query()->updateOrCreate(
                [
                    'product_price_product_id' => $product->product_id,
                    'product_price_member_level_id' => $levelId,
                ],
                ['product_price_value' => $memberPrice['price']],
            );
        }

        $product->levelPrices()
            ->whereNotIn('product_price_member_level_id', $levelIds)
            ->delete();
    }

    /** @param Collection<int, object> $products */
    private function attachMemberPrices(Collection $products): void
    {
        $prices = ProductPrice::query()
            ->with('level')
            ->whereIn('product_price_product_id', $products->pluck('id')->map(fn (mixed $id): int => (int) $id))
            ->orderBy('product_price_member_level_id')
            ->get()
            ->groupBy('product_price_product_id');

        foreach ($products as $product) {
            $product->member_prices = $prices->get((int) $product->id, collect());
        }
    }

    /** @return array{product_image: string, product_image_filename: string} */
    private function productImageAttributes(?string $imageUrl): array
    {
        if ($imageUrl === null || $imageUrl === '') {
            return [
                'product_image' => '',
                'product_image_filename' => '',
            ];
        }

        $pathWithoutQuery = Str::before($imageUrl, '?');
        $filename = rawurldecode(Str::afterLast($pathWithoutQuery, '/'));

        return [
            'product_image' => $imageUrl,
            'product_image_filename' => Str::limit($filename, 255, ''),
        ];
    }
}
