<?php

namespace App\Services\System;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\AuditTrail;
use App\Models\Config;
use App\Models\Member;
use App\Models\MemberLevel;
use App\Support\BusinessConfig;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class AdminSystemService
{
    /** @var list<string> */
    private const INTERNAL_SEQUENCE_KEYS = [
        'partnership.member_code_sequence',
        'transaction.code_sequence',
        'document_code.rtr_sequence',
        'document_code.grn_sequence',
        'document_code.adj_sequence',
    ];

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function configs(array $params): array
    {
        $configTable = (new Config)->getTable();

        return DataTable::select([
            "{$configTable}.config_id as id",
            "{$configTable}.config_key as key",
            "{$configTable}.config_value as value",
            "{$configTable}.config_type as type",
            "{$configTable}.config_scope as scope",
            "{$configTable}.config_created_datetime as created_at",
            "{$configTable}.config_updated_datetime as updated_at",
        ])
            ->from($configTable)
            ->where("{$configTable}.config_scope", Config::SCOPE_SYSTEM)
            ->whereNotIn("{$configTable}.config_key", self::INTERNAL_SEQUENCE_KEYS)
            ->search(['key', 'value'])
            ->defaultSort('key')
            ->get($params);
    }

    public function config(Config $config): Config
    {
        $this->ensurePublicConfig($config);

        return $config;
    }

    /** @param array<string, mixed> $data */
    public function updateConfig(Config $config, array $data): Config
    {
        $this->ensurePublicConfig($config);
        $type = $data['type'] ?? $config->config_type;
        if (BusinessConfig::isBusinessGroup($config->config_key) && $type !== 'json') {
            throw new ProcessException('Konfigurasi bisnis wajib menggunakan format data pengaturan.');
        }
        $config->update([
            'config_value' => $this->serializeConfigValue($data['value'], $type),
            'config_type' => $type,
            'config_updated_datetime' => now(),
        ]);

        return $config->refresh();
    }

    private function ensurePublicConfig(Config $config): void
    {
        if (
            $config->config_scope !== Config::SCOPE_SYSTEM
            || in_array($config->config_key, self::INTERNAL_SEQUENCE_KEYS, true)
        ) {
            throw (new ModelNotFoundException)->setModel(Config::class, [$config->getKey()]);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function memberLevels(array $params): array
    {
        $levelTable = (new MemberLevel)->getTable();
        $memberTable = (new Member)->getTable();

        return DataTable::select([
            "{$levelTable}.member_level_id as id",
            "{$levelTable}.member_level_code as code",
            "{$levelTable}.member_level_name as name",
            "{$levelTable}.member_level_description as description",
            "{$levelTable}.member_level_min_order as min_order",
            "{$levelTable}.member_level_point_value as point_value",
            "{$levelTable}.member_level_sort_order as sort_order",
            "{$levelTable}.member_level_is_active as is_active",
        ])
            ->selectRaw(
                "(SELECT COUNT(*) FROM {$memberTable} WHERE {$memberTable}.member_member_level_id = {$levelTable}.member_level_id) as member_count"
            )
            ->from($levelTable)
            ->search(['code', 'name', 'description'])
            ->defaultSort('sort_order,id')
            ->get($params);
    }

    public function memberLevel(MemberLevel $memberLevel): MemberLevel
    {
        return $memberLevel->loadCount('members');
    }

    /** @param array<string, mixed> $data */
    public function updateMemberLevel(MemberLevel $memberLevel, array $data): MemberLevel
    {
        $memberLevel->update([
            'member_level_name' => $data['name'],
            'member_level_description' => $data['description'] ?? null,
            'member_level_min_order' => $data['min_order'],
            'member_level_point_value' => $data['point_value'],
            'member_level_sort_order' => $data['sort_order'],
            'member_level_is_active' => $data['is_active'],
        ]);

        return $this->memberLevel($memberLevel->refresh());
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function commissionConfigs(array $params): array
    {
        $configTable = (new Config)->getTable();

        return DataTable::select([
            "{$configTable}.config_id as id",
            "{$configTable}.config_key as key",
            "{$configTable}.config_value as value",
            "{$configTable}.config_type as type",
            "{$configTable}.config_scope as scope",
            "{$configTable}.config_created_datetime as created_at",
            "{$configTable}.config_updated_datetime as updated_at",
        ])
            ->from($configTable)
            ->where("{$configTable}.config_scope", Config::SCOPE_COMMISSION)
            ->search(['key', 'value'])
            ->defaultSort('id')
            ->get($params);
    }

    /** @param array{value: array<string, mixed>} $data */
    public function updateCommissionConfig(Config $config, array $data): Config
    {
        $this->ensureCommissionConfig($config);
        $config->update([
            'config_value' => $this->serializeConfigValue($data['value'], 'json'),
            'config_type' => 'json',
            'config_updated_datetime' => now(),
        ]);

        return $config->refresh();
    }

    private function ensureCommissionConfig(Config $config): void
    {
        if ($config->config_scope !== Config::SCOPE_COMMISSION) {
            throw (new ModelNotFoundException)->setModel(Config::class, [$config->getKey()]);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function auditTrails(array $params): array
    {
        $auditTable = (new AuditTrail)->getTable();

        return DataTable::select([
            "{$auditTable}.audittrail_id as id",
            "{$auditTable}.audittrail_admin_id as admin_id",
            "{$auditTable}.audittrail_admin_name as admin_name",
            "{$auditTable}.audittrail_menu_name as menu_name",
            "{$auditTable}.audittrail_desc as description",
            "{$auditTable}.audittrail_act as action",
            "{$auditTable}.audittrail_ip_address as ip_address",
            "{$auditTable}.audittrail_user_agent as user_agent",
            "{$auditTable}.audittrail_datetime as happened_at",
        ])
            ->from($auditTable)
            ->search(['admin_name', 'menu_name', 'description', 'action', 'ip_address'])
            ->defaultSort('-id')
            ->allowUnpaginated()
            ->get($params);
    }

    public function auditTrail(AuditTrail $auditTrail): AuditTrail
    {
        return $auditTrail;
    }

    private function serializeConfigValue(mixed $value, string $type): ?string
    {
        return match ($type) {
            'integer' => (string) ((int) $value),
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'json' => is_string($value)
                ? $value
                : json_encode($value, JSON_THROW_ON_ERROR),
            default => is_null($value) ? null : (string) $value,
        };
    }
}
