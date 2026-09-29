<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\SiteAdministratorMenu;
use Illuminate\Http\Request;

class AdminMenuManagementResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $menu = $this->resource instanceof SiteAdministratorMenu ? $this->resource : null;

        return [
            'id' => $this->value('id', 'administrator_menu_id'),
            'parent_id' => (int) $this->value('parent_id', 'administrator_menu_par_id'),
            'title' => $this->value('title', 'administrator_menu_title'),
            'description' => $this->value('description', 'administrator_menu_description'),
            'link' => $this->value('link', 'administrator_menu_link'),
            'icon' => $this->value('icon', 'administrator_menu_icon'),
            'class' => $this->value('class', 'administrator_menu_class'),
            'order' => (int) $this->value('order', 'administrator_menu_order_by'),
            'is_active' => (bool) $this->value('is_active', 'administrator_menu_is_active'),
            'children' => $this->when(
                $menu?->relationLoaded('children') === true,
                fn () => self::collection($menu->children)
            ),
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof SiteAdministratorMenu
            ? $this->resource->getAttribute($modelField)
            : $this->resource->{$listField};
    }
}
