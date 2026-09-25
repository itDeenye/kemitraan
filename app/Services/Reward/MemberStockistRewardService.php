<?php

namespace App\Services\Reward;

use App\Libraries\DataTable;
use App\Models\RewardStockist;
use App\Models\Trx;

class MemberStockistRewardService
{
    /** @param array<string, mixed> $params */
    public function stockistRewards(int $memberId, array $params): array
    {
        $rewardTable = (new RewardStockist)->getTable();
        $trxTable = (new Trx)->getTable();

        $dt = DataTable::select([
            "{$rewardTable}.reward_stockist_id as id",
            "{$rewardTable}.reward_stockist_year as year",
            "{$rewardTable}.reward_stockist_month as month",
            "{$rewardTable}.reward_stockist_total_trx_amount as total_spending",
            "{$rewardTable}.reward_stockist_bonus_value as voucher_value",
            "{$rewardTable}.reward_stockist_used_value as used_value",
            "{$rewardTable}.reward_stockist_used_trx_id as used_trx_id",
            "{$trxTable}.trx_code as used_trx_code",
            "{$rewardTable}.reward_stockist_expiry_date as expiry_date",
            "{$rewardTable}.reward_stockist_created_datetime as created_at",
        ])
            ->from($rewardTable)
            ->leftJoin($trxTable, "{$trxTable}.trx_id = {$rewardTable}.reward_stockist_used_trx_id")
            ->where("{$rewardTable}.reward_stockist_member_id", $memberId);

        if (isset($params['year'])) {
            $dt->where("{$rewardTable}.reward_stockist_year", $params['year']);
        }

        return $dt->search(['used_trx_code'])
            ->defaultSort('-year,-month,-id')
            ->get($params);
    }

    /** @param array<string, mixed> $params */
    public function summary(int $memberId, array $params): array
    {
        $query = RewardStockist::query()
            ->where('reward_stockist_member_id', $memberId)
            ->when(isset($params['year']), function ($q) use ($params) {
                $q->where('reward_stockist_year', $params['year']);
            });

        $totals = clone $query;
        $totalSpending = (int) $totals->sum('reward_stockist_total_trx_amount');
        $totalVoucher = (int) $totals->sum('reward_stockist_bonus_value');

        $percentage = $totalSpending > 0 ? round(($totalVoucher / $totalSpending) * 100, 2) : 0;

        return [
            'total_spending' => $totalSpending,
            'total_voucher' => $totalVoucher,
            'percentage' => (float) $percentage,
        ];
    }

    public function stockistReward(RewardStockist $reward): RewardStockist
    {
        return $reward->load('usedTransaction');
    }

    /** @return list<int> */
    public function availableYears(int $memberId): array
    {
        return RewardStockist::query()
            ->where('reward_stockist_member_id', $memberId)
            ->distinct()
            ->orderByDesc('reward_stockist_year')
            ->pluck('reward_stockist_year')
            ->map(fn (mixed $year): int => (int) $year)
            ->values()
            ->all();
    }
}
