<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Media;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_master_data_requires_an_administrator(): void
    {
        $this->getJson('/api/v1/admin/products')->assertUnauthorized();
        $this->getJson('/api/v1/admin/product/categories')->assertUnauthorized();
    }

    public function test_administrator_can_manage_categories_and_products(): void
    {
        $administrator = $this->createAdministrator();
        $this->actingAs($administrator, 'admin_api');

        $mediaPath = 'media/product/2026/07/product-image.webp';
        $media = Media::query()->create([
            'media_uuid' => (string) Str::uuid(),
            'media_uploader_type' => $administrator->getMorphClass(),
            'media_uploader_id' => $administrator->getKey(),
            'media_collection' => 'product',
            'media_original_name' => 'serum.jpg',
            'media_original_mime_type' => 'image/jpeg',
            'media_original_size' => 1024,
            'media_mime_type' => 'image/webp',
            'media_size' => 800,
            'media_chunk_size' => 1024,
            'media_total_chunks' => 1,
            'media_uploaded_chunks' => 1,
            'media_status' => Media::STATUS_READY,
            'media_disk' => 'public',
            'media_path' => $mediaPath,
            'media_expires_at' => now()->addDay(),
        ]);
        $imageUrl = Storage::disk('public')->url($media->media_path);

        $categoryResponse = $this->postJson('/api/v1/admin/product/categories', [
            'name' => 'Skincare',
            'description' => 'Perawatan kulit',
            'is_active' => true,
        ])->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Skincare');
        $categoryId = $categoryResponse->json('data.id');

        $productResponse = $this->postJson('/api/v1/admin/products', [
            'category_id' => $categoryId,
            'code' => 'SERUM-01',
            'name' => 'Brightening Serum',
            'bpom_number' => 'NA18250100001',
            'description' => 'Serum pencerah',
            'image_url' => $imageUrl,
            'customer_price' => 200000,
            'member_prices' => [
                ['member_level_id' => 1, 'price' => 130000],
                ['member_level_id' => 2, 'price' => 150000],
                ['member_level_id' => 3, 'price' => 170000],
            ],
            'weight' => 100,
            'length' => 10,
            'width' => 8,
            'height' => 5,
            'unit' => 'pcs',
            'is_package' => true,
            'is_publish' => true,
            'is_active' => true,
        ])->assertSuccessful()
            ->assertJsonPath('data.code', 'SERUM-01')
            ->assertJsonPath('data.bpom_number', 'NA18250100001')
            ->assertJsonPath('data.image', $imageUrl)
            ->assertJsonMissingPath('data.pv')
            ->assertJsonPath('data.is_package', true)
            ->assertJsonPath('data.prices.members.1.code', 'AGT')
            ->assertJsonPath('data.prices.members.1.price', 150000);
        $productId = $productResponse->json('data.id');

        $categoryList = $this->getJson('/api/v1/admin/product/categories')->assertOk();
        $this->assertStringStartsWith(
            '{"success":true,"message":"Daftar kategori produk berhasil dimuat.","data":',
            (string) $categoryList->getContent()
        );

        $this->getJson('/api/v1/admin/products?search=serum&field_search=name&sort=-customer_price,name')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonMissingPath('data.results.0.pv')
            ->assertJsonPath('data.results.0.bpom_number', 'NA18250100001')
            ->assertJsonPath('data.results.0.category.name', 'Skincare')
            ->assertJsonPath('data.pagination.total_data', 1);

        $this->getJson('/api/v1/admin/products?filter[code][like]=%25TIDAK-ADA%25')
            ->assertOk()
            ->assertJsonCount(0, 'data.results');

        $emptyCategory = ProductCategory::query()->create([
            'product_category_name' => 'Kategori Tanpa Produk',
            'product_category_description' => '',
            'product_category_is_active' => 1,
        ]);
        $this->getJson("/api/v1/admin/products?filter[category_id]={$emptyCategory->getKey()}")
            ->assertOk()
            ->assertJsonCount(0, 'data.results');

        $this->getJson("/api/v1/admin/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Brightening Serum')
            ->assertJsonPath('data.bpom_number', 'NA18250100001')
            ->assertJsonMissingPath('data.pv')
            ->assertJsonPath('data.image', $imageUrl);

        $this->assertDatabaseHas('product', [
            'product_id' => $productId,
            'product_bpom_number' => 'NA18250100001',
            'product_image' => $imageUrl,
            'product_image_filename' => 'product-image.webp',
            'product_is_package' => 1,
        ]);
        $this->assertDatabaseHas('product_price', [
            'product_price_product_id' => $productId,
            'product_price_member_level_id' => 2,
            'product_price_value' => 150000,
        ]);

        $this->putJson("/api/v1/admin/products/{$productId}", [
            'category_id' => $categoryId,
            'code' => 'SERUM-01',
            'name' => 'Advanced Brightening Serum',
            'bpom_number' => 'na18250100002',
            'description' => 'Formula baru',
            'image_url' => 'https://cdn.example.test/products/advanced-serum.webp',
            'customer_price' => 210000,
            'member_prices' => [
                ['member_level_id' => 1, 'price' => 140000],
                ['member_level_id' => 2, 'price' => 160000],
                ['member_level_id' => 3, 'price' => 180000],
            ],
            'weight' => 100,
            'length' => 10,
            'width' => 8,
            'height' => 5,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Advanced Brightening Serum')
            ->assertJsonPath('data.bpom_number', 'NA18250100002')
            ->assertJsonPath('data.image', 'https://cdn.example.test/products/advanced-serum.webp')
            ->assertJsonMissingPath('data.pv')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('product', [
            'product_id' => $productId,
            'product_bpom_number' => 'NA18250100002',
            'product_image' => 'https://cdn.example.test/products/advanced-serum.webp',
            'product_image_filename' => 'advanced-serum.webp',
        ]);
        $this->assertDatabaseHas('product_price', [
            'product_price_product_id' => $productId,
            'product_price_member_level_id' => 2,
            'product_price_value' => 160000,
        ]);

        $this->postJson('/api/v1/admin/product/prices/bulk', [[
            'product_id' => $productId,
            'customer_price' => 231000,
            'member_prices' => [
                ['member_level_id' => 1, 'price' => 154000],
                ['member_level_id' => 2, 'price' => 176000],
                ['member_level_id' => 3, 'price' => 198000],
            ],
        ]])->assertOk()
            ->assertJsonPath('data.updated_count', 1);
        $this->assertDatabaseHas('product', [
            'product_id' => $productId,
            'product_customer_price' => 231000,
        ]);
        $this->assertDatabaseHas('product_price', [
            'product_price_product_id' => $productId,
            'product_price_member_level_id' => 2,
            'product_price_value' => 176000,
        ]);

        $this->deleteJson("/api/v1/admin/product/categories/{$categoryId}")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath(
                'message',
                'Kategori masih digunakan oleh produk aktif dan tidak dapat dihapus.'
            )
            ->assertJsonMissingPath('errors');

        $this->deleteJson("/api/v1/admin/products/{$productId}")->assertOk();
        $this->getJson("/api/v1/admin/products/{$productId}")->assertNotFound();
        $this->assertDatabaseHas('product', [
            'product_id' => $productId,
            'product_is_deleted' => 1,
        ]);

        $this->deleteJson("/api/v1/admin/product/categories/{$categoryId}")->assertOk();
        $this->assertDatabaseMissing('product_category', ['product_category_id' => $categoryId]);
    }

    public function test_administrator_can_update_multiple_product_prices_from_a_root_array(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $category = ProductCategory::query()->create([
            'product_category_name' => 'Perawatan Wajah',
            'product_category_description' => 'Produk perawatan wajah',
            'product_category_is_active' => 1,
        ]);
        $products = collect([1, 2])->map(fn (int $number): Product => Product::query()->create([
            'product_product_category_id' => $category->getKey(),
            'product_code' => "BULK-{$number}",
            'product_name' => "Produk Bulk {$number}",
            'product_description' => '',
            'product_image' => '',
            'product_image_filename' => '',
            'product_customer_price' => 100000,
            'product_weight' => 100,
            'product_length' => 10,
            'product_width' => 10,
            'product_height' => 10,
            'product_unit' => 'pcs',
            'product_is_package' => 0,
            'product_is_publish' => 1,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
            'product_input_datetime' => now(),
        ]));

        $payload = $products->values()->map(fn (Product $product, int $index): array => [
            'product_id' => $product->getKey(),
            'customer_price' => 200000 + ($index * 10000),
            'member_prices' => [
                ['member_level_id' => 1, 'price' => 130000 + ($index * 10000)],
                ['member_level_id' => 2, 'price' => 150000 + ($index * 10000)],
                ['member_level_id' => 3, 'price' => 170000 + ($index * 10000)],
            ],
        ])->all();

        $this->postJson('/api/v1/admin/product/prices/bulk', $payload)
            ->assertOk()
            ->assertJsonPath('data.updated_count', 2);

        foreach ($payload as $productData) {
            $this->assertDatabaseHas('product', [
                'product_id' => $productData['product_id'],
                'product_customer_price' => $productData['customer_price'],
            ]);
            foreach ($productData['member_prices'] as $memberPrice) {
                $this->assertDatabaseHas('product_price', [
                    'product_price_product_id' => $productData['product_id'],
                    'product_price_member_level_id' => $memberPrice['member_level_id'],
                    'product_price_value' => $memberPrice['price'],
                ]);
            }
        }
    }

    public function test_api_validation_messages_are_in_indonesian(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $this->postJson('/api/v1/admin/products', [])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonPath('message', 'Kategori wajib diisi.')
            ->assertJsonPath('errors.category_id.0', 'Kategori wajib diisi.')
            ->assertJsonPath('errors.customer_price.0', 'Harga pelanggan wajib diisi.');

        $this->getJson('/api/v1/admin/products?limit=101')
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.limit.0',
                'Jumlah data per halaman tidak boleh lebih besar dari 100.'
            );

        $this->postJson('/api/v1/admin/products', [
            'image_url' => 'bukan-url',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.image_url.0', 'Tautan gambar harus berupa tautan yang valid.');
    }

    public function test_missing_api_model_returns_a_safe_process_error(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $this->getJson('/api/v1/admin/product/categories/999999')
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Data yang diminta tidak ditemukan.',
                'error_code' => 'process_error',
            ])
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('trace');
    }

    private function createAdministrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Super Administrator',
            'administrator_group_type' => 'superuser',
            'administrator_group_is_active' => 1,
        ]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->administrator_group_id,
            'administrator_username' => 'product.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Product Admin',
            'administrator_email' => 'product.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
