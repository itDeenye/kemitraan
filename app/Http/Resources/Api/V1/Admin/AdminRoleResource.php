<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\SiteAdministratorGroup;
use Illuminate\Http\Request;

class AdminRoleResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->value('id', 'administrator_group_id'),
            'title' => $this->value('title', 'administrator_group_title'),
            'type' => $this->value('type', 'administrator_group_type'),
            'administrator_count' => (int) $this->value('administrator_count', 'administrators_count'),
            'is_active' => (bool) $this->value('is_active', 'administrator_group_is_active'),
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof SiteAdministratorGroup
            ? $this->resource->getAttribute($modelField)
            : $this->resource->{$listField};
    }
}
