<?php

namespace App\Services\System;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\SiteAdministratorGroup;
use App\Models\SiteAdministratorMenu;
use App\Models\SiteAdministratorPrivilege;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminAccessControlService
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function roles(array $params): array
    {
        return DataTable::select([
            'site_administrator_group.administrator_group_id as id',
            'site_administrator_group.administrator_group_title as title',
            'site_administrator_group.administrator_group_type as type',
            'site_administrator_group.administrator_group_is_active as is_active',
        ])
            ->selectRaw('(SELECT COUNT(*) FROM site_administrator WHERE site_administrator.administrator_administrator_group_id = site_administrator_group.administrator_group_id) as administrator_count')
            ->from('site_administrator_group')
            ->search(['title'])
            ->defaultSort('title')
            ->get($params);
    }

    public function role(SiteAdministratorGroup $role): SiteAdministratorGroup
    {
        return $role->loadCount('administrators');
    }

    /** @param array<string, mixed> $data */
    public function createRole(array $data): SiteAdministratorGroup
    {
        $role = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => $data['title'],
            'administrator_group_type' => $data['type'],
            'administrator_group_is_active' => $data['is_active'] ?? 1,
        ]);

        return $this->role($role);
    }

    /** @param array<string, mixed> $data */
    public function updateRole(SiteAdministratorGroup $role, array $data): SiteAdministratorGroup
    {
        $attributes = [
            'administrator_group_title' => $data['title'],
            'administrator_group_type' => $data['type'],
        ];

        if (array_key_exists('is_active', $data)) {
            $attributes['administrator_group_is_active'] = $data['is_active'];
        }

        $role->update($attributes);

        return $this->role($role->refresh());
    }

    public function deleteRole(SiteAdministratorGroup $role): void
    {
        if ($role->administrators()->exists()) {
            throw new ProcessException('Grup akses masih digunakan oleh administrator dan tidak dapat dihapus.');
        }

        DB::transaction(function () use ($role): void {
            $role->menus()->detach();
            $role->delete();
        });
    }

    /** @return Collection<int, SiteAdministratorPrivilege> */
    public function privileges(SiteAdministratorGroup $role): Collection
    {
        return SiteAdministratorPrivilege::query()
            ->with('menu')
            ->where('administrator_privilege_administrator_group_id', $role->getKey())
            ->get()
            ->sortBy(fn (SiteAdministratorPrivilege $privilege): int => (int) $privilege->menu?->administrator_menu_order_by)
            ->values();
    }

    /**
     * @param  array<int, array{menu_id: int}>  $menus
     * @return Collection<int, SiteAdministratorPrivilege>
     */
    public function syncPrivileges(SiteAdministratorGroup $role, array $menus): Collection
    {
        $menuIds = collect($menus)->pluck('menu_id')->all();

        $role->menus()->sync($menuIds);

        return $this->privileges($role);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function menus(array $params): array
    {
        return DataTable::select([
            'site_administrator_menu.administrator_menu_id as id',
            'site_administrator_menu.administrator_menu_par_id as parent_id',
            'site_administrator_menu.administrator_menu_title as title',
            'site_administrator_menu.administrator_menu_description as description',
            'site_administrator_menu.administrator_menu_link as link',
            'site_administrator_menu.administrator_menu_icon as icon',
            'site_administrator_menu.administrator_menu_class as class',
            'site_administrator_menu.administrator_menu_order_by as order',
            'site_administrator_menu.administrator_menu_is_active as is_active',
        ])
            ->from('site_administrator_menu')
            ->search(['title', 'description', 'link'])
            ->defaultSort('order,id')
            ->get($params);
    }

    /** @return Collection<int, SiteAdministratorMenu> */
    public function menuTree(): Collection
    {
        $menus = SiteAdministratorMenu::query()
            ->orderBy('administrator_menu_order_by')
            ->orderBy('administrator_menu_id')
            ->get();

        return $this->buildMenuTree($menus);
    }

    public function menu(SiteAdministratorMenu $menu): SiteAdministratorMenu
    {
        return $menu->load(['parent', 'children']);
    }

    /** @param array<string, mixed> $data */
    public function createMenu(array $data): SiteAdministratorMenu
    {
        $menu = SiteAdministratorMenu::query()->create($this->menuAttributes($data) + [
            'administrator_menu_order_by' => 0,
            'administrator_menu_is_active' => 1,
        ]);

        return $this->menu($menu);
    }

    /** @param array<string, mixed> $data */
    public function updateMenu(SiteAdministratorMenu $menu, array $data): SiteAdministratorMenu
    {
        $parentId = (int) ($data['parent_id'] ?? 0);
        $this->ensureValidParent($menu, $parentId);
        $menu->update($this->menuAttributes($data));

        return $this->menu($menu->refresh());
    }

    public function deleteMenu(SiteAdministratorMenu $menu): void
    {
        if ($menu->children()->exists()) {
            throw new ProcessException('Menu masih memiliki menu turunan dan tidak dapat dihapus.');
        }

        DB::transaction(function () use ($menu): void {
            $menu->groups()->detach();
            $menu->delete();
        });
    }

    /** @param array<string, mixed> $data */
    private function menuAttributes(array $data): array
    {
        $attributes = [
            'administrator_menu_par_id' => $data['parent_id'] ?? 0,
            'administrator_menu_title' => $data['title'],
            'administrator_menu_description' => $data['description'] ?? '',
            'administrator_menu_link' => $data['link'],
            'administrator_menu_icon' => $data['icon'] ?? '',
            'administrator_menu_class' => $data['class'] ?? '',
        ];

        foreach (['order' => 'administrator_menu_order_by', 'is_active' => 'administrator_menu_is_active'] as $input => $column) {
            if (array_key_exists($input, $data)) {
                $attributes[$column] = $data[$input];
            }
        }

        return $attributes;
    }

    private function ensureValidParent(SiteAdministratorMenu $menu, int $parentId): void
    {
        while ($parentId > 0) {
            if ($parentId === (int) $menu->getKey()) {
                throw new ProcessException('Menu induk tidak boleh berasal dari menu itu sendiri atau turunannya.');
            }

            $parentId = (int) SiteAdministratorMenu::query()
                ->whereKey($parentId)
                ->value('administrator_menu_par_id');
        }
    }

    /**
     * @param  Collection<int, SiteAdministratorMenu>  $menus
     * @return Collection<int, SiteAdministratorMenu>
     */
    private function buildMenuTree(Collection $menus, int $parentId = 0): Collection
    {
        return $menus
            ->where('administrator_menu_par_id', $parentId)
            ->map(function (SiteAdministratorMenu $menu) use ($menus): SiteAdministratorMenu {
                $menu->setRelation('children', $this->buildMenuTree($menus, $menu->administrator_menu_id));

                return $menu;
            })
            ->values();
    }
}
