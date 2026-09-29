<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberBankAccount;
use App\Models\MemberGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_login_and_receive_profile_without_dynamic_menus(): void
    {
        $this->createMemberAccount();

        $response = $this->postJson('/api/v1/member/auth/login', [
            'username' => 'member.test',
            'password' => 'Secret123',
            'device_name' => 'mobile-test',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'member.test')
            ->assertJsonPath('data.user.member.level.code', 'RSL')
            ->assertJsonPath('data.user.member.level.name', 'Reseller')
            ->assertJsonPath('data.user.has_bank_account', false)
            ->assertJsonPath('data.user.is_default_password', false)
            ->assertJsonMissingPath('data.menus')
            ->assertJsonStructure(['data' => [
                'access_token',
                'refresh_token',
                'expires_at',
                'refresh_token_expires_at',
            ]]);

        $this->withToken($response->json('data.access_token'))
            ->getJson('/api/v1/member/auth/me')
            ->assertOk()
            ->assertJsonPath('data.member.code', 'MEMBER-001')
            ->assertJsonPath('data.has_bank_account', false)
            ->assertJsonPath('data.is_default_password', false);

        $refreshToken = $response->json('data.refresh_token');
        $refreshed = $this->postJson('/api/v1/member/auth/refresh', [
            'refresh_token' => $refreshToken,
        ])->assertOk()
            ->assertJsonPath('message', 'Sesi berhasil diperbarui.');
        $this->assertNotSame($refreshToken, $refreshed->json('data.refresh_token'));
        $this->postJson('/api/v1/member/auth/refresh', [
            'refresh_token' => $refreshToken,
        ])->assertUnauthorized();
        $this->withToken($refreshed->json('data.access_token'))
            ->getJson('/api/v1/member/auth/me')
            ->assertOk()
            ->assertJsonPath('data.member.code', 'MEMBER-001');

        $this->getJson('/api/v1/member/menus')->assertNotFound();
    }

    public function test_suspended_member_cannot_login(): void
    {
        $account = $this->createMemberAccount();
        $account->member()->update(['member_status' => 2]);

        $this->postJson('/api/v1/member/auth/login', [
            'username' => 'member.test',
            'password' => 'Secret123',
        ])->assertUnauthorized();
    }

    public function test_invalid_member_credentials_return_login_instruction(): void
    {
        $this->createMemberAccount();

        $this->postJson('/api/v1/member/auth/login', [
            'username' => 'member.test',
            'password' => 'Wrong123',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Username atau kata sandi tidak sesuai.')
            ->assertJsonPath('error_code', 'process_error');

        $this->getJson('/api/v1/member/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Silakan masuk untuk melanjutkan.');
    }

    public function test_me_flags_active_bank_account_and_birth_date_password(): void
    {
        $account = $this->createMemberAccount();
        $account->update(['member_account_password' => Hash::make('01011990')]);
        MemberBankAccount::query()->create([
            'member_bank_account_member_id' => $account->member_account_member_id,
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Rekening Tidak Aktif',
            'member_bank_account_number' => '1111111111',
            'member_bank_account_is_active' => 0,
            'member_bank_account_is_default' => 0,
        ]);

        $login = $this->postJson('/api/v1/member/auth/login', [
            'username' => 'member.test',
            'password' => '01011990',
        ])->assertOk()
            ->assertJsonPath('data.user.has_bank_account', false)
            ->assertJsonPath('data.user.is_default_password', true);

        $token = $login->json('data.access_token');
        $this->withToken($token)->getJson('/api/v1/member/auth/me')
            ->assertOk()
            ->assertJsonPath('data.has_bank_account', false)
            ->assertJsonPath('data.is_default_password', true);

        MemberBankAccount::query()->create([
            'member_bank_account_member_id' => $account->member_account_member_id,
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Rekening Aktif',
            'member_bank_account_number' => '2222222222',
            'member_bank_account_is_active' => 1,
            'member_bank_account_is_default' => 1,
        ]);
        $account->update(['member_account_password' => Hash::make('PasswordBaru123')]);

        $this->withToken($token)->getJson('/api/v1/member/auth/me')
            ->assertOk()
            ->assertJsonPath('data.has_bank_account', true)
            ->assertJsonPath('data.is_default_password', false);
    }

    private function createMemberAccount(): MemberAccount
    {
        $group = MemberGroup::query()->create([
            'member_group_name' => 'Reseller',
            'member_group_description' => 'Reseller test',
            'member_group_is_active' => 1,
        ]);
        $member = Member::query()->create([
            'member_code' => 'MEMBER-001',
            'member_member_level_id' => 3,
            'member_name' => 'Member Test',
            'member_email' => 'member@example.test',
            'member_mobilephone' => '08123456789',
            'member_birth_date' => '1990-01-01',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->member_id,
            'member_account_member_group_id' => $group->member_group_id,
            'member_account_username' => 'member.test',
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
    }
}
