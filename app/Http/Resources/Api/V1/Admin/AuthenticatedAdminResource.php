<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class AuthenticatedAdminResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->administrator_id,
            'username' => $this->administrator_username,
            'name' => $this->administrator_name,
            'email' => $this->administrator_email,
            'mobile_phone' => $this->administrator_mobilephone,
            'image' => MediaUrl::publicUrl($this->administrator_image),
            'last_login_at' => $this->administrator_last_login?->toAtomString(),
            'role' => [
                'id' => $this->group?->administrator_group_id,
                'name' => $this->group?->administrator_group_title,
                'type' => $this->group?->administrator_group_type,
            ],
        ];
    }
}
