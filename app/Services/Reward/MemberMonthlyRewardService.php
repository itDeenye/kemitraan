<?php

namespace App\Services\Reward;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\Member;
use App\Models\MemberBankAccount;
use App\Models\MemberLevel;
use App\Models\MemberPointTransaction;
use App\Models\RefBank;
use App\Models\RewardPointMonthly;
use App\Support\BusinessConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MemberMonthlyRewardService
{
    /** @param array<string, mixed> $params */
    public function monthlyRewards(int $memberId, array $params): array
    {
        $rewardTable = (new RewardPointMonthly)->getTable();

        $dt = DataTable::select([
            "{$rewardTable}.reward_point_monthly_id as id",
            "{$rewardTable}.reward_point_monthly_year as year",
            "{$rewardTable}.reward_point_monthly_month as month",
            "{$rewardTable}.reward_point_monthly_total_qty as total_points",
            "{$rewardTable}.reward_point_monthly_bonus_value as reward_value",
            "{$rewardTable}.reward_point_monthly_is_processed as is_processed",
            "{$rewardTable}.reward_point_monthly_processed_datetime as processed_at",
            "{$rewardTable}.reward_point_monthly_upline_id as responsible_sponsor_id",
            'responsible_sponsor.member_code as responsible_sponsor_code',
            'responsible_sponsor.member_name as responsible_sponsor_name',
            'responsible_sponsor_level.member_level_id as responsible_sponsor_level_id',
            'responsible_sponsor_level.member_level_code as responsible_sponsor_level_code',
            'responsible_sponsor_level.member_level_name as responsible_sponsor_level_name',
        ])
            ->from($rewardTable)
            ->leftJoin(
                'member as responsible_sponsor',
                "responsible_sponsor.member_id = {$rewardTable}.reward_point_monthly_upline_id",
            )
            ->leftJoin(
                'member_level as responsible_sponsor_level',
                'responsible_sponsor_level.member_level_id = responsible_sponsor.member_member_level_id',
            )
            ->where("{$rewardTable}.reward_point_monthly_member_id", $memberId);

        if (isset($params['year'])) {
            $dt->where("{$rewardTable}.reward_point_monthly_year", $params['year']);
        }
        if (isset($params['month'])) {
            $dt->where("{$rewardTable}.reward_point_monthly_month", $params['month']);
        }
        if (isset($params['is_processed'])) {
            $dt->where(
                "{$rewardTable}.reward_point_monthly_is_processed",
                (bool) $params['is_processed'],
            );
        }

        return $dt->defaultSort('-year,-month,-id')
            ->get($params);
    }

    /** @param array<string, mixed> $params */
    public function downlineMonthlyRewards(int $uplineId, array $params): array
    {
        $rewardTable = (new RewardPointMonthly)->getTable();
        $memberTable = (new Member)->getTable();
        $levelTable = (new MemberLevel)->getTable();
        $bankAccountTable = (new MemberBankAccount)->getTable();
        $bankTable = (new RefBank)->getTable();

        $dt = DataTable::select([
            "{$rewardTable}.reward_point_monthly_id as id",
            "{$rewardTable}.reward_point_monthly_member_id as member_id",
            "{$memberTable}.member_code as member_code",
            "{$memberTable}.member_name as member_name",
            "{$rewardTable}.reward_point_monthly_member_level_id as member_level_id",
            "{$levelTable}.member_level_code as member_level_code",
            "{$levelTable}.member_level_name as member_level_name",
            "{$levelTable}.member_level_point_value as point_value",
            "{$rewardTable}.reward_point_monthly_year as year",
            "{$rewardTable}.reward_point_monthly_month as month",
            "{$rewardTable}.reward_point_monthly_total_qty as total_points",
            "{$rewardTable}.reward_point_monthly_bonus_value as reward_value",
            "{$rewardTable}.reward_point_monthly_is_processed as is_processed",
            "{$rewardTable}.reward_point_monthly_processed_datetime as processed_at",
        ])
            ->from($rewardTable)
            ->leftJoin(
                $memberTable,
                "{$memberTable}.member_id = {$rewardTable}.reward_point_monthly_member_id",
            )
            ->leftJoin(
                $levelTable,
                "{$levelTable}.member_level_id = {$rewardTable}.reward_point_monthly_member_level_id",
            )
            ->where("{$rewardTable}.reward_point_monthly_upline_id", $uplineId)
            ->search(['member_code', 'member_name']);

        $dt->selectRaw("(SELECT {$bankAccountTable}.member_bank_account_bank_id FROM {$bankAccountTable} WHERE {$bankAccountTable}.member_bank_account_member_id = {$rewardTable}.reward_point_monthly_member_id AND {$bankAccountTable}.member_bank_account_is_active = 1 ORDER BY {$bankAccountTable}.member_bank_account_is_default DESC, {$bankAccountTable}.member_bank_account_id ASC LIMIT 1) as bank_id")
            ->selectRaw("(SELECT {$bankTable}.bank_code FROM {$bankTable} INNER JOIN {$bankAccountTable} ON {$bankAccountTable}.member_bank_account_bank_id = {$bankTable}.bank_id WHERE {$bankAccountTable}.member_bank_account_member_id = {$rewardTable}.reward_point_monthly_member_id AND {$bankAccountTable}.member_bank_account_is_active = 1 ORDER BY {$bankAccountTable}.member_bank_account_is_default DESC, {$bankAccountTable}.member_bank_account_id ASC LIMIT 1) as bank_code")
            ->selectRaw("(SELECT {$bankTable}.bank_name FROM {$bankTable} INNER JOIN {$bankAccountTable} ON {$bankAccountTable}.member_bank_account_bank_id = {$bankTable}.bank_id WHERE {$bankAccountTable}.member_bank_account_member_id = {$rewardTable}.reward_point_monthly_member_id AND {$bankAccountTable}.member_bank_account_is_active = 1 ORDER BY {$bankAccountTable}.member_bank_account_is_default DESC, {$bankAccountTable}.member_bank_account_id ASC LIMIT 1) as bank_name")
            ->selectRaw("(SELECT {$bankAccountTable}.member_bank_account_name FROM {$bankAccountTable} WHERE {$bankAccountTable}.member_bank_account_member_id = {$rewardTable}.reward_point_monthly_member_id AND {$bankAccountTable}.member_bank_account_is_active = 1 ORDER BY {$bankAccountTable}.member_bank_account_is_default DESC, {$bankAccountTable}.member_bank_account_id ASC LIMIT 1) as bank_account_name")
            ->selectRaw("(SELECT {$bankAccountTable}.member_bank_account_number FROM {$bankAccountTable} WHERE {$bankAccountTable}.member_bank_account_member_id = {$rewardTable}.reward_point_monthly_member_id AND {$bankAccountTable}.member_bank_account_is_active = 1 ORDER BY {$bankAccountTable}.member_bank_account_is_default DESC, {$bankAccountTable}.member_bank_account_id ASC LIMIT 1) as bank_account_number");

        if (isset($params['year'])) {
            $dt->where("{$rewardTable}.reward_point_monthly_year", $params['year']);
        }
        if (isset($params['month'])) {
            $dt->where("{$rewardTable}.reward_point_monthly_month", $params['month']);
        }
        if (isset($params['is_processed'])) {
            $dt->where(
                "{$rewardTable}.reward_point_monthly_is_processed",
                (bool) $params['is_processed'],
            );
        }

        return $dt->defaultSort('-year,-month,member_name,-id')
            ->get($params);
    }

    /** @param array<string, mixed> $params */
    public function summary(int $memberId, array $params): array
    {
        $query = RewardPointMonthly::query()
            ->where('reward_point_monthly_member_id', $memberId);

        return $this->summarize($this->applySummaryFilters($query, $params));
    }

    /** @param array<string, mixed> $params */
    public function downlineSummary(int $uplineId, array $params): array
    {
        $query = RewardPointMonthly::query()
            ->where('reward_point_monthly_upline_id', $uplineId);

        return $this->summarize($this->applySummaryFilters($query, $params));
    }

    public function approveDownlineReward(
        RewardPointMonthly $reward,
        int $uplineId,
    ): RewardPointMonthly {
        return DB::transaction(function () use ($reward, $uplineId): RewardPointMonthly {
            $lockedReward = RewardPointMonthly::query()
                ->whereKey($reward->getKey())
                ->where('reward_point_monthly_upline_id', $uplineId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedReward->reward_point_monthly_bonus_value <= 0) {
                throw new ProcessException('Reward tidak terkualifikasi dan tidak perlu dibayarkan.');
            }

            if ($lockedReward->reward_point_monthly_is_processed) {
                throw new ProcessException('Reward bulanan ini sudah disetujui sebelumnya.');
            }

            $lockedReward->update([
                'reward_point_monthly_is_processed' => 1,
                'reward_point_monthly_processed_datetime' => now(),
            ]);

            return $lockedReward->refresh()->load(['member', 'memberLevel']);
        });
    }

    /** @return list<int> */
    public function availableYears(int $memberId): array
    {
        return RewardPointMonthly::query()
            ->where('reward_point_monthly_member_id', $memberId)
            ->distinct()
            ->orderByDesc('reward_point_monthly_year')
            ->pluck('reward_point_monthly_year')
            ->map(fn (mixed $year): int => (int) $year)
            ->values()
            ->all();
    }

    /** @return list<int> */
    public function availableDownlineYears(int $uplineId): array
    {
        return RewardPointMonthly::query()
            ->where('reward_point_monthly_upline_id', $uplineId)
            ->distinct()
            ->orderByDesc('reward_point_monthly_year')
            ->pluck('reward_point_monthly_year')
            ->map(fn (mixed $year): int => (int) $year)
            ->values()
            ->all();
    }

    public function downlineActionCount(int $uplineId): int
    {
        return RewardPointMonthly::query()
            ->where('reward_point_monthly_upline_id', $uplineId)
            ->where('reward_point_monthly_bonus_value', '>', 0)
            ->where('reward_point_monthly_is_processed', 0)
            ->count();
    }

    /** @param array<string, mixed> $params */
    public function growth(int $memberId, array $params): array
    {
        $period = $this->growthPeriod($memberId, $params);
        $summaryPeriod = $this->summaryPeriod($period, $params);
        $from = CarbonImmutable::create($period['year'], 1, 1);
        $to = CarbonImmutable::create($period['year'], $period['month'], 1);
        $rows = RewardPointMonthly::query()
            ->where('reward_point_monthly_member_id', $memberId)
            ->where('reward_point_monthly_year', $period['year'])
            ->whereBetween('reward_point_monthly_month', [1, $period['month']])
            ->selectRaw('reward_point_monthly_month as month')
            ->selectRaw('SUM(reward_point_monthly_bonus_value) as reward_value')
            ->selectRaw('SUM(reward_point_monthly_total_qty) as total_points')
            ->groupBy('reward_point_monthly_month')
            ->get()
            ->keyBy('month');
        $summary = $this->realtimeMonthlySummary(
            $memberId,
            $summaryPeriod['year'],
            $summaryPeriod['month'],
        );

        $series = collect(range(1, $period['month']))
            ->map(fn (int $month): array => $this->growthSeriesItem($month, $rows))
            ->values()
            ->all();
        $current = $series[$period['month'] - 1] ?? $this->growthSeriesItem($period['month'], collect());
        $previous = $period['month'] > 1
            ? ($series[$period['month'] - 2] ?? null)
            : null;
        $growthPercent = $this->growthPercent(
            (int) $current['reward_value'],
            (int) ($previous['reward_value'] ?? 0),
        );

        return [
            'period' => [
                'year' => $period['year'],
                'month' => $period['month'],
                'label' => $this->monthName($period['month']).' '.$period['year'],
                'range_label' => $this->monthName($from->month).'–'.$this->monthName($to->month).' '.$period['year'],
            ],
            'summary' => [
                'year' => $summary['year'],
                'month' => $summary['month'],
                'label' => $summary['label'],
                'member_level' => $summary['member_level'],
                'minimum_points' => $summary['minimum_points'],
                'remaining_points' => $summary['remaining_points'],
                'is_qualified' => $summary['is_qualified'],
                'monthly_reward' => $summary['monthly_reward'],
                'total_points' => $summary['total_points'],
                'source' => $summary['source'],
                'is_realtime' => $summary['is_realtime'],
            ],
            'growth' => [
                'percent' => $growthPercent,
                'status' => match (true) {
                    $growthPercent > 0 => 'up',
                    $growthPercent < 0 => 'down',
                    default => 'flat',
                },
                'label' => match (true) {
                    $growthPercent > 0 => 'Naik',
                    $growthPercent < 0 => 'Turun',
                    default => 'Stabil',
                },
            ],
            'series' => $series,
        ];
    }

    /**
     * @param  array{year: int, month: int}  $growthPeriod
     * @param  array<string, mixed>  $params
     * @return array{year: int, month: int}
     */
    private function summaryPeriod(array $growthPeriod, array $params): array
    {
        if (isset($params['year'], $params['month'])) {
            return [
                'year' => (int) $params['year'],
                'month' => (int) $params['month'],
            ];
        }

        if (isset($params['year']) && (int) $params['year'] !== now()->year) {
            return $growthPeriod;
        }

        return [
            'year' => now()->year,
            'month' => now()->month,
        ];
    }

    /** @return array{year: int, month: int, label: string, member_level: array{id: int, code: string, name: string}, minimum_points: int, remaining_points: int, is_qualified: bool, monthly_reward: int, total_points: int, source: string, is_realtime: bool} */
    private function realtimeMonthlySummary(int $memberId, int $year, int $month): array
    {
        $period = CarbonImmutable::create($year, $month, 1);
        $member = Member::query()
            ->with('level')
            ->find($memberId);
        $minimumQuantity = $this->minimumMonthlyQuantity($member);
        $level = [
            'id' => (int) ($member?->level?->member_level_id ?? 0),
            'code' => (string) ($member?->level?->member_level_code ?? ''),
            'name' => (string) ($member?->level?->member_level_name ?? ''),
        ];
        $quantity = (int) MemberPointTransaction::query()
            ->where('member_point_transaction_member_id', $memberId)
            ->whereBetween('member_point_transaction_approved_datetime', [
                $period->startOfMonth(),
                $period->endOfMonth(),
            ])
            ->sum('member_point_transaction_quantity');
        $source = 'realtime';

        if ($quantity === 0) {
            $snapshot = RewardPointMonthly::query()
                ->where('reward_point_monthly_member_id', $memberId)
                ->where('reward_point_monthly_year', $year)
                ->where('reward_point_monthly_month', $month)
                ->first([
                    'reward_point_monthly_total_qty',
                    'reward_point_monthly_bonus_value',
                ]);

            if ($snapshot) {
                return [
                    'year' => $year,
                    'month' => $month,
                    'label' => $this->monthName($month).' '.$year,
                    'member_level' => $level,
                    'minimum_points' => $minimumQuantity,
                    'remaining_points' => max(
                        0,
                        $minimumQuantity - (int) $snapshot->reward_point_monthly_total_qty,
                    ),
                    'is_qualified' => (int) $snapshot->reward_point_monthly_total_qty >= $minimumQuantity,
                    'monthly_reward' => (int) $snapshot->reward_point_monthly_bonus_value,
                    'total_points' => (int) $snapshot->reward_point_monthly_total_qty,
                    'source' => 'snapshot',
                    'is_realtime' => false,
                ];
            }
        }

        $pointValue = (int) ($member?->level?->member_level_point_value ?? 0);

        return [
            'year' => $year,
            'month' => $month,
            'label' => $this->monthName($month).' '.$year,
            'member_level' => $level,
            'minimum_points' => $minimumQuantity,
            'remaining_points' => max(0, $minimumQuantity - $quantity),
            'is_qualified' => $quantity >= $minimumQuantity,
            'monthly_reward' => $quantity >= $minimumQuantity
                ? $quantity * $pointValue
                : 0,
            'total_points' => $quantity,
            'source' => $source,
            'is_realtime' => true,
        ];
    }

    private function minimumMonthlyQuantity(?Member $member): int
    {
        $levelCode = (string) ($member?->level?->member_level_code ?? '');
        $levelName = match ($levelCode) {
            'DST' => 'distributor',
            'AGT' => 'agent',
            'RSL' => 'reseller',
            default => strtolower($levelCode),
        };

        return (int) BusinessConfig::get(
            'reward_monthly.reward_monthly_min_qty_'.$levelName,
            match ($levelCode) {
                'DST' => 50,
                'AGT' => 30,
                'RSL' => 20,
                default => PHP_INT_MAX,
            },
        );
    }

    /** @param array<string, mixed> $params */
    private function applySummaryFilters(Builder $query, array $params): Builder
    {
        return $query
            ->when(isset($params['year']), fn (Builder $builder) => $builder
                ->where('reward_point_monthly_year', $params['year']))
            ->when(isset($params['month']), fn (Builder $builder) => $builder
                ->where('reward_point_monthly_month', $params['month']))
            ->when(isset($params['is_processed']), fn (Builder $builder) => $builder
                ->where('reward_point_monthly_is_processed', (bool) $params['is_processed']));
    }

    /** @param array<string, mixed> $params
     * @return array{year: int, month: int}
     */
    private function growthPeriod(int $memberId, array $params): array
    {
        $year = isset($params['year']) ? (int) $params['year'] : null;
        $month = isset($params['month']) ? (int) $params['month'] : null;

        if ($year !== null && $month !== null) {
            return [
                'year' => $year,
                'month' => $month,
            ];
        }

        if ($year !== null) {
            $latestMonth = RewardPointMonthly::query()
                ->where('reward_point_monthly_member_id', $memberId)
                ->where('reward_point_monthly_year', $year)
                ->max('reward_point_monthly_month');

            return [
                'year' => $year,
                'month' => (int) ($latestMonth ?: ($year === now()->year ? now()->month : 12)),
            ];
        }

        $latest = RewardPointMonthly::query()
            ->where('reward_point_monthly_member_id', $memberId)
            ->orderByDesc('reward_point_monthly_year')
            ->orderByDesc('reward_point_monthly_month')
            ->first([
                'reward_point_monthly_year',
                'reward_point_monthly_month',
            ]);

        if ($latest) {
            return [
                'year' => (int) $latest->reward_point_monthly_year,
                'month' => (int) $latest->reward_point_monthly_month,
            ];
        }

        return [
            'year' => now()->year,
            'month' => now()->month,
        ];
    }

    /** @param Collection<int, object> $rows */
    private function growthSeriesItem(int $month, Collection $rows): array
    {
        $row = $rows->get($month);

        return [
            'month' => $month,
            'label' => $this->shortMonthName($month),
            'reward_value' => (int) ($row?->reward_value ?? 0),
            'total_points' => (int) ($row?->total_points ?? 0),
        ];
    }

    private function growthPercent(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function monthName(int $month): string
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ][$month] ?? '';
    }

    private function shortMonthName(int $month): string
    {
        return [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Agu',
            9 => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ][$month] ?? '';
    }

    /** @return array{accumulated: int, paid: int, unpaid: int} */
    private function summarize(Builder $query): array
    {
        $totalAccumulated = (int) (clone $query)->sum('reward_point_monthly_bonus_value');
        $totalPaid = (int) (clone $query)
            ->where('reward_point_monthly_is_processed', 1)
            ->sum('reward_point_monthly_bonus_value');

        return [
            'accumulated' => $totalAccumulated,
            'paid' => $totalPaid,
            'unpaid' => $totalAccumulated - $totalPaid,
        ];
    }

    public function monthlyReward(RewardPointMonthly $reward): RewardPointMonthly
    {
        return $reward->loadMissing('upline.level');
    }
}
