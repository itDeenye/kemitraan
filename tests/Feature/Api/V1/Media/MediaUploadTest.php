<?php

namespace Tests\Feature\Api\V1\Media;

use App\Models\Media;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Support\MediaUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        config([
            'cache.default' => 'array',
            'media.chunk_size' => 5,
            'media.max_size' => 100,
            'media.compression_enabled' => false,
            'media.default_disk' => 'local',
            'media.mime_types' => ['text/plain'],
            'media.collections.test' => [
                'disk' => 'local',
                'mime_types' => ['text/plain'],
            ],
            'media.collections.public-test' => [
                'disk' => 'public',
                'mime_types' => ['text/plain'],
            ],
        ]);
    }

    public function test_media_upload_requires_authentication(): void
    {
        $this->postJson('/api/v1/admin/media/uploads', [])->assertUnauthorized();
    }

    public function test_administrator_can_upload_and_process_media_in_chunks(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        Queue::fake();

        $response = $this->postJson('/api/v1/admin/media/uploads', [
            'collection' => 'test',
            'filename' => 'catatan.txt',
            'mime_type' => 'text/plain',
            'size' => 11,
            'checksum' => hash('sha256', 'hello world'),
        ])->assertCreated()
            ->assertJsonPath('data.chunk_size', 5)
            ->assertJsonPath('data.total_chunks', 3)
            ->assertJsonPath('data.status', Media::STATUS_PENDING);
        $mediaUuid = $response->json('data.id');

        $this->uploadChunk($mediaUuid, 1, 'hello')
            ->assertOk()
            ->assertJsonPath('data.uploaded_chunks', 1);
        $this->uploadChunk($mediaUuid, 2, ' worl')->assertOk();
        $this->uploadChunk($mediaUuid, 3, 'd')
            ->assertOk()
            ->assertJsonPath('data.progress', 100);

        $this->postJson("/api/v1/admin/media/uploads/{$mediaUuid}/complete")
            ->assertOk()
            ->assertJsonPath('message', 'Media berhasil diproses.')
            ->assertJsonPath('data.status', Media::STATUS_READY);
        Queue::assertNothingPushed();

        $media = Media::query()->where('media_uuid', $mediaUuid)->firstOrFail();

        $this->assertSame(Media::STATUS_READY, $media->media_status);
        $this->assertNotNull($media->media_path);
        Storage::disk('local')->assertExists($media->media_path);
        $absoluteMediaPath = Storage::disk('local')->path($media->media_path);
        $this->assertTrue(is_file($absoluteMediaPath), $absoluteMediaPath);
        $this->assertSame(11, filesize($absoluteMediaPath));

        $canonicalPath = "/api/v1/admin/media/uploads/{$mediaUuid}/content.txt";
        $canonicalUrl = (string) $this->getJson("/api/v1/admin/media/uploads/{$mediaUuid}")
            ->assertOk()
            ->json('data.url');
        $this->assertSame($canonicalPath, parse_url($canonicalUrl, PHP_URL_PATH));
        $this->assertStringContainsString('expires=', $canonicalUrl);
        $this->assertStringContainsString('signature=', $canonicalUrl);

        $this->assertSame(
            url($canonicalPath),
            MediaUrl::canonicalPrivateUrl(
                "/api/v1/member/media/uploads/{$mediaUuid}/content",
                'admin',
            ),
        );
        $canonicalizedUrl = MediaUrl::temporaryPrivateUrl(
            "/api/v1/member/media/uploads/{$mediaUuid}/content",
            'admin',
        );
        $this->assertSame($canonicalPath, parse_url((string) $canonicalizedUrl, PHP_URL_PATH));
        $this->get((string) $canonicalizedUrl)->assertOk();

        $this->get($canonicalUrl)
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');
        $this->get($canonicalPath)->assertForbidden();
        $wrongExtensionUrl = URL::temporarySignedRoute(
            'api.v1.admin.media.content',
            now()->addHour(),
            ['media' => $mediaUuid, 'extension' => 'png'],
        );
        $this->get($wrongExtensionUrl)
            ->assertNotFound();

        $this->get("/api/v1/admin/media/uploads/{$mediaUuid}/content")
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');
    }

    public function test_frontend_can_define_a_collection_without_backend_whitelist(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $response = $this->postJson('/api/v1/admin/media/uploads', [
            'collection' => 'Product Images/2026',
            'filename' => 'catatan.txt',
            'mime_type' => 'text/plain',
            'size' => 5,
        ])->assertCreated()
            ->assertJsonPath('data.collection', 'Product Images/2026');

        $this->assertDatabaseHas('media', [
            'media_uuid' => $response->json('data.id'),
            'media_collection' => 'Product Images/2026',
            'media_disk' => 'local',
        ]);
    }

    public function test_profile_and_product_collections_always_use_public_disk(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        foreach (['profile', 'profile_photos', 'product'] as $collection) {
            $mediaUuid = $this->postJson('/api/v1/admin/media/uploads', [
                'collection' => $collection,
                'filename' => "{$collection}.jpg",
                'mime_type' => 'image/jpeg',
                'size' => 5,
            ])->assertCreated()->json('data.id');

            $this->assertDatabaseHas('media', [
                'media_uuid' => $mediaUuid,
                'media_collection' => $collection,
                'media_disk' => 'public',
            ]);
        }
    }

    public function test_public_media_uses_non_expiring_api_content_url(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $response = $this->postJson('/api/v1/admin/media/uploads', [
            'collection' => 'public-test',
            'filename' => 'public.txt',
            'mime_type' => 'text/plain',
            'size' => 5,
        ])->assertCreated();
        $mediaUuid = $response->json('data.id');

        $this->uploadChunk($mediaUuid, 1, 'hello')->assertOk();

        $contentUrl = (string) $this->postJson("/api/v1/admin/media/uploads/{$mediaUuid}/complete")
            ->assertOk()
            ->json('data.url');
        $contentPath = "/api/v1/media/public/{$mediaUuid}/content.txt";

        $this->assertSame($contentPath, parse_url($contentUrl, PHP_URL_PATH));
        $this->assertStringNotContainsString('signature=', $contentUrl);

        $this->app['auth']->forgetGuards();

        $this->get($contentPath)
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');

        $legacyStorageUrl = url("/storage/media/public-test/2026/08/{$mediaUuid}.txt");
        $this->assertSame($contentUrl, MediaUrl::publicUrl($legacyStorageUrl));
        $this->assertSame(
            $contentUrl,
            MediaUrl::publicUrl("https://host-lama.test{$contentPath}"),
        );
    }

    public function test_member_profile_media_can_complete_when_fileinfo_returns_generic_mime(): void
    {
        $this->actingAs($this->createMemberAccount(), 'member_api');

        $mediaUuid = $this->postJson('/api/v1/member/media/uploads', [
            'collection' => 'profile',
            'filename' => 'profile.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 5,
        ])->assertCreated()->json('data.id');

        $this->uploadMemberChunk($mediaUuid, 1, 'abcde')->assertOk();

        $contentUrl = (string) $this->postJson("/api/v1/member/media/uploads/{$mediaUuid}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', Media::STATUS_READY)
            ->assertJsonPath('data.mime_type', 'image/jpeg')
            ->json('data.url');

        $contentPath = "/api/v1/media/public/{$mediaUuid}/content.jpg";
        $this->assertSame($contentPath, parse_url($contentUrl, PHP_URL_PATH));

        $this->assertDatabaseHas('media', [
            'media_uuid' => $mediaUuid,
            'media_collection' => 'profile',
            'media_disk' => 'public',
            'media_mime_type' => 'image/jpeg',
            'media_status' => Media::STATUS_READY,
        ]);
    }

    public function test_private_media_cannot_be_read_through_public_content_route(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $mediaUuid = $this->postJson('/api/v1/admin/media/uploads', [
            'collection' => 'test',
            'filename' => 'private.txt',
            'mime_type' => 'text/plain',
            'size' => 5,
        ])->assertCreated()->json('data.id');

        $this->uploadChunk($mediaUuid, 1, 'hello')->assertOk();
        $this->postJson("/api/v1/admin/media/uploads/{$mediaUuid}/complete")->assertOk();

        $this->get("/api/v1/media/public/{$mediaUuid}/content.txt")->assertNotFound();
    }

    public function test_chunk_size_and_media_ownership_are_enforced(): void
    {
        $owner = $this->createAdministrator('owner');
        $this->actingAs($owner, 'admin_api');

        $mediaUuid = $this->postJson('/api/v1/admin/media/uploads', [
            'collection' => 'test',
            'filename' => 'catatan.txt',
            'mime_type' => 'text/plain',
            'size' => 5,
        ])->assertCreated()->json('data.id');

        $this->uploadChunk($mediaUuid, 1, 'kurang')
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath('message', 'Ukuran bagian berkas tidak sesuai dengan sesi unggah.');

        $this->uploadChunk($mediaUuid, 1, 'salah')
            ->assertOk()
            ->assertJsonPath('data.uploaded_chunks', 1);
        $this->uploadChunk($mediaUuid, 1, 'salah')
            ->assertOk()
            ->assertJsonPath('data.uploaded_chunks', 1);

        $this->actingAs($this->createAdministrator('other'), 'admin_api');
        $this->getJson("/api/v1/admin/media/uploads/{$mediaUuid}")->assertForbidden();
        $this->deleteJson("/api/v1/admin/media/uploads/{$mediaUuid}")->assertForbidden();
    }

    private function uploadChunk(string $mediaUuid, int $chunkNumber, string $contents)
    {
        return $this->post(
            "/api/v1/admin/media/uploads/{$mediaUuid}/chunks/{$chunkNumber}",
            ['chunk' => UploadedFile::fake()->createWithContent("{$chunkNumber}.part", $contents)],
            ['Accept' => 'application/json']
        );
    }

    private function uploadMemberChunk(string $mediaUuid, int $chunkNumber, string $contents)
    {
        return $this->post(
            "/api/v1/member/media/uploads/{$mediaUuid}/chunks/{$chunkNumber}",
            ['chunk' => UploadedFile::fake()->createWithContent("{$chunkNumber}.part", $contents)],
            ['Accept' => 'application/json']
        );
    }

    private function createAdministrator(string $suffix = 'media'): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->firstOrCreate([
            'administrator_group_title' => 'Super Administrator',
        ], [
            'administrator_group_type' => 'superuser',
            'administrator_group_is_active' => 1,
        ]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->administrator_group_id,
            'administrator_username' => "{$suffix}.admin",
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Media Admin',
            'administrator_email' => "{$suffix}.admin@example.test",
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }

    private function createMemberAccount(): MemberAccount
    {
        $group = MemberGroup::query()->create([
            'member_group_name' => 'Media Member Group',
            'member_group_is_active' => 1,
        ]);

        $member = Member::query()->create([
            'member_code' => 'DNYMEDIA001',
            'member_member_level_id' => 1,
            'member_name' => 'Media Member',
            'member_email' => 'media.member@example.test',
            'member_mobilephone' => '628999462641',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->member_id,
            'member_account_member_group_id' => $group->member_group_id,
            'member_account_username' => 'media.member',
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
    }
}
