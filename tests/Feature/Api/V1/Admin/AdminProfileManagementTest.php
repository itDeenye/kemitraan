<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_an_administrator(): void
    {
        $this->getJson('/api/v1/admin/profile')->assertUnauthorized();
        $this->putJson('/api/v1/admin/profile', [])->assertUnauthorized();
    }

    public function test_administrator_can_view_and_update_profile(): void
    {
        $administrator = $this->createAdministrator();
        $this->actingAs($administrator, 'admin_api');

        $this->getJson('/api/v1/admin/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.username', 'profile.admin')
            ->assertJsonPath('data.role.name', 'Administrator');

        $this->putJson('/api/v1/admin/profile', [
            'name' => 'Administrator Baru',
            'email' => 'administrator.baru@example.test',
            'image_url' => 'https://cdn.example.test/admin/profile.webp',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Administrator Baru')
            ->assertJsonPath('data.email', 'administrator.baru@example.test')
            ->assertJsonPath('data.image', 'https://cdn.example.test/admin/profile.webp');

        $this->putJson('/api/v1/admin/profile', [
            'name' => 'Administrator',
            'email' => 'bukan-email',
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonPath('errors.email.0', 'Email harus berupa alamat email yang valid.');
    }

    public function test_administrator_can_change_password_using_the_current_password(): void
    {
        $administrator = $this->createAdministrator();
        $this->actingAs($administrator, 'admin_api');

        $this->putJson('/api/v1/admin/profile/password', [
            'current_password' => 'Salah123',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonPath('errors.current_password.0', 'Kata sandi lama tidak sesuai.');

        $this->putJson('/api/v1/admin/profile/password', [
            'current_password' => 'Secret123',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertOk()
            ->assertJsonPath('message', 'Kata sandi administrator berhasil diperbarui.');

        $this->assertTrue(Hash::check(
            'PasswordBaru123',
            $administrator->refresh()->administrator_password
        ));
    }

    private function createAdministrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Administrator',
            'administrator_group_type' => 'administrator',
            'administrator_group_is_active' => 1,
        ]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->getKey(),
            'administrator_username' => 'profile.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Profile Admin',
            'administrator_email' => 'profile.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
