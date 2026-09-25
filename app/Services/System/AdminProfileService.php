<?php

namespace App\Services\System;

use App\Models\SiteAdministrator;
use Illuminate\Support\Facades\Hash;
use Laravel\Telescope\Telescope;

class AdminProfileService
{
    public function profile(SiteAdministrator $administrator): SiteAdministrator
    {
        return $administrator->load('group');
    }

    /** @param array<string, mixed> $data */
    public function updateProfile(SiteAdministrator $administrator, array $data): SiteAdministrator
    {
        $attributes = [
            'administrator_name' => $data['name'],
            'administrator_email' => $data['email'],
        ];

        if (array_key_exists('mobile_phone', $data)) {
            $attributes['administrator_mobilephone'] = $data['mobile_phone'] ?? '';
        }

        if (array_key_exists('image_url', $data)) {
            $attributes['administrator_image'] = $data['image_url'] ?? '';
        }

        $administrator->update($attributes);

        return $this->profile($administrator->refresh());
    }

    public function updatePassword(SiteAdministrator $administrator, string $password): void
    {
        $administrator->update([
            'administrator_password' => Hash::make($password),
        ]);
    }
}
