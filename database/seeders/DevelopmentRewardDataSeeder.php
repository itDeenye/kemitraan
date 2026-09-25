<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\MemberAchievement;
use App\Models\RewardPointAnnual;
use App\Models\RewardPointAnnualLog;
use App\Models\RewardPointMonthly;
use App\Models\RewardStockist;
use App\Models\SiteAdministrator;
use App\Models\Trx;
use App\Support\BusinessConfig;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DevelopmentRewardDataSeeder extends Seeder
{
    private const PURCHASED_RETAIL_STATUSES = [
        'processing',
        'shipped',
        'ready_to_pickup',
        'received',
        'completed',
    ];

    private const MONTHLY_MINIMUM_QUANTITY = [
        'DST' => 50,
        'AGT' => 30,
        'RSL' => 20,
    ];

    private const STOCKIST_MINIMUM_AMOUNT = 50_000_000;

    private const STOCKIST_PERCENTAGE_BASIS_POINTS = 250;

    public function run(): void
    {
        $members = Member::query()
            ->with(['level', 'parent.level', 'stockist'])
            ->where(function ($query): void {
                $query->whereKey(1)
                    ->orWhereIn('member_mobilephone', ['+6281200000101', '+6281200000201']);
            })
            ->get();
        $administrator = SiteAdministrator::query()->findOrFail(1);
        $now = now();

        DB::transaction(function () use ($administrator, $members, $now): void {
            $periods = collect(range(3, 1))->map(
                fn (int $monthsAgo): CarbonInterface => $now->copy()->subMonths($monthsAgo)->startOfMonth(),
            );

            foreach ($members as $member) {
                $purchases = $this->eligibleTransactions($member);

                $this->achievements($member, $purchases, $periods);
                $this->monthlyRewards($member, $administrator, $purchases, $periods, $now);
                $this->annualReward($member, $purchases, $now);
                $this->stockistRewards($member, $purchases, $periods, $now);
            }
        });
    }

    /** @return Collection<int, Trx> */
    private function eligibleTransactions(Member $member): Collection
    {
        $approvedTransactionIds = DB::table('member_point_transaction')
            ->where('member_point_transaction_member_id', $member->getKey())
            ->pluck('member_point_transaction_trx_id');

        return Trx::query()
            ->with('details')
            ->where('trx_buyer_id', $member->getKey())
            ->where('trx_buyer_type', $this->memberType($member))
            ->where('trx_type', 'stock')
            ->where(function ($query) use ($approvedTransactionIds): void {
                $query->whereIn('trx_status', ['received', 'completed'])
                    ->orWhereIn('trx_id', $approvedTransactionIds);
            })
            ->orderBy('trx_datetime')
            ->get();
    }

    /**
     * @param  Collection<int, Trx>  $transactions
     * @param  Collection<int, CarbonInterface>  $periods
     */
    private function monthlyRewards(
        Member $member,
        SiteAdministrator $administrator,
        Collection $transactions,
        Collection $periods,
        CarbonInterface $now,
    ): void {
        $levelCode = (string) $member->level?->member_level_code;
        $minimumQuantity = $this->integerConfig(
            'reward_monthly.reward_monthly_min_qty_'.strtolower($this->levelName($levelCode)),
            self::MONTHLY_MINIMUM_QUANTITY[$levelCode],
        );
        $pointValue = (int) $member->level?->member_level_point_value;

        foreach ($periods as $period) {
            $periodTransactions = $this->transactionsForPeriod($transactions, $period);
            $quantity = $this->quantity($periodTransactions);
            $qualified = $quantity >= $minimumQuantity;
            $processed = $qualified && $period->lt($now->copy()->startOfMonth());
            $processedByAdministrator = $processed && $levelCode === 'DST';

            RewardPointMonthly::query()->updateOrCreate(
                [
                    'reward_point_monthly_member_id' => $member->getKey(),
                    'reward_point_monthly_year' => $period->year,
                    'reward_point_monthly_month' => $period->month,
                ],
                [
                    'reward_point_monthly_upline_id' => (int) ($member->member_parent_member_id ?? 0),
                    'reward_point_monthly_upline_level_id' => (int) ($member->parent?->member_member_level_id ?? 0),
                    'reward_point_monthly_member_level_id' => $member->member_member_level_id,
                    'reward_point_monthly_total_qty' => $quantity,
                    'reward_point_monthly_bonus_value' => $qualified ? $quantity * $pointValue : 0,
                    'reward_point_monthly_is_processed' => $processed,
                    'reward_point_monthly_admin_id' => $processedByAdministrator
                        ? $administrator->getKey()
                        : 0,
                    'reward_point_monthly_processed_datetime' => $processed
                        ? $period->copy()->endOfMonth()->endOfDay()
                        : null,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Trx>  $transactions
     * @param  Collection<int, CarbonInterface>  $periods
     */
    private function achievements(
        Member $member,
        Collection $transactions,
        Collection $periods,
    ): void {
        foreach ($periods as $period) {
            $periodTransactions = $this->transactionsForPeriod($transactions, $period);

            MemberAchievement::query()->updateOrCreate(
                [
                    'member_achievement_member_id' => $member->getKey(),
                    'member_achievement_year' => $period->year,
                    'member_achievement_month' => $period->month,
                ],
                [
                    'member_achievement_point' => $this->quantity($periodTransactions),
                    'member_achievement_customer_count' => $this->customerCount($member, $period),
                    'member_achievement_total_trx_amount' => (int) $periodTransactions
                        ->sum('trx_grand_total_price'),
                    'member_achievement_create_datetime' => $period->copy()->endOfMonth()->endOfDay(),
                ],
            );
        }
    }

    /** @param Collection<int, Trx> $transactions */
    private function annualReward(
        Member $member,
        Collection $transactions,
        CarbonInterface $now,
    ): void {
        RewardPointAnnualLog::query()
            ->where('reward_point_annual_log_member_id', $member->getKey())
            ->where('reward_point_annual_log_note', 'like', 'Data development bulan %')
            ->delete();

        foreach ($transactions->filter(
            fn (Trx $trx): bool => $trx->trx_datetime?->year === $now->year,
        ) as $trx) {
            RewardPointAnnualLog::query()->updateOrCreate(
                [
                    'reward_point_annual_log_member_id' => $member->getKey(),
                    'reward_point_annual_log_trx_id' => $trx->getKey(),
                    'reward_point_annual_log_type' => 'in',
                ],
                [
                    'reward_point_annual_log_points' => $this->quantity(collect([$trx])),
                    'reward_point_annual_log_note' => "Poin dari transaksi {$trx->trx_code}",
                    'reward_point_annual_log_datetime' => $trx->trx_status_datetime ?? $trx->trx_datetime,
                ],
            );
        }

        $totalPoints = (int) RewardPointAnnualLog::query()
            ->where('reward_point_annual_log_member_id', $member->getKey())
            ->whereYear('reward_point_annual_log_datetime', $now->year)
            ->selectRaw("SUM(CASE WHEN reward_point_annual_log_type = 'in' THEN reward_point_annual_log_points ELSE -reward_point_annual_log_points END) AS points")
            ->value('points');

        RewardPointAnnual::query()->updateOrCreate(
            [
                'reward_point_annual_member_id' => $member->getKey(),
                'reward_point_annual_year' => $now->year,
            ],
            [
                'reward_point_annual_total_points' => max(0, $totalPoints),
                'reward_point_annual_last_updated_datetime' => $now,
            ],
        );
    }

    /**
     * @param  Collection<int, Trx>  $transactions
     * @param  Collection<int, CarbonInterface>  $periods
     */
    private function stockistRewards(
        Member $member,
        Collection $transactions,
        Collection $periods,
        CarbonInterface $now,
    ): void {
        if (! $member->stockist) {
            return;
        }

        $minimumAmount = $this->integerConfig(
            'reward_stockist.reward_stockist_min_amount',
            self::STOCKIST_MINIMUM_AMOUNT,
        );
        $percentage = $this->integerConfig(
            'reward_stockist.reward_stockist_percentage_basis_points',
            self::STOCKIST_PERCENTAGE_BASIS_POINTS,
        );

        foreach ($periods as $period) {
            $periodTransactions = $this->transactionsForPeriod($transactions, $period);
            $totalAmount = (int) $periodTransactions->sum('trx_grand_total_price');

            if ($totalAmount < $minimumAmount) {
                continue;
            }

            $voucherValue = intdiv($totalAmount * $percentage, 10_000);
            $nextPeriod = $period->copy()->addMonth();
            $usedTransaction = $period->lt($now->copy()->subMonth()->startOfMonth())
                ? $this->transactionsForPeriod($transactions, $nextPeriod)->first()
                : null;

            RewardStockist::query()->updateOrCreate(
                [
                    'reward_stockist_member_id' => $member->getKey(),
                    'reward_stockist_year' => $period->year,
                    'reward_stockist_month' => $period->month,
                ],
                [
                    'reward_stockist_total_trx_amount' => $totalAmount,
                    'reward_stockist_bonus_value' => $voucherValue,
                    'reward_stockist_used_value' => $usedTransaction ? $voucherValue : 0,
                    'reward_stockist_used_trx_id' => $usedTransaction?->getKey() ?? 0,
                    'reward_stockist_expiry_date' => $period->copy()->addMonth()->endOfMonth(),
                    'reward_stockist_created_datetime' => $period->copy()->endOfMonth()->endOfDay(),
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Trx>  $transactions
     * @return Collection<int, Trx>
     */
    private function transactionsForPeriod(
        Collection $transactions,
        CarbonInterface $period,
    ): Collection {
        return $transactions->filter(
            fn (Trx $trx): bool => $trx->trx_datetime?->year === $period->year
                && $trx->trx_datetime?->month === $period->month,
        )->values();
    }

    /** @param Collection<int, Trx> $transactions */
    private function quantity(Collection $transactions): int
    {
        return (int) $transactions->sum(
            fn (Trx $trx): int => (int) $trx->details->sum('trx_detail_qty'),
        );
    }

    private function customerCount(Member $member, CarbonInterface $period): int
    {
        return Trx::query()
            ->where('trx_seller_id', $member->getKey())
            ->where('trx_seller_type', $this->memberType($member))
            ->where('trx_buyer_type', 'customer')
            ->where('trx_type', 'retail')
            ->whereIn('trx_status', self::PURCHASED_RETAIL_STATUSES)
            ->whereYear('trx_datetime', $period->year)
            ->whereMonth('trx_datetime', $period->month)
            ->distinct('trx_buyer_id')
            ->count('trx_buyer_id');
    }

    private function integerConfig(string $key, int $default): int
    {
        return (int) BusinessConfig::get($key, $default);
    }

    private function memberType(Member $member): string
    {
        return match ($member->level?->member_level_code) {
            'DST' => 'distributor',
            'AGT' => 'agent',
            'RSL' => 'reseller',
            default => 'member',
        };
    }

    private function levelName(string $levelCode): string
    {
        return match ($levelCode) {
            'DST' => 'distributor',
            'AGT' => 'agent',
            'RSL' => 'reseller',
        };
    }
}
