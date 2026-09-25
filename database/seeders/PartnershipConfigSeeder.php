<?php

namespace Database\Seeders;

use App\Support\BusinessConfig;
use Illuminate\Database\Seeder;

class PartnershipConfigSeeder extends Seeder
{
    public function run(): void
    {
        BusinessConfig::put('reward_monthly', [
            'reward_monthly_min_qty_distributor' => 50,
            'reward_monthly_min_qty_agent' => 30,
            'reward_monthly_min_qty_reseller' => 20,
        ]);
        BusinessConfig::put('reward_stockist', [
            'reward_stockist_min_amount' => 50_000_000,
            'reward_stockist_percentage_basis_points' => 250,
        ]);
        BusinessConfig::put('upgrade', [
            'upgrade_reseller_min_qty_per_month' => 50,
            'upgrade_reseller_min_total_qty' => 150,
            'upgrade_reseller_min_customer_count' => 3,
            'upgrade_agent_min_qty_per_month' => 270,
            'upgrade_agent_min_total_qty' => 800,
            'upgrade_agent_min_customer_count' => 10,
            'upgrade_agent_min_reseller_count' => 3,
        ]);
        BusinessConfig::put('return', ['return_max_days' => 3]);
        BusinessConfig::put('partnership', [
            'reward_point_per_item' => 1,
            'spread_payment_percentage' => 1,
        ]);
        $development = ['frontend_enabled' => true];
        $seededAt = BusinessConfig::get('development.seeded_at');
        if ($seededAt !== null) {
            $development['seeded_at'] = $seededAt;
        }

        BusinessConfig::put('development', $development);
    }
}
