<?php

namespace Tests\Feature\Console;

use App\Models\Media;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneUnusedImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_only_deletes_expired_unreferenced_images(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $orphan = $this->createMedia('image/webp', now()->subHour());
        $referenced = $this->createMedia('image/webp', now()->subHour());
        $fresh = $this->createMedia('image/webp', now()->addHour());
        $expiredVideo = $this->createMedia('video/mp4', now()->subHour(), 'mp4');

        foreach ([$orphan, $referenced, $fresh, $expiredVideo] as $media) {
            Storage::disk('public')->put($media->media_path, 'content');
            Storage::disk('local')->put("media/chunks/{$media->media_uuid}/1.part", 'chunk');
        }

        $category = ProductCategory::query()->create([
            'product_category_name' => 'Media Reference',
            'product_category_description' => 'Media reference test',
            'product_category_is_active' => 1,
        ]);
        Product::query()->create([
            'product_product_category_id' => $category->getKey(),
            'product_code' => 'MEDIA-REF',
            'product_name' => 'Referenced Image',
            'product_image' => $referenced->media_path,
            'product_image_filename' => 'referenced.webp',
            'product_input_datetime' => now(),
        ]);

        $this->artisan('media:prune-unused')
            ->expectsOutput('Deleted 1 unused expired image.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('media', ['media_id' => $orphan->getKey()]);
        Storage::disk('public')->assertMissing($orphan->media_path);
        Storage::disk('local')->assertMissing("media/chunks/{$orphan->media_uuid}");

        foreach ([$referenced, $fresh, $expiredVideo] as $media) {
            $this->assertDatabaseHas('media', ['media_id' => $media->getKey()]);
            Storage::disk('public')->assertExists($media->media_path);
            Storage::disk('local')->assertExists("media/chunks/{$media->media_uuid}/1.part");
        }
    }

    private function createMedia($mimeType, $expiresAt, string $extension = 'webp'): Media
    {
        $uuid = (string) Str::uuid();

        return Media::query()->create([
            'media_uuid' => $uuid,
            'media_uploader_type' => 'test',
            'media_uploader_id' => 1,
            'media_collection' => 'product',
            'media_original_name' => "image.{$extension}",
            'media_original_mime_type' => $mimeType,
            'media_original_size' => 7,
            'media_mime_type' => $mimeType,
            'media_size' => 7,
            'media_chunk_size' => 7,
            'media_total_chunks' => 1,
            'media_uploaded_chunks' => 1,
            'media_status' => Media::STATUS_READY,
            'media_disk' => 'public',
            'media_path' => "media/product/2026/10/{$uuid}.{$extension}",
            'media_expires_at' => $expiresAt,
        ]);
    }
}
