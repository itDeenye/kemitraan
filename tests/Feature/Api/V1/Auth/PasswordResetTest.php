<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Mail\PasswordResetMail;
use App\Models\AuthRefreshToken;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_request_and_use_password_reset_link_once(): void
    {
        Mail::fake();
        config(['dny_auth.password_reset_urls.member' => 'https://member.example.test/reset-password']);
        $account = $this->createMemberAccount();
        $account->forceFill([
            'member_account_failed_login_attempts' => 5,
            'member_account_last_failed_login_datetime' => now(),
            'member_account_locked_until' => now()->addMinutes(15),
        ])->save();
        $refreshToken = $this->createRefreshToken('member', (int) $account->getKey());

        $this->postJson('/api/v1/member/auth/forgot-password', [
            'identifier' => 'member-reset-001',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Tautan reset password sudah dikirim ke email Anda. Silakan cek email.')
            ->assertJsonPath('data', null);

        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail): bool {
            return $mail->hasTo('member@example.test')
                && $mail->accountName === 'Member Test'
                && str_starts_with($mail->resetUrl, 'https://member.example.test/reset-password?')
                && str_contains($mail->render(), 'Buat Password Baru');
        });

        $this->postJson('/api/v1/member/auth/forgot-password', [
            'identifier' => 'MEMBER@EXAMPLE.TEST',
        ])->assertOk();
        $this->postJson('/api/v1/member/auth/forgot-password', [
            'email' => 'member@example.test',
        ])->assertOk();
        Mail::assertSent(PasswordResetMail::class, 1);

        $mail = Mail::sent(PasswordResetMail::class)->first();
        $this->assertInstanceOf(PasswordResetMail::class, $mail);
        $query = $this->resetUrlQuery($mail->resetUrl);
        $storedToken = DB::table('member_password_reset_tokens')
            ->where('email', 'member@example.test')
            ->value('token');
        $this->assertArrayNotHasKey('email', $query);
        $this->assertNotSame($query['token'], $storedToken);
        $this->assertTrue(Hash::check($this->brokerToken($query['token']), $storedToken));

        $this->postJson('/api/v1/member/auth/reset-password', [
            'token' => $query['token'],
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertOk()
            ->assertJsonPath('message', 'Password berhasil diatur ulang. Silakan masuk kembali.');

        $account->refresh();
        $this->assertTrue(Hash::check('NewSecret123', $account->member_account_password));
        $this->assertSame(0, $account->member_account_failed_login_attempts);
        $this->assertNull($account->member_account_locked_until);
        $this->assertNotNull($refreshToken->refresh()->refresh_token_revoked_at);
        $this->assertDatabaseMissing('member_password_reset_tokens', ['email' => 'member@example.test']);

        $this->postJson('/api/v1/member/auth/reset-password', [
            'token' => $query['token'],
            'password' => 'AnotherSecret123',
            'password_confirmation' => 'AnotherSecret123',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Tautan reset password tidak valid atau sudah kedaluwarsa.')
            ->assertJsonPath('error_code', 'process_error');
    }

    public function test_unknown_member_email_returns_generic_success_without_sending_email(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/member/auth/forgot-password', [
            'identifier' => '0000/9999/0000',
        ])->assertOk()
            ->assertJsonPath('message', 'Tautan reset password sudah dikirim ke email Anda. Silakan cek email.');

        Mail::assertNothingSent();
    }

    public function test_expired_member_reset_token_is_rejected(): void
    {
        Mail::fake();
        $this->createMemberAccount();

        $this->postJson('/api/v1/member/auth/forgot-password', [
            'email' => 'member@example.test',
        ])->assertOk();

        $mail = Mail::sent(PasswordResetMail::class)->first();
        $this->assertInstanceOf(PasswordResetMail::class, $mail);
        $query = $this->resetUrlQuery($mail->resetUrl);
        DB::table('member_password_reset_tokens')
            ->where('email', 'member@example.test')
            ->update(['created_at' => now()->subMinutes(61)]);

        $this->postJson('/api/v1/member/auth/reset-password', [
            'token' => $query['token'],
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Tautan reset password tidak valid atau sudah kedaluwarsa.');
    }

    public function test_administrator_can_request_and_use_password_reset_link(): void
    {
        Mail::fake();
        config(['dny_auth.password_reset_urls.admin' => 'https://admin.example.test/reset-password']);
        $administrator = $this->createAdministrator();
        $refreshToken = $this->createRefreshToken('admin', (int) $administrator->getKey());

        $this->postJson('/api/v1/admin/auth/forgot-password', [
            'identifier' => 'ADMIN.RESET',
        ])->assertOk()
            ->assertJsonPath('message', 'Tautan reset password sudah dikirim ke email Anda. Silakan cek email.');

        Mail::assertSent(PasswordResetMail::class, fn (PasswordResetMail $mail): bool => $mail->hasTo('admin@example.test')
            && $mail->accountName === 'Admin Test'
            && str_starts_with($mail->resetUrl, 'https://admin.example.test/reset-password?'));

        $mail = Mail::sent(PasswordResetMail::class)->first();
        $this->assertInstanceOf(PasswordResetMail::class, $mail);
        $query = $this->resetUrlQuery($mail->resetUrl);

        $this->postJson('/api/v1/admin/auth/forgot-password', [
            'email' => 'admin@example.test',
        ])->assertOk();
        Mail::assertSent(PasswordResetMail::class, 1);

        $this->postJson('/api/v1/admin/auth/reset-password', [
            'token' => $query['token'],
            'password' => 'NewAdminSecret123',
            'password_confirmation' => 'NewAdminSecret123',
        ])->assertOk()
            ->assertJsonPath('message', 'Password administrator berhasil diatur ulang. Silakan masuk kembali.');

        $this->assertTrue(Hash::check('NewAdminSecret123', $administrator->refresh()->administrator_password));
        $this->assertNotNull($refreshToken->refresh()->refresh_token_revoked_at);
        $this->assertDatabaseMissing('administrator_password_reset_tokens', ['email' => 'admin@example.test']);
    }

    public function test_reset_password_requires_confirmation_and_valid_token(): void
    {
        $this->createMemberAccount();

        $this->postJson('/api/v1/member/auth/reset-password', [
            'token' => 'invalid-token',
            'password' => 'NewSecret123',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonValidationErrors('password');

        $this->postJson('/api/v1/member/auth/reset-password', [
            'token' => 'invalid-token',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');
    }

    private function createMemberAccount(): MemberAccount
    {
        $group = MemberGroup::query()->create([
            'member_group_name' => 'Reseller',
            'member_group_description' => 'Reseller test',
            'member_group_is_active' => 1,
        ]);
        $member = Member::query()->create([
            'member_code' => 'MEMBER-RESET-001',
            'member_member_level_id' => 3,
            'member_name' => 'Member Test',
            'member_email' => 'member@example.test',
            'member_mobilephone' => '+628123456789',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->member_id,
            'member_account_member_group_id' => $group->member_group_id,
            'member_account_username' => 'member.reset',
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
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
            'administrator_username' => 'admin.reset',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Admin Test',
            'administrator_email' => 'admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }

    private function createRefreshToken(string $ownerType, int $ownerId): AuthRefreshToken
    {
        return AuthRefreshToken::query()->create([
            'refresh_token_owner_type' => $ownerType,
            'refresh_token_owner_id' => $ownerId,
            'refresh_token_hash' => hash('sha256', $ownerType.'-refresh-token'),
            'refresh_token_device_name' => 'password-reset-test',
            'refresh_token_expires_at' => now()->addDay(),
            'refresh_token_created_at' => now(),
        ]);
    }

    /** @return array<string, string> */
    private function resetUrlQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return array_map(static fn (mixed $value): string => (string) $value, $query);
    }

    private function brokerToken(string $publicToken): string
    {
        return explode('.', $publicToken, 2)[1] ?? '';
    }
}
