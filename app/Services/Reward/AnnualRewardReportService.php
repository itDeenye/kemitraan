<?php

namespace App\Services\Reward;

use App\Libraries\DataTable;
use App\Models\Member;
use App\Models\MemberLevel;
use App\Models\RewardPointAnnual;
use App\Models\RewardPointAnnualLog;
use Illuminate\Support\Facades\DB;

class AnnualRewardReportService
{
    /** @param array<string, mixed> $params */
    public function reports(array $params): array
    {
        $rewardTable = (new RewardPointAnnual)->getTable();
        $memberTable = (new Member)->getTable();
        $levelTable = (new MemberLevel)->getTable();

        return DataTable::select([
            "{$rewardTable}.reward_point_annual_id as id",
            "{$rewardTable}.reward_point_annual_member_id as member_id",
            "{$memberTable}.member_code as member_code",
            "{$memberTable}.member_name as member_name",
            "{$memberTable}.member_member_level_id as member_level_id",
            "{$levelTable}.member_level_code as member_level_code",
            "{$levelTable}.member_level_name as member_level_name",
            "{$rewardTable}.reward_point_annual_year as year",
            "{$rewardTable}.reward_point_annual_total_points as total_points",
            "{$rewardTable}.reward_point_annual_last_updated_datetime as updated_at",
        ])
            ->from($rewardTable)
            ->leftJoin($memberTable, "{$memberTable}.member_id = {$rewardTable}.reward_point_annual_member_id")
            ->leftJoin($levelTable, "{$levelTable}.member_level_id = {$memberTable}.member_member_level_id")
            ->search(['member_code', 'member_name', 'member_level_code', 'member_level_name'])
            ->defaultSort('-year,-total_points,-id')
            ->get($params);
    }

    /** @return array<string, mixed> */
    public function report(RewardPointAnnual $annualReward): array
    {
        $annualReward->load('member.level');
        $member = $annualReward->member;
        $year = (int) $annualReward->reward_point_annual_year;

        return [
            'id' => (int) $annualReward->getKey(),
            'member_id' => (int) $annualReward->reward_point_annual_member_id,
            'member_code' => $member?->member_code,
            'member_name' => $member?->member_name,
            'member_level_id' => (int) ($member?->member_member_level_id ?? 0),
            'member_level_code' => $member?->level?->member_level_code,
            'member_level_name' => $member?->level?->member_level_name,
            'year' => $year,
            'total_points' => (int) $annualReward->reward_point_annual_total_points,
            'updated_at' => $annualReward->reward_point_annual_last_updated_datetime?->toAtomString(),
            'months' => $this->monthlyPoints((int) $annualReward->reward_point_annual_member_id, $year),
        ];
    }

    /** @return array<string, mixed> */
    public function memberReport(int $memberId, int $year): array
    {
        $months = $this->monthlyPoints($memberId, $year);
        $storedTotal = RewardPointAnnual::query()
            ->where('reward_point_annual_member_id', $memberId)
            ->where('reward_point_annual_year', $year)
            ->value('reward_point_annual_total_points');

        return [
            'year' => $year,
            'total_points' => $storedTotal === null
                ? array_sum(array_column($months, 'total_points'))
                : (int) $storedTotal,
            'months' => $months,
        ];
    }

    /** @return list<int> */
    public function memberAvailableYears(int $memberId): array
    {
        $annualYears = RewardPointAnnual::query()
            ->where('reward_point_annual_member_id', $memberId)
            ->distinct()
            ->pluck('reward_point_annual_year')
            ->map(fn (mixed $year): int => (int) $year);

        $logYears = collect($this->memberLogYears($memberId));

        return $annualYears
            ->merge($logYears)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /** @return list<array{year: int, month: int, total_points: int}> */
    private function monthlyPoints(int $memberId, int $year): array
    {
        $logTable = (new RewardPointAnnualLog)->getTable();
        $datetimeColumn = "{$logTable}.reward_point_annual_log_datetime";
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', {$datetimeColumn}) AS INTEGER)"
            : "MONTH({$datetimeColumn})";

        return RewardPointAnnualLog::query()
            ->selectRaw("{$monthExpression} as month")
            ->selectRaw(
                "SUM(CASE WHEN {$logTable}.reward_point_annual_log_type = 'in' "
                ."THEN {$logTable}.reward_point_annual_log_points "
                ."ELSE -{$logTable}.reward_point_annual_log_points END) as total_points"
            )
            ->where('reward_point_annual_log_member_id', $memberId)
            ->whereBetween('reward_point_annual_log_datetime', [
                "{$year}-01-01 00:00:00",
                "{$year}-12-31 23:59:59",
            ])
            ->groupByRaw($monthExpression)
            ->orderByDesc('month')
            ->get()
            ->map(fn (RewardPointAnnualLog $row): array => [
                'year' => $year,
                'month' => (int) $row->getAttribute('month'),
                'total_points' => (int) $row->getAttribute('total_points'),
            ])
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function memberLogYears(int $memberId): array
    {
        $logTable = (new RewardPointAnnualLog)->getTable();
        $datetimeColumn = "{$logTable}.reward_point_annual_log_datetime";
        $yearExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', {$datetimeColumn}) AS INTEGER)"
            : "YEAR({$datetimeColumn})";

        return RewardPointAnnualLog::query()
            ->selectRaw("{$yearExpression} as year")
            ->where('reward_point_annual_log_member_id', $memberId)
            ->groupByRaw($yearExpression)
            ->orderByDesc('year')
            ->get()
            ->map(fn (RewardPointAnnualLog $row): int => (int) $row->getAttribute('year'))
            ->values()
            ->all();
    }
}
