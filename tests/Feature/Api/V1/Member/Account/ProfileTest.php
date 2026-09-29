<?php

namespace Tests\Feature\Api\V1\Member\Account;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Models\MemberGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

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
