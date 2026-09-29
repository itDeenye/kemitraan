<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'reward_monthly' => [
                'reward_monthly_min_qty_distributor' => 50,
                'reward_monthly_min_qty_agent' => 30,
                'reward_monthly_min_qty_reseller' => 20,
            ],
            'reward_stockist' => [
                'reward_stockist_min_amount' => 50_000_000,
                'reward_stockist_percentage_basis_points' => 250,
            ],
            'upgrade' => [
                'upgrade_reseller_min_qty_per_month' => 50,
                'upgrade_reseller_min_total_qty' => 150,
                'upgrade_reseller_min_customer_count' => 3,
                'upgrade_agent_min_qty_per_month' => 270,
                'upgrade_agent_min_total_qty' => 800,
                'upgrade_agent_min_customer_count' => 10,
                'upgrade_agent_min_reseller_count' => 3,
            ],
            'return' => ['return_max_days' => 3],
            'partnership' => [
                'reward_point_per_item' => 1,
                'spread_payment_percentage' => 1,
            ],
            'development' => ['frontend_enabled' => true],
        ];
        $groups = [
            'reward_monthly' => [
                'partnership.reward_monthly_min_qty_distributor',
                'partnership.reward_monthly_min_qty_agent',
                'partnership.reward_monthly_min_qty_reseller',
            ],
            'reward_stockist' => [
                'partnership.reward_stockist_min_amount',
                'partnership.reward_stockist_percentage_basis_points',
            ],
            'upgrade' => [
                'partnership.upgrade_reseller_min_qty_per_month',
                'partnership.upgrade_reseller_min_total_qty',
                'partnership.upgrade_reseller_min_customer_count',
                'partnership.upgrade_agent_min_qty_per_month',
                'partnership.upgrade_agent_min_total_qty',
                'partnership.upgrade_agent_min_customer_count',
                'partnership.upgrade_agent_min_reseller_count',
            ],
            'return' => ['partnership.return_max_days'],
            'partnership' => [
                'partnership.reward_point_per_item',
                'partnership.spread_payment_percentage',
            ],
            'development' => [
                'development.frontend_enabled',
                'development.seeded_at',
            ],
        ];

        foreach ($groups as $group => $keys) {
            $existingValue = DB::table('config')
                ->where('config_key', $group)
                ->where('config_type', 'json')
                ->value('config_value');
            $existingValues = is_string($existingValue)
                ? json_decode($existingValue, true)
                : [];
            $values = array_merge(
                $defaults[$group],
                is_array($existingValues) ? $existingValues : [],
            );

            foreach ($keys as $key) {
                $row = DB::table('config')->where('config_key', $key)->first();
                if (! $row) {
                    continue;
                }

                $shortKey = str_contains($key, '.')
                    ? substr($key, strrpos($key, '.') + 1)
                    : $key;
                $values[$shortKey] = match ($row->config_type) {
                    'integer' => (int) $row->config_value,
                    'boolean' => $row->config_value === '1',
                    default => $row->config_value,
                };
            }

            $now = now();
            DB::table('config')->updateOrInsert(
                ['config_key' => $group],
                [
                    'config_value' => json_encode($values, JSON_THROW_ON_ERROR),
                    'config_type' => 'json',
                    'config_created_datetime' => $now,
                    'config_updated_datetime' => $now,
                ],
            );
        }

        DB::table('config')->where(function ($query): void {
            $query->where('config_key', 'like', 'partnership.%')
                ->orWhere('config_key', 'like', 'development.%');
        })->whereNotIn('config_key', array_keys($groups))->delete();
    }

    public function down(): void
    {
        // Grouped JSON configuration is the canonical schema.
    }
};
