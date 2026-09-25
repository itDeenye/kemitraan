<?php

namespace App\Services\System;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminAdministratorService
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function administrators(array $params): array
    {
        $administratorTable = (new SiteAdministrator)->getTable();
        $groupTable = (new SiteAdministratorGroup)->getTable();

        return DataTable::select([
            "{$administratorTable}.administrator_id as id",
            "{$administratorTable}.administrator_username as username",
            "{$administratorTable}.administrator_name as name",
            "{$administratorTable}.administrator_email as email",
            "{$administratorTable}.administrator_mobilephone as mobile_phone",
            "{$administratorTable}.administrator_image as image_url",
            "{$administratorTable}.administrator_administrator_group_id as role_id",
            "{$groupTable}.administrator_group_title as role_title",
            "{$groupTable}.administrator_group_type as role_type",
            "{$administratorTable}.administrator_is_active as is_active",
            "{$administratorTable}.administrator_last_login as last_login",
            "{$administratorTable}.administrator_locked_until as locked_until",
        ])
            ->from($administratorTable)
            ->leftJoin(
                $groupTable,
                "{$groupTable}.administrator_group_id = {$administratorTable}.administrator_administrator_group_id"
            )
            ->search(['username', 'name', 'email', 'role_title'])
            ->defaultSort('-id')
            ->get($params);
    }

    public function administrator(SiteAdministrator $administrator): SiteAdministrator
    {
        return $administrator->load('group');
    }

    /** @param array<string, mixed> $data */
    public function createAdministrator(array $data): SiteAdministrator
    {
        $administrator = SiteAdministrator::query()->create([
            ...$this->administratorAttributes($data),
            'administrator_password' => Hash::make($data['password']),
            'administrator_is_active' => $data['is_active'] ?? true,
        ]);

        return $this->administrator($administrator);
    }

    /** @param array<string, mixed> $data */
    public function updateAdministrator(
        SiteAdministrator $administrator,
        array $data,
        int $currentAdministratorId
    ): SiteAdministrator {
        if ((int) $administrator->getKey() === $currentAdministratorId
            && array_key_exists('is_active', $data)
            && ! $data['is_active']) {
            throw new ProcessException('Administrator yang sedang digunakan tidak dapat dinonaktifkan.');
        }

        $administrator->update($this->administratorAttributes($data));

        return $this->administrator($administrator->refresh());
    }

    public function updatePassword(SiteAdministrator $administrator, string $password): SiteAdministrator
    {
        DB::transaction(function () use ($administrator, $password): void {
            $administrator->update([
                'administrator_password' => Hash::make($password),
            ]);
        });

        return $this->administrator($administrator->refresh());
    }

    public function deleteAdministrator(
        SiteAdministrator $administrator,
        int $currentAdministratorId
    ): void {
        if ((int) $administrator->getKey() === 1) {
            throw new ProcessException('Administrator utama tidak dapat dihapus.');
        }

        if ((int) $administrator->getKey() === $currentAdministratorId) {
            throw new ProcessException('Administrator yang sedang digunakan tidak dapat dihapus.');
        }

        DB::transaction(function () use ($administrator): void {
            $administrator->delete();
        });
    }

    /** @param array<string, mixed> $data */
    private function administratorAttributes(array $data): array
    {
        $attributes = [
            'administrator_administrator_group_id' => $data['role_id'],
            'administrator_username' => $data['username'],
            'administrator_name' => $data['name'],
            'administrator_email' => $data['email'],
            'administrator_image' => $data['image_url'] ?? '',
        ];

        if (array_key_exists('mobile_phone', $data)) {
            $attributes['administrator_mobilephone'] = $data['mobile_phone'] ?? '';
        }

        if (array_key_exists('is_active', $data)) {
            $attributes['administrator_is_active'] = $data['is_active'];
        }

        return $attributes;
    }
}
