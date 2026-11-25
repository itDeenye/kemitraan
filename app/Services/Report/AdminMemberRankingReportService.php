<?php

namespace App\Services\Report;

use App\Libraries\DataTable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AdminMemberRankingReportService
{
    /** @param array<string, mixed> $params */
    public function report(array $params): array
    {
        $report = $params['report'];
        $year = (int) $params['year'];
        $month = (int) ($params['month'] ?? 1);
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0);

        [$metrics, $columns, $levels, $sort] = match ($report) {
            'purchases' => [$this->purchases($start), ['purchase_amount'], ['DST', 'AGT', 'RSL'], '-purchase_amount,id'],
            'recruitment' => [$this->recruitment($start), ['recruit_count'], ['DST', 'AGT'], '-recruit_count,id'],
            'monthly-rewards' => [$this->monthlyRewards($year, $month), ['monthly_points', 'reward_amount'], ['DST'], '-reward_amount,-monthly_points,id'],
            'annual-points' => [$this->annualPoints($year), ['annual_points'], ['DST'], '-annual_points,id'],
        };
        $levelKey = $report === 'monthly-rewards' ? 'metrics.level_id' : 'member.member_member_level_id';
        $addresses = DB::table('member_address')->where('member_address_is_default', 1)
            ->groupBy('member_address_member_id')
            ->select('member_address_member_id')
            ->selectRaw('MIN(member_address_id) as address_id');

        return DataTable::select([
            'member.member_id as id',
            'member.member_name as name',
            'member.member_code as code',
            'level.member_level_name as level',
            'level.member_level_code as level_code',
            'city.city_name as city_name',
            'city.city_type as city_type',
            ...array_map(fn (string $column): string => "metrics.{$column} as {$column}", $columns),
        ])
            ->fromSub($metrics, 'metrics')
            ->join('inner', 'member', 'member.member_id = metrics.member_id')
            ->join('inner', 'member_level as level', "level.member_level_id = {$levelKey}")
            ->leftJoinSub($addresses, 'default_address', 'default_address.member_address_member_id = member.member_id')
            ->leftJoin('member_address as address', 'address.member_address_id = default_address.address_id')
            ->leftJoin('ref_city as city', 'city.city_id = address.member_address_city_id')
            ->whereIn('level.member_level_code', $levels)
            ->orderByRaw('CASE level.member_level_code WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END', ['DST', 'AGT'])
            ->defaultSort($sort)
            ->search(['name', 'code', 'city_name'])
            ->allowUnpaginated()
            ->get($params);
    }

    private function purchases(CarbonImmutable $start): Builder
    {
        return DB::table('trx')
            ->select('trx_buyer_id as member_id')
            ->selectRaw('SUM(trx_grand_total_nett_price) as purchase_amount')
            ->where('trx_type', 'stock')
            ->whereIn('trx_buyer_type', ['distributor', 'agent', 'reseller'])
            ->whereIn('trx_status', ['processing', 'shipped', 'reship_required', 'ready_to_pickup', 'received', 'completed'])
            ->where('trx_datetime', '>=', $start)
            ->where('trx_datetime', '<', $start->addMonth())
            ->groupBy('trx_buyer_id');
    }

    private function recruitment(CarbonImmutable $start): Builder
    {
        return DB::table('member_registration')
            ->select('member_registration_submitter_member_id as member_id')
            ->selectRaw('COUNT(DISTINCT member_registration_member_id) as recruit_count')
            ->where('member_registration_submitter_member_id', '>', 0)
            ->where('member_registration_member_id', '>', 0)
            ->where('member_registration_status', 'approved')
            ->where('member_registration_status_datetime', '>=', $start)
            ->where('member_registration_status_datetime', '<', $start->addMonth())
            ->groupBy('member_registration_submitter_member_id');
    }

    private function monthlyRewards(int $year, int $month): Builder
    {
        return DB::table('reward_point_monthly')
            ->select([
                'reward_point_monthly_member_id as member_id',
                'reward_point_monthly_member_level_id as level_id',
                'reward_point_monthly_total_qty as monthly_points',
                'reward_point_monthly_bonus_value as reward_amount',
            ])
            ->where('reward_point_monthly_year', $year)
            ->where('reward_point_monthly_month', $month)
            ->where('reward_point_monthly_upline_id', 0)
            ->where('reward_point_monthly_bonus_value', '>', 0);
    }

    private function annualPoints(int $year): Builder
    {
        return DB::table('reward_point_annual')
            ->select('reward_point_annual_member_id as member_id', 'reward_point_annual_total_points as annual_points')
            ->where('reward_point_annual_year', $year);
    }
}
