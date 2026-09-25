<?php

namespace App\Support;

use App\Models\Config;
use Illuminate\Support\Arr;

final class BusinessConfig
{
    /** @var list<string> */
    public const GROUPS = [
        'reward_monthly',
        'reward_stockist',
        'upgrade',
        'return',
        'partnership',
        'development',
    ];

    /** @var list<string> */
    public const COMMISSION_GROUPS = [
        'reward_monthly',
        'reward_stockist',
        'partnership',
    ];

    public static function get(string $path, mixed $default = null): mixed
    {
        [$group, $key] = array_pad(explode('.', $path, 2), 2, null);
        if ($key === null) {
            return $default;
        }

        $value = Config::query()
            ->where('config_key', $group)
            ->where('config_type', 'json')
            ->value('config_value');

        if ($value !== null) {
            $decoded = json_decode((string) $value, true);
            $nested = Arr::get(is_array($decoded) ? $decoded : [], $key);
            if ($nested !== null) {
                return $nested;
            }
        }

        // Backward-compatible fallback while older environments are migrated.
        return Config::query()->where('config_key', $path)->value('config_value') ?? $default;
    }

    public static function put(string $group, array $values, ?\DateTimeInterface $now = null): Config
    {
        $timestamp = $now ?? now();

        return Config::query()->updateOrCreate(
            ['config_key' => $group],
            [
                'config_value' => json_encode($values, JSON_THROW_ON_ERROR),
                'config_type' => 'json',
                'config_scope' => self::scopeForGroup($group),
                'config_created_datetime' => $timestamp,
                'config_updated_datetime' => $timestamp,
            ],
        );
    }

    public static function isBusinessGroup(string $key): bool
    {
        return in_array($key, self::GROUPS, true);
    }

    public static function scopeForGroup(string $group): string
    {
        return in_array($group, self::COMMISSION_GROUPS, true)
            ? Config::SCOPE_COMMISSION
            : Config::SCOPE_SYSTEM;
    }
}
