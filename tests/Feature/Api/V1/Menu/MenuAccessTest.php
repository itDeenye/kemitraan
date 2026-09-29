<?php

namespace Tests\Feature\Api\V1\Menu;

use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Models\SiteAdministratorMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menu_only_contains_authorized_items_and_their_ancestors(): void
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Finance',
            'administrator_group_type' => 'administrator',
            'administrator_group_is_active' => 1,
        ]);
        $parent = $this->menu('Finance', 0, 1);
        $allowed = $this->menu('Payout', $parent->administrator_menu_id, 1);
        $this->menu('Users', 0, 2);
        $group->menus()->attach($allowed->administrator_menu_id);
        $administrator = SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->administrator_group_id,
            'administrator_username' => 'finance.test',
            'administrator_password' => 'unused',
            'administrator_name' => 'Finance Test',
            'administrator_email' => 'finance@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
        $this->actingAs($administrator, 'admin_api');

        $response = $this->getJson('/api/v1/admin/menus');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Finance')
            ->assertJsonPath('data.0.children.0.title', 'Payout')
            ->assertJsonMissingPath('data.0.children.0.actions');
    }

    private function menu(string $title, int $parentId, int $order): SiteAdministratorMenu
    {
        return SiteAdministratorMenu::query()->create([
            'administrator_menu_par_id' => $parentId,
            'administrator_menu_title' => $title,
            'administrator_menu_description' => $title,
            'administrator_menu_link' => '/'.strtolower($title),
            'administrator_menu_icon' => '',
            'administrator_menu_class' => '',
            'administrator_menu_order_by' => $order,
            'administrator_menu_is_active' => 1,
        ]);
    }
}
