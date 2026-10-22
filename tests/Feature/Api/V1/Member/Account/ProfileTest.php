<?php

namespace Tests\Feature\Api\V1\Member\Account;

use App\Models\Media;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Models\MemberGroup;
use App\Models\MemberRegistration;
use App\Support\MediaUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\V1\Partnership\MemberRegistrationWorkflowTest;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('duplicatePrimaryPhones')]
    public function test_profile_rejects_another_members_primary_phone_in_equivalent_formats(string $storedPhone): void
    {
        $account = $this->createMemberAccount();
        Member::query()->create([
            'member_code' => 'OTHER-PHONE', 'member_name' => 'Other', 'member_member_level_id' => 1,
            'member_mobilephone' => $storedPhone, 'member_status' => 1, 'member_join_datetime' => now(),
        ]);
        $this->actingAs($account, 'member_api');
        $this->putJson('/api/v1/member/profile', ['name' => 'Must Not Save', 'phone' => '081799998888'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->assertSame('Test Member', $account->member->fresh()->member_name);
        $this->assertSame('08123456789', $account->member->fresh()->member_mobilephone);
    }

    public static function duplicatePrimaryPhones(): array
    {
        return ['local' => ['081799998888'], 'country code' => ['6281799998888'], 'international' => ['+6281799998888']];
    }

    public function test_profile_rejects_a_phone_reserved_by_a_pending_registration(): void
    {
        $account = $this->createMemberAccount();
        MemberRegistration::query()->create([
            'member_registration_member_level_id' => 1, 'member_registration_name' => 'Applicant',
            'member_registration_username' => '', 'member_registration_password' => '',
            'member_registration_mobilephone' => '081799998888', 'member_registration_status' => 'requested',
            'member_registration_datetime' => now(),
        ]);
        $this->actingAs($account, 'member_api');
        $this->putJson('/api/v1/member/profile', ['name' => 'Test Member', 'phone' => '+6281799998888'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->assertSame('08123456789', $account->member->fresh()->member_mobilephone);
    }

    public function test_profile_allows_own_phone_including_its_approved_registration(): void
    {
        $account = $this->createMemberAccount();
        MemberRegistration::query()->create([
            'member_registration_member_level_id' => 1, 'member_registration_member_id' => $account->member->getKey(),
            'member_registration_username' => '', 'member_registration_password' => '',
            'member_registration_name' => 'Test Member', 'member_registration_mobilephone' => '+628123456789',
            'member_registration_status' => 'approved', 'member_registration_datetime' => now(),
        ]);
        $this->actingAs($account, 'member_api');
        $this->putJson('/api/v1/member/profile', ['name' => 'Test Member', 'phone' => '628123456789'])->assertOk();
        $this->assertSame('+628123456789', $account->member->fresh()->member_mobilephone);
    }

    #[DataProvider('validIdentities')]
    public function test_profile_accepts_document_formats(string $type, string $number): void
    {
        $this->actingAs($this->createMemberAccount(), 'member_api');
        $this->putJson('/api/v1/member/profile', [
            'name' => 'Identitas Baru', 'identity_type' => $type, 'identity_no' => $number,
        ])->assertOk()->assertJsonPath('data.member.identity.type', $type)
            ->assertJsonPath('data.member.identity.number', $number);
    }

    #[DataProvider('invalidIdentities')]
    public function test_profile_rejects_changed_invalid_document_numbers(string $type, mixed $number): void
    {
        $this->actingAs($this->createMemberAccount(), 'member_api');
        $this->putJson('/api/v1/member/profile', [
            'name' => 'Identitas Tidak Valid', 'identity_type' => $type, 'identity_no' => $number,
        ])->assertUnprocessable()->assertJsonValidationErrors('identity_no');
    }

    public function test_profile_keeps_legacy_identity_when_omitted_or_resubmitted_unchanged(): void
    {
        $account = $this->createMemberAccount();
        $account->member->update(['member_identity_type' => 'SIM', 'member_identity_no' => '001234567890']);
        $this->actingAs($account, 'member_api');
        $this->putJson('/api/v1/member/profile', ['name' => 'Legacy Identity'])
            ->assertOk()->assertJsonPath('data.member.identity.number', '001234567890');
        $this->putJson('/api/v1/member/profile', [
            'name' => 'Legacy Identity', 'identity_type' => 'SIM', 'identity_no' => '001234567890',
        ])->assertOk()->assertJsonPath('data.member.identity.number', '001234567890');
    }

    public function test_profile_validates_number_against_stored_type_and_type_against_stored_number(): void
    {
        $account = $this->createMemberAccount();
        $account->member->update(['member_identity_type' => 'BPJS', 'member_identity_no' => '0001234567890']);
        $this->actingAs($account, 'member_api');
        $this->putJson('/api/v1/member/profile', ['name' => 'Partial Identity', 'identity_no' => '123'])
            ->assertUnprocessable()->assertJsonValidationErrors('identity_no');
        $this->putJson('/api/v1/member/profile', ['name' => 'Partial Identity', 'identity_type' => 'KTP'])
            ->assertUnprocessable()->assertJsonValidationErrors('identity_no');
    }

    public static function validIdentities(): array
    {
        return MemberRegistrationWorkflowTest::validIdentities();
    }

    public static function invalidIdentities(): array
    {
        return MemberRegistrationWorkflowTest::invalidIdentities();
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/member/profile')->assertUnauthorized();
    }

    public function test_member_can_view_profile(): void
    {
        $account = $this->createMemberAccount();
        $this->createDefaultProfileRelations($account);
        $this->actingAs($account, 'member_api');

        $this->getJson('/api/v1/member/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member.name', 'Test Member')
            ->assertJsonPath('data.member.code', 'MEMBER-TEST')
            ->assertJsonPath('data.member.default_address.full_address', 'Jalan Profil Nomor 1')
            ->assertJsonPath('data.member.default_bank_account.bank_code', 'BCA')
            ->assertJsonPath('data.member.default_bank_account.account_number', '1234567890');
    }

    public function test_member_can_update_profile(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');

        $this->putJson('/api/v1/member/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
            'phone' => '0899999999',
            'gender' => 'Perempuan',
            'birth_date' => '1995-05-05',
            'identity_type' => 'PASPOR',
            'identity_no' => 'A1234567',
            'nib' => '1234567890123',
            'instagram' => '@dny.updated',
            'facebook' => 'DNY Updated',
            'tiktok' => '@dny.tiktok',
        ])
            ->assertOk()
            ->assertJsonPath('data.member.name', 'Updated Name')
            ->assertJsonPath('data.member.identity.type', 'PASPOR')
            ->assertJsonPath('data.member.identity.number', 'A1234567')
            ->assertJsonPath('data.member.nib', '1234567890123')
            ->assertJsonPath('data.member.social_media.instagram', '@dny.updated')
            ->assertJsonPath('data.member.social_media.facebook', 'DNY Updated')
            ->assertJsonPath('data.member.social_media.tiktok', '@dny.tiktok');

        $this->assertDatabaseHas('member', [
            'member_id' => $account->member_account_member_id,
            'member_name' => 'Updated Name',
            'member_email' => 'updated@example.test',
            'member_gender' => 'Perempuan',
            'member_identity_type' => 'PASPOR',
            'member_identity_no' => 'A1234567',
            'member_nib' => '1234567890123',
            'member_instagram' => '@dny.updated',
            'member_facebook' => 'DNY Updated',
            'member_tiktok' => '@dny.tiktok',
        ]);
    }

    public function test_profile_displays_documents_and_all_owned_addresses_and_bank_accounts(): void
    {
        $account = $this->createMemberAccount();
        $this->createDefaultProfileRelations($account);
        $account->member->update([
            'member_identity_type' => 'BPJS', 'member_identity_no' => '000123456789',
            'member_identity_image' => 'https://example.test/identity.jpg',
            'member_nib' => '0123456789012', 'member_npwp' => '0123456789012345',
            'member_image' => 'https://example.test/profile.jpg',
        ]);
        $address = $account->member->addresses()->firstOrFail()->replicate();
        $address->fill(['member_address_label' => 'Kantor', 'member_address_full' => 'Jalan Kantor Nomor 2',
            'member_address_is_default' => 0])->save();
        $bank = $account->member->bankAccounts()->firstOrFail()->replicate();
        $bank->fill(['member_bank_account_number' => '0012345678', 'member_bank_account_is_default' => 0,
            'member_bank_account_is_active' => 0])->save();
        $other = Member::query()->create(['member_code' => 'OTHER-TEST', 'member_name' => 'Other Member',
            'member_member_level_id' => 1, 'member_status' => 1, 'member_join_datetime' => now()]);
        $address->replicate()->fill(['member_address_member_id' => $other->getKey()])->save();
        $bank->replicate()->fill(['member_bank_account_member_id' => $other->getKey()])->save();

        $this->actingAs($account, 'member_api');
        $this->getJson('/api/v1/member/profile')
            ->assertOk()->assertJsonPath('data.member.identity.type', 'BPJS')
            ->assertJsonPath('data.member.identity.image', 'https://example.test/identity.jpg')
            ->assertJsonPath('data.member.image', 'https://example.test/profile.jpg')
            ->assertJsonPath('data.member.nib', '0123456789012')
            ->assertJsonPath('data.member.npwp', '0123456789012345')
            ->assertJsonPath('data.member.payment_proof_image_url', null)
            ->assertJsonCount(2, 'data.member.addresses')->assertJsonCount(2, 'data.member.bank_accounts')
            ->assertJsonPath('data.member.addresses.1.full_address', 'Jalan Kantor Nomor 2')
            ->assertJsonPath('data.member.bank_accounts.1.account_number', '0012345678')
            ->assertJsonPath('data.member.bank_accounts.1.is_active', false)
            ->assertJsonMissingPath('data.member_account_password')->assertJsonMissingPath('data.member_account_pin');
    }

    public function test_profile_supports_separate_identity_and_profile_images_and_keeps_omitted_uploads(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');
        $this->putJson('/api/v1/member/profile', [
            'name' => 'Two Uploads', 'identity_type' => 'bpjs', 'identity_no' => '0001234567890',
            'identity_image_url' => 'https://example.test/identity.jpg',
            'image_url' => 'https://example.test/profile.jpg',
        ])->assertOk()->assertJsonPath('data.member.identity.type', 'BPJS')
            ->assertJsonPath('data.member.identity.image', 'https://example.test/identity.jpg')
            ->assertJsonPath('data.member.image', 'https://example.test/profile.jpg');
        $this->putJson('/api/v1/member/profile', ['name' => 'Still Two Uploads'])
            ->assertOk()->assertJsonPath('data.member.identity.image', 'https://example.test/identity.jpg')
            ->assertJsonPath('data.member.image', 'https://example.test/profile.jpg');
        $this->assertDatabaseHas('member', ['member_id' => $account->member_account_member_id,
            'member_identity_image_filename' => 'identity.jpg', 'member_image_filename' => 'profile.jpg']);
        $this->putJson('/api/v1/member/profile', [
            'name' => 'Cleared Uploads', 'identity_image_url' => null, 'image_url' => null,
        ])->assertOk()->assertJsonPath('data.member.identity.image', null)->assertJsonPath('data.member.image', null);
        $this->assertDatabaseHas('member', ['member_id' => $account->member_account_member_id,
            'member_identity_image_filename' => '', 'member_image_filename' => null]);
    }

    public function test_private_identity_upload_is_stored_canonically_and_profile_returns_a_working_signed_url(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');
        Storage::fake('local');
        $uuid = (string) Str::uuid();
        $path = "media/general/{$uuid}.jpg";
        Storage::disk('local')->put($path, 'identity-document');
        $media = Media::query()->create([
            'media_uuid' => $uuid, 'media_uploader_type' => $account->getMorphClass(),
            'media_uploader_id' => $account->getKey(), 'media_collection' => 'general',
            'media_original_name' => 'identity.jpg', 'media_original_mime_type' => 'image/jpeg',
            'media_original_size' => 17, 'media_mime_type' => 'image/jpeg', 'media_size' => 17,
            'media_chunk_size' => 1024, 'media_total_chunks' => 1, 'media_uploaded_chunks' => 1,
            'media_status' => Media::STATUS_READY, 'media_disk' => 'local', 'media_path' => $path,
            'media_expires_at' => now()->addDay(),
        ]);
        $this->putJson('/api/v1/member/profile', [
            'name' => 'Private Identity', 'identity_image_url' => MediaUrl::privateContentUrl($media, 'member'),
        ])->assertOk();
        $this->assertStringNotContainsString('signature=', $account->member->fresh()->member_identity_image);
        $url = $this->getJson('/api/v1/member/profile')->assertOk()->json('data.member.identity.image');
        $this->assertStringContainsString('signature=', $url);
        $this->get($url)->assertOk();
    }

    public function test_profile_rejects_unknown_identity_types(): void
    {
        $this->actingAs($this->createMemberAccount(), 'member_api');
        $this->putJson('/api/v1/member/profile', ['name' => 'Invalid Identity', 'identity_type' => 'LAINNYA'])
            ->assertUnprocessable()->assertJsonValidationErrors('identity_type');
    }

    public function test_member_cannot_replace_registration_payment_proof(): void
    {
        $account = $this->createMemberAccount();
        $registration = MemberRegistration::query()->create([
            'member_registration_member_level_id' => $account->member->member_member_level_id,
            'member_registration_member_id' => $account->member->getKey(),
            'member_registration_name' => 'Test Member',
            'member_registration_username' => '',
            'member_registration_password' => '',
            'member_registration_status' => 'approved',
            'member_registration_datetime' => now(),
            'member_registration_payment_proof_image' => 'https://example.test/admin-proof.webp',
        ]);
        $this->actingAs($account, 'member_api');

        $this->putJson('/api/v1/member/profile', [
            'name' => 'Test Member',
            'payment_proof_image_url' => 'https://example.test/member-proof.webp',
        ])->assertUnprocessable()->assertJsonValidationErrors('payment_proof_image_url');

        $this->assertSame('https://example.test/admin-proof.webp', $registration->fresh()->member_registration_payment_proof_image);
    }

    public function test_member_can_update_password(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');

        $this->putJson('/api/v1/member/profile/password', [
            'current_password' => 'Secret123',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertOk();

        $account->refresh();
        $this->assertTrue(Hash::check('NewPassword123', $account->member_account_password));
    }

    public function test_member_can_update_profile_photo_from_media_url(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');

        $imageUrl = '/api/v1/member/media/uploads/test-upload/content.webp';

        $this->putJson('/api/v1/member/profile/photo', [
            'image_url' => $imageUrl,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member.image', $imageUrl);

        $this->assertDatabaseHas('member', [
            'member_id' => $account->member_account_member_id,
            'member_image' => $imageUrl,
            'member_image_filename' => 'content.webp',
        ]);
    }

    public function test_profile_photo_requires_image_url_key(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');

        $this->putJson('/api/v1/member/profile/photo', [])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonValidationErrors('image_url');
    }

    public function test_update_password_fails_if_current_password_is_wrong(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');

        $this->putJson('/api/v1/member/profile/password', [
            'current_password' => 'WrongPassword',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'process_error');
    }

    private function createMemberAccount(): MemberAccount
    {
        $group = MemberGroup::query()->create([
            'member_group_name' => 'Test Group',
            'member_group_is_active' => 1,
        ]);

        $member = Member::query()->create([
            'member_code' => 'MEMBER-TEST',
            'member_member_level_id' => 1,
            'member_name' => 'Test Member',
            'member_email' => 'test@example.test',
            'member_mobilephone' => '08123456789',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->member_id,
            'member_account_member_group_id' => $group->member_group_id,
            'member_account_username' => 'test.member',
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
    }

    private function createDefaultProfileRelations(MemberAccount $account): void
    {
        MemberAddress::query()->create([
            'member_address_member_id' => $account->member_account_member_id,
            'member_address_label' => 'Rumah',
            'member_address_recipient' => 'Test Member',
            'member_address_phone' => '628123456789',
            'member_address_full' => 'Jalan Profil Nomor 1',
            'member_address_country_id' => 1,
            'member_address_is_default' => 1,
        ]);

        $bankId = DB::table('ref_bank')->insertGetId([
            'bank_code' => 'BCA',
            'bank_name' => 'Bank Central Asia',
        ], 'bank_id');

        MemberBankAccount::query()->create([
            'member_bank_account_member_id' => $account->member_account_member_id,
            'member_bank_account_bank_id' => $bankId,
            'member_bank_account_name' => 'Test Member',
            'member_bank_account_number' => '1234567890',
            'member_bank_account_is_active' => 1,
            'member_bank_account_is_default' => 1,
        ]);
    }
}
