<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_control_management_requires_an_administrator(): void
    {
        $this->getJson('/api/v1/admin/system/roles')->assertUnauthorized();
        $this->getJson('/api/v1/admin/system/menus')->assertUnauthorized();
        $this->getJson('/api/v1/admin/system/member-menus')->assertNotFound();
    }

    public function test_administrator_can_manage_roles_menus_and_privileges(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $parentId = $this->postJson('/api/v1/admin/system/menus', [
            'title' => 'System',
            'description' => 'System management',
            'link' => '/system',
            'icon' => 'mdi-cog',
            'order' => 10,
            'is_active' => true,
        ])->assertSuccessful()->json('data.id');

        $childId = $this->postJson('/api/v1/admin/system/menus', [
            'parent_id' => $parentId,
            'title' => 'Roles',
            'description' => 'Role management',
            'link' => '/system/roles',
            'icon' => 'mdi-shield',
            'order' => 1,
            'is_active' => true,
        ])->assertSuccessful()->json('data.id');

        $this->getJson('/api/v1/admin/system/menus/tree')
            ->assertOk()
            ->assertJsonPath('data.0.id', $parentId)
            ->assertJsonPath('data.0.children.0.id', $childId);

        $roleId = $this->postJson('/api/v1/admin/system/roles', [
            'title' => 'Product Administrator',
            'type' => 'administrator',
            'is_active' => true,
        ])->assertSuccessful()->json('data.id');

        $this->putJson("/api/v1/admin/system/roles/{$roleId}/privileges", [
            'menus' => [
                ['menu_id' => $parentId],
                ['menu_id' => $childId],
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['menu_id' => $childId])
            ->assertJsonMissingPath('data.0.actions');

        $this->getJson("/api/v1/admin/system/roles/{$roleId}/privileges")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/admin/system/roles?search=product&sort=title')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.title', 'Product Administrator');

        $this->deleteJson("/api/v1/admin/system/menus/{$parentId}")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        $this->deleteJson("/api/v1/admin/system/roles/{$roleId}")->assertOk();
        $this->assertDatabaseMissing('site_administrator_privilege', [
            'administrator_privilege_administrator_group_id' => $roleId,
        ]);

        $this->deleteJson("/api/v1/admin/system/menus/{$childId}")->assertOk();
        $this->deleteJson("/api/v1/admin/system/menus/{$parentId}")->assertOk();
    }

    public function test_role_in_use_cannot_be_deleted_and_privilege_menu_is_validated(): void
    {
        $administrator = $this->createAdministrator();
        $this->actingAs($administrator, 'admin_api');
        $roleId = $administrator->administrator_administrator_group_id;

        $this->deleteJson("/api/v1/admin/system/roles/{$roleId}")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        $this->putJson("/api/v1/admin/system/roles/{$roleId}/privileges", [
            'menus' => [
                ['menu_id' => 999999],
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonValidationErrors(['menus.0.menu_id']);

        $this->putJson("/api/v1/admin/system/roles/{$roleId}/privileges", [
            'menus' => [
                ['menu_id' => 1, 'actions' => ['read']],
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonValidationErrors(['menus.0']);
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
            'administrator_username' => 'access.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Access Admin',
            'administrator_email' => 'access.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
