<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DnyProductSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DnyProductSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_seeds_the_complete_catalog_and_member_prices_idempotently(): void
    {
        $this->seed(AccessControlSeeder::class);
        $this->seed(DnyProductSeeder::class);

        Product::query()
            ->where('product_code', 'DNY-A001B3A2EEA7')
            ->update([
                'product_weight' => 250,
                'product_length' => 15,
                'product_width' => 12,
                'product_height' => 8,
            ]);

        $this->seed(DnyProductSeeder::class);

        $this->assertDatabaseCount('product_category', 18);
        $this->assertDatabaseCount('product', 184);
        $this->assertDatabaseCount('product_price', 552);
        $this->assertDatabaseHas('product_category', [
            'product_category_name' => 'Cream Malam',
            'product_category_is_active' => 1,
        ]);
        $this->assertDatabaseHas('product_category', [
            'product_category_name' => 'Bundling',
            'product_category_is_active' => 1,
        ]);
        $this->assertDatabaseHas('product', [
            'product_code' => 'DNY-A1DEE0F49D7C',
            'product_name' => 'DNY Skincare Night Cream 3',
            'product_customer_price' => 135000,
            'product_weight' => 100,
            'product_length' => 10,
            'product_width' => 10,
            'product_height' => 10,
        ]);
        $this->assertDatabaseHas('product', [
            'product_code' => 'DNY-A001B3A2EEA7',
            'product_name' => 'DNY Skincare Cysteamine Dark Spot Night Cream - untuk Membantu Mencerahkan dan Membantu Menyamarkan Noda Hitam Pada Wajah',
            'product_customer_price' => 364000,
            'product_weight' => 250,
            'product_length' => 15,
            'product_width' => 12,
            'product_height' => 8,
        ]);

        $bundlingCategory = ProductCategory::query()
            ->where('product_category_name', 'Bundling')
            ->firstOrFail();
        $bundling = Product::query()
            ->where('product_code', 'DNY-A24356D81843')
            ->firstOrFail();

        $this->assertTrue($bundling->category->is($bundlingCategory));
        $this->assertSame(3, $bundling->levelPrices()->count());
        $this->assertSame(
            [
                1 => 223300,
                2 => 255200,
                3 => 287100,
            ],
            $bundling->levelPrices()
                ->orderBy('product_price_member_level_id')
                ->pluck('product_price_value', 'product_price_member_level_id')
                ->mapWithKeys(fn (mixed $value, mixed $key): array => [(int) $key => (int) $value])
                ->all(),
        );
    }
}
