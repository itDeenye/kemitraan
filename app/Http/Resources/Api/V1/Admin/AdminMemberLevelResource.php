<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\MemberLevel;
use Illuminate\Http\Request;

class AdminMemberLevelResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->value('id', 'member_level_id'),
            'code' => $this->value('code', 'member_level_code'),
            'name' => $this->value('name', 'member_level_name'),
            'description' => $this->value('description', 'member_level_description'),
            'min_order' => (int) $this->value('min_order', 'member_level_min_order'),
            'point_value' => (int) $this->value('point_value', 'member_level_point_value'),
            'sort_order' => (int) $this->value('sort_order', 'member_level_sort_order'),
            'member_count' => (int) $this->value('member_count', 'members_count'),
            'is_active' => (bool) $this->value('is_active', 'member_level_is_active'),
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof MemberLevel
            ? $this->resource->getAttribute($modelField)
            : $this->resource->{$listField};
    }
}
