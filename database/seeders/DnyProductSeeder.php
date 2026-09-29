<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DnyProductSeeder extends Seeder
{
    private const IMAGE_BASE_URL = 'https://dnyskincare.com/assets/images/produk/';

    private const PRODUCT_BASE_URL = 'https://dnyskincare.com/commerce/produk/';

    /** Berat awal dalam gram sampai ukuran aktual produk dilengkapi melalui admin. */
    private const DEFAULT_WEIGHT = 100;

    /** Dimensi awal dalam sentimeter sampai ukuran aktual produk dilengkapi melalui admin. */
    private const DEFAULT_DIMENSION = 10;

    /**
     * Diskon harga mitra dari harga pelanggan.
     *
     * @var array<int, int>
     */
    private const MEMBER_LEVEL_DISCOUNTS = [
        1 => 30, // Distributor
        2 => 20, // Agent
        3 => 10, // Reseller
    ];

    public function run(): void
    {
        /** @var array<string, list<array{string, string, int, int|null, string}>> $catalog */
        $catalog = File::json(
            database_path('seeders/dny-products.json'),
            JSON_THROW_ON_ERROR,
        );

        DB::transaction(function () use ($catalog): void {
            foreach ($catalog as $categoryName => $products) {
                $category = ProductCategory::query()->updateOrCreate(
                    ['product_category_name' => $categoryName],
                    [
                        'product_category_description' => "Kategori {$categoryName} berdasarkan katalog DNY Skincare.",
                        'product_category_is_active' => 1,
                    ],
                );

                foreach ($products as [$uuid, $name, $price, $originalPrice, $imagePath]) {
                    $sourceUrl = self::PRODUCT_BASE_URL.$uuid;
                    $imageUrl = self::IMAGE_BASE_URL.$imagePath;
                    $product = Product::query()->firstOrNew([
                        'product_code' => $this->productCode($uuid),
                    ]);
                    $product->fill([
                        'product_product_category_id' => $category->getKey(),
                        'product_name' => $name,
                        'product_description' => $this->description(
                            $categoryName,
                            $price,
                            $originalPrice,
                            $sourceUrl,
                        ),
                        'product_image' => $imageUrl,
                        'product_image_filename' => Str::afterLast($imageUrl, '/'),
                        'product_customer_price' => $price,
                        'product_weight' => $this->shippingValue(
                            $product->product_weight,
                            self::DEFAULT_WEIGHT,
                        ),
                        'product_length' => $this->shippingValue(
                            $product->product_length,
                            self::DEFAULT_DIMENSION,
                        ),
                        'product_height' => $this->shippingValue(
                            $product->product_height,
                            self::DEFAULT_DIMENSION,
                        ),
                        'product_width' => $this->shippingValue(
                            $product->product_width,
                            self::DEFAULT_DIMENSION,
                        ),
                        'product_unit' => 'pcs',
                        'product_is_package' => 0,
                        'product_is_publish' => 1,
                        'product_is_active' => 1,
                        'product_is_deleted' => 0,
                        'product_input_datetime' => $product->product_input_datetime ?? now(),
                    ]);
                    $product->save();

                    foreach (self::MEMBER_LEVEL_DISCOUNTS as $memberLevelId => $discountPercentage) {
                        ProductPrice::query()->updateOrCreate(
                            [
                                'product_price_product_id' => $product->getKey(),
                                'product_price_member_level_id' => $memberLevelId,
                            ],
                            [
                                'product_price_value' => $this->memberPrice(
                                    $price,
                                    $discountPercentage,
                                ),
                            ],
                        );
                    }
                }
            }
        });
    }

    private function productCode(string $uuid): string
    {
        return 'DNY-'.Str::upper(Str::substr(Str::remove('-', $uuid), 0, 12));
    }

    private function shippingValue(mixed $currentValue, int $defaultValue): int
    {
        return (int) $currentValue > 0 ? (int) $currentValue : $defaultValue;
    }

    private function memberPrice(int $customerPrice, int $discountPercentage): int
    {
        return (int) round($customerPrice * (100 - $discountPercentage) / 100);
    }

    private function description(
        string $categoryName,
        int $price,
        ?int $originalPrice,
        string $sourceUrl,
    ): string {
        $priceNote = $originalPrice === null || $originalPrice === $price
            ? ''
            : " Harga katalog sebelum diskon: Rp{$originalPrice}.";

        return "Produk kategori {$categoryName} DNY Skincare.{$priceNote} Sumber katalog: {$sourceUrl}";
    }
}
