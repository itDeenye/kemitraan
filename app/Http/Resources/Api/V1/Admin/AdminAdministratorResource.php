<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\SiteAdministrator;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class AdminAdministratorResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $administrator = $this->resource instanceof SiteAdministrator ? $this->resource : null;

        return [
            'id' => $this->value('id', 'administrator_id'),
            'username' => $this->value('username', 'administrator_username'),
            'name' => $this->value('name', 'administrator_name'),
            'email' => $this->value('email', 'administrator_email'),
            'mobile_phone' => $this->value('mobile_phone', 'administrator_mobilephone'),
            'image_url' => MediaUrl::publicUrl(
                $this->value('image_url', 'administrator_image'),
            ),
            'role' => [
                'id' => $administrator?->group?->administrator_group_id ?? $this->resource->role_id,
                'title' => $administrator?->group?->administrator_group_title ?? $this->resource->role_title,
                'type' => $administrator?->group?->administrator_group_type ?? $this->resource->role_type,
            ],
            'is_active' => (bool) $this->value('is_active', 'administrator_is_active'),
            'last_login' => $this->value('last_login', 'administrator_last_login'),
            'locked_until' => $this->value('locked_until', 'administrator_locked_until'),
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof SiteAdministrator
            ? $this->resource->getAttribute($modelField)
            : $this->resource->{$listField};
    }
}
