<?php

namespace App\Services\Menu;

use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorMenu;
use Illuminate\Support\Collection;

class AdminMenuService
{
    /** @return array<int, array<string, mixed>> */
    public function for(SiteAdministrator $administrator): array
    {
        $administrator->loadMissing('group');
        $group = $administrator->group;

        if (! $group) {
            return [];
        }

        $menus = SiteAdministratorMenu::query()
            ->where('administrator_menu_is_active', 1)
            ->orderBy('administrator_menu_order_by')
            ->orderBy('administrator_menu_id')
            ->get();

        if ($group->administrator_group_type === 'superuser') {
            $allowedIds = $menus->modelKeys();
        } else {
            $allowedMenus = $group->menus()->where('administrator_menu_is_active', 1)->get();
            $allowedIds = $allowedMenus->modelKeys();
        }

        return $this->buildTree($menus, $this->includeAncestors($menus, $allowedIds));
    }

    /**
     * @param  Collection<int, SiteAdministratorMenu>  $menus
     * @param  array<int, int>  $allowedIds
     * @return array<int, int>
     */
    private function includeAncestors(Collection $menus, array $allowedIds): array
    {
        $byId = $menus->keyBy('administrator_menu_id');
        $included = array_fill_keys($allowedIds, true);

        foreach ($allowedIds as $id) {
            $parentId = (int) ($byId->get($id)?->administrator_menu_par_id ?? 0);
            while ($parentId > 0 && $byId->has($parentId)) {
                $included[$parentId] = true;
                $parentId = (int) $byId->get($parentId)->administrator_menu_par_id;
            }
        }

        return array_map('intval', array_keys($included));
    }

    /**
     * @param  Collection<int, SiteAdministratorMenu>  $menus
     * @param  array<int, int>  $allowedIds
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $menus, array $allowedIds, int $parentId = 0): array
    {
        return $menus->whereIn('administrator_menu_id', $allowedIds)
            ->where('administrator_menu_par_id', $parentId)
            ->map(fn (SiteAdministratorMenu $menu): array => [
                'id' => $menu->administrator_menu_id,
                'title' => $menu->administrator_menu_title,
                'description' => $menu->administrator_menu_description,
                'route' => $menu->administrator_menu_link,
                'icon' => $menu->administrator_menu_icon,
                'css_class' => $menu->administrator_menu_class,
                'sort_order' => $menu->administrator_menu_order_by,
                'children' => $this->buildTree($menus, $allowedIds, $menu->administrator_menu_id),
            ])->values()->all();
    }
}
