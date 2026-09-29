<?php

namespace App\Services\Reward;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\Member;
use App\Models\MemberAchievement;
use App\Models\MemberLevel;
use App\Models\RewardPointMonthly;
use App\Models\RewardStockist;
use App\Models\SiteAdministrator;
use App\Models\Trx;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class AdminMonthlyRewardService
{
    /** @param array<string, mixed> $params */
    public function monthlyRewards(array $params): array
    {
        $rewardTable = (new RewardPointMonthly)->getTable();
        $memberTable = (new Member)->getTable();
        $levelTable = (new MemberLevel)->getTable();

        return DataTable::select([
            "{$rewardTable}.reward_point_monthly_id as id",
            "{$rewardTable}.reward_point_monthly_member_id as member_id",
            "{$memberTable}.member_code as member_code",
            "{$memberTable}.member_name as member_name",
            "{$rewardTable}.reward_point_monthly_member_level_id as member_level_id",
            "{$levelTable}.member_level_code as member_level_code",
            "{$levelTable}.member_level_name as member_level_name",
            "{$levelTable}.member_level_point_value as point_value",
            "{$rewardTable}.reward_point_monthly_upline_id as upline_id",
            "{$rewardTable}.reward_point_monthly_year as year",
            "{$rewardTable}.reward_point_monthly_month as month",
            "{$rewardTable}.reward_point_monthly_total_qty as total_points",
            "{$rewardTable}.reward_point_monthly_bonus_value as reward_value",
            "{$rewardTable}.reward_point_monthly_is_processed as is_processed",
            "{$rewardTable}.reward_point_monthly_admin_id as administrator_id",
            "{$rewardTable}.reward_point_monthly_processed_datetime as processed_at",
        ])
            ->from($rewardTable)
            ->leftJoin($memberTable, "{$memberTable}.member_id = {$rewardTable}.reward_point_monthly_member_id")
            ->leftJoin($levelTable, "{$levelTable}.member_level_id = {$rewardTable}.reward_point_monthly_member_level_id")
            ->where("{$rewardTable}.reward_point_monthly_upline_id", 0)
            ->search(['member_code', 'member_name', 'member_level_code', 'member_level_name'])
            ->defaultSort('-year,-month,-id')
            ->get($params);
    }

    public function monthlyReward(RewardPointMonthly $reward): RewardPointMonthly
    {
        if ((int) $reward->reward_point_monthly_upline_id !== 0) {
            throw (new ModelNotFoundException)->setModel(
                RewardPointMonthly::class,
                [$reward->getKey()],
            );
        }

        return $reward->load(['member', 'memberLevel', 'upline']);
    }

    public function processMonthlyReward(
        RewardPointMonthly $reward,
        SiteAdministrator $administrator,
    ): RewardPointMonthly {
        return DB::transaction(function () use ($reward, $administrator): RewardPointMonthly {
            $lockedReward = RewardPointMonthly::query()->whereKey($reward->getKey())->lockForUpdate()->firstOrFail();
            $lockedReward->load('memberLevel');

            if ((int) $lockedReward->reward_point_monthly_upline_id !== 0
                || $lockedReward->memberLevel?->member_level_code !== 'DST') {
                throw new ProcessException('Hanya reward bulanan Distributor yang dapat diproses oleh administrator.');
            }
            if ($lockedReward->reward_point_monthly_is_processed) {
                throw new ProcessException('Reward bulanan ini sudah pernah diproses.');
            }

            $lockedReward->update([
                'reward_point_monthly_is_processed' => 1,
                'reward_point_monthly_admin_id' => $administrator->getKey(),
                'reward_point_monthly_processed_datetime' => now(),
            ]);

            return $this->monthlyReward($lockedReward->refresh());
        });
    }

    /** @param array<string, mixed> $params */
    public function stockistRewards(array $params): array
    {
        $rewardTable = (new RewardStockist)->getTable();
        $memberTable = (new Member)->getTable();
        $trxTable = (new Trx)->getTable();

        return DataTable::select([
            "{$rewardTable}.reward_stockist_id as id",
            "{$rewardTable}.reward_stockist_member_id as member_id",
            "{$memberTable}.member_code as member_code",
            "{$memberTable}.member_name as member_name",
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
            ->leftJoin($memberTable, "{$memberTable}.member_id = {$rewardTable}.reward_stockist_member_id")
            ->leftJoin($trxTable, "{$trxTable}.trx_id = {$rewardTable}.reward_stockist_used_trx_id")
            ->search(['member_code', 'member_name', 'used_trx_code'])
            ->defaultSort('-year,-month,-id')
            ->get($params);
    }

    public function stockistReward(RewardStockist $reward): RewardStockist
    {
        return $reward->load(['member', 'usedTransaction']);
    }

    /** @param array<string, mixed> $params */
    public function pointAchievements(array $params): array
    {
        $achievementTable = (new MemberAchievement)->getTable();
        $memberTable = (new Member)->getTable();
        $levelTable = (new MemberLevel)->getTable();
        $fromPeriod = ((int) ($params['year_from'] ?? now()->year) * 100) + (int) ($params['month_from'] ?? 1);
        $toPeriod = ((int) ($params['year_to'] ?? now()->year) * 100) + (int) ($params['month_to'] ?? 12);

        return DataTable::select([
            "{$achievementTable}.member_achievement_id as id",
            "{$achievementTable}.member_achievement_member_id as member_id",
            "{$memberTable}.member_code as member_code",
            "{$memberTable}.member_name as member_name",
            "{$memberTable}.member_member_level_id as member_level_id",
            "{$levelTable}.member_level_code as member_level_code",
            "{$levelTable}.member_level_name as member_level_name",
            "{$achievementTable}.member_achievement_year as year",
            "{$achievementTable}.member_achievement_month as month",
            "{$achievementTable}.member_achievement_point as points",
            "{$achievementTable}.member_achievement_customer_count as customer_count",
            "{$achievementTable}.member_achievement_total_trx_amount as total_spending",
            "{$achievementTable}.member_achievement_create_datetime as created_at",
        ])
            ->selectRaw("({$achievementTable}.member_achievement_year * 100 + {$achievementTable}.member_achievement_month) as period", [])
            ->from($achievementTable)
            ->leftJoin($memberTable, "{$memberTable}.member_id = {$achievementTable}.member_achievement_member_id")
            ->leftJoin($levelTable, "{$levelTable}.member_level_id = {$memberTable}.member_member_level_id")
            ->where(function ($query) use ($achievementTable, $fromPeriod, $toPeriod): void {
                $query->whereRaw(
                    "({$achievementTable}.member_achievement_year * 100 + {$achievementTable}.member_achievement_month) BETWEEN ? AND ?",
                    [$fromPeriod, $toPeriod],
                );
            })
            ->search(['member_code', 'member_name', 'member_level_code', 'member_level_name'])
            ->defaultSort('-year,-month,-id')
            ->get($params);
    }
}
