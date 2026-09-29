<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class AdminRolePrivilegeResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'menu_id' => $this->administrator_privilege_administrator_menu_id,
        ];
    }
}
