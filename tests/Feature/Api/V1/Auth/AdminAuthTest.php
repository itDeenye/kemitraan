<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_login_access_profile_and_logout(): void
    {
        $administrator = $this->createAdministrator();

        $login = $this->postJson('/api/v1/admin/auth/login', [
            'username' => $administrator->administrator_username,
            'password' => 'Secret123',
            'device_name' => 'admin-test',
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.username', 'admin.test')
            ->assertJsonStructure(['data' => [
                'access_token',
                'refresh_token',
                'expires_at',
                'refresh_token_expires_at',
            ]]);

        $token = $login->json('data.access_token');
        $refreshToken = $login->json('data.refresh_token');
        $this->withToken($token)->getJson('/api/v1/admin/auth/me')
            ->assertOk()->assertJsonPath('data.username', 'admin.test');

        $refreshed = $this->postJson('/api/v1/admin/auth/refresh', [
            'refresh_token' => $refreshToken,
        ])->assertOk()
            ->assertJsonPath('message', 'Sesi administrator berhasil diperbarui.')
            ->assertJsonStructure(['data' => [
                'access_token',
                'refresh_token',
                'expires_at',
                'refresh_token_expires_at',
            ]]);
        $this->assertNotSame($refreshToken, $refreshed->json('data.refresh_token'));
        $this->postJson('/api/v1/admin/auth/refresh', [
            'refresh_token' => $refreshToken,
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Sesi Anda telah berakhir. Silakan masuk kembali.');

        $token = $refreshed->json('data.access_token');
        $refreshToken = $refreshed->json('data.refresh_token');
        $this->withToken($token)->getJson('/api/v1/admin/auth/me')
            ->assertOk()->assertJsonPath('data.username', 'admin.test');
        $this->withToken($token)->postJson('/api/v1/admin/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/admin/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Silakan masuk untuk melanjutkan.');
        $this->postJson('/api/v1/admin/auth/refresh', [
            'refresh_token' => $refreshToken,
        ])->assertUnauthorized();
    }

    public function test_administrator_is_locked_after_repeated_failed_logins(): void
    {
        $this->createAdministrator();

        foreach (range(1, 4) as $attempt) {
            $this->postJson('/api/v1/admin/auth/login', [
                'username' => 'admin.test',
                'password' => 'Wrong123',
            ])->assertUnauthorized()
                ->assertJsonPath('message', 'Username atau kata sandi tidak sesuai.');
        }

        $this->postJson('/api/v1/admin/auth/login', [
            'username' => 'admin.test',
            'password' => 'Wrong123',
        ])->assertStatus(423)->assertJsonPath('success', false);

        $this->assertDatabaseHas('site_administrator', [
            'administrator_username' => 'admin.test',
            'administrator_failed_login_attempts' => 5,
        ]);
    }

    public function test_expired_refresh_token_cannot_be_used(): void
    {
        $administrator = $this->createAdministrator();
        $login = $this->postJson('/api/v1/admin/auth/login', [
            'username' => $administrator->administrator_username,
            'password' => 'Secret123',
        ])->assertOk();

        DB::table('auth_refresh_token')->update([
            'refresh_token_expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/v1/admin/auth/refresh', [
            'refresh_token' => $login->json('data.refresh_token'),
        ])->assertUnauthorized()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath('message', 'Sesi Anda telah berakhir. Silakan masuk kembali.');
    }

    public function test_administrator_captcha_endpoint_is_not_available(): void
    {
        $this->getJson('/api/v1/admin/auth/captcha')->assertNotFound();
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
            'administrator_username' => 'admin.test',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Admin Test',
            'administrator_email' => 'admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
