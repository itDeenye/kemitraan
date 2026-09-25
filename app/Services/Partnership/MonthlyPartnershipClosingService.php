<?php

namespace App\Services\Partnership;

use App\Models\Member;
use App\Models\MemberAchievement;
use App\Models\MemberBankAccount;
use App\Models\MemberLevel;
use App\Models\MemberUpgradeQualified;
use App\Models\RewardPointMonthly;
use App\Models\RewardStockist;
use App\Models\Trx;
use App\Models\TrxDetail;
use App\Models\TrxSpreadPayment;
use App\Support\BusinessConfig;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyPartnershipClosingService
{
    private const MEMBER_TYPES = ['distributor', 'agent', 'reseller'];

    private const PURCHASED_RETAIL_STATUSES = [
        'processing',
        'shipped',
        'ready_to_pickup',
        'received',
        'completed',
    ];

    /**
     * Urutan perhitungan penutupan reward bulanan:
     * 1. Normalisasi periode ke awal bulan dan ambil seluruh mitra aktif.
     * 2. Rekap kuantitas dan nilai pembelian dari ledger poin yang disetujui pada periode tersebut.
     *    Untuk PO, ledger baru dibuat setelah seluruh pembayaran dalam rantai PO disetujui.
     * 3. Tambahkan transaksi lama berstatus diterima/selesai yang belum mempunyai ledger poin.
     * 4. Simpan pencapaian bulanan: poin, jumlah pelanggan, dan total nilai transaksi setiap mitra.
     * 5. Hitung reward bulanan jika poin mencapai minimum level: poin x nilai poin level.
     * 6. Hitung reward stokis berdasarkan nilai pembelian dan konfigurasi persentase.
     * 7. Lengkapi sharing profit lama dan evaluasi kualifikasi upgrade berdasarkan periode terkait.
     *
     * @return array{period: string, achievements: int, monthly_rewards: int, stockist_rewards: int, spread_payments: int, upgrade_qualifications: int}
     */
    public function close(CarbonInterface $period): array
    {
        // 1. Semua perhitungan selalu menggunakan satu bulan kalender penuh.
        $period = CarbonImmutable::instance($period)->startOfMonth();

        return DB::transaction(function () use ($period): array {
            $members = Member::query()
                ->with(['level', 'parent.level', 'stockist'])
                ->where('member_status', 1)
                ->orderBy('member_id')
                ->get();

            // 2-3. Rekap sumber angka yang akan dipakai untuk setiap mitra.
            $quantityByMember = $this->purchaseQuantities($period);
            $purchaseAmountByMember = $this->purchaseAmounts($period);
            $customerCountByMember = $this->customerCounts($period, $period);
            $result = [
                'period' => $period->format('Y-m'),
                'achievements' => 0,
                'monthly_rewards' => 0,
                'stockist_rewards' => 0,
                'spread_payments' => 0,
                'upgrade_qualifications' => 0,
            ];

            foreach ($members as $member) {
                if (! $member->level) {
                    continue;
                }

                $quantity = (int) ($quantityByMember->get($member->getKey()) ?? 0);
                $purchaseAmount = (int) ($purchaseAmountByMember->get($member->getKey()) ?? 0);
                $customerCount = (int) ($customerCountByMember->get($member->getKey()) ?? 0);

                // 4. Snapshot pencapaian operasional mitra pada bulan yang ditutup.
                MemberAchievement::query()->updateOrCreate(
                    [
                        'member_achievement_member_id' => $member->getKey(),
                        'member_achievement_year' => $period->year,
                        'member_achievement_month' => $period->month,
                    ],
                    [
                        'member_achievement_point' => $quantity,
                        'member_achievement_customer_count' => $customerCount,
                        'member_achievement_total_trx_amount' => $purchaseAmount,
                        'member_achievement_create_datetime' => $period->endOfMonth()->endOfDay(),
                    ],
                );
                $result['achievements']++;

                // 5. Reward bulanan hanya bernilai jika minimum poin level tercapai.
                $this->storeMonthlyReward($member, $period, $quantity);
                $result['monthly_rewards']++;

                // 6. Reward stokis memakai total nilai pembelian yang sudah sah.
                if ($this->storeStockistReward($member, $period, $purchaseAmount)) {
                    $result['stockist_rewards']++;
                }
            }

            // 7. Lengkapi data lama dan hitung kualifikasi upgrade setelah rekap tersimpan.
            // firstOrCreate pada sharing profit menjaga proses tetap idempoten.
            $result['spread_payments'] = $this->createSpreadPayments($period);
            $result['upgrade_qualifications'] = $this->createUpgradeQualifications($members, $period);

            return $result;
        });
    }

    /** @return Collection<int, int> */
    private function purchaseQuantities(CarbonImmutable $period): Collection
    {
        $trxTable = (new Trx)->getTable();
        $detailTable = (new TrxDetail)->getTable();
        $pointTable = 'member_point_transaction';

        // 2. Sumber utama: ledger poin. PO belum masuk ledger sampai seluruh rantai pembayarannya approved.
        $ledger = DB::table($pointTable)
            ->whereBetween("{$pointTable}.member_point_transaction_approved_datetime", [
                $period->startOfMonth(),
                $period->endOfMonth(),
            ])
            ->select("{$pointTable}.member_point_transaction_member_id as member_id")
            ->selectRaw("SUM({$pointTable}.member_point_transaction_quantity) AS total_quantity")
            ->groupBy("{$pointTable}.member_point_transaction_member_id")
            ->get();

        // 3. Backfill hanya untuk transaksi lama yang selesai sebelum ledger poin diberlakukan.
        // whereNotExists mencegah transaksi baru dihitung dua kali.
        $legacy = DB::table($trxTable)
            ->join($detailTable, "{$detailTable}.trx_detail_trx_id", '=', "{$trxTable}.trx_id")
            ->whereNotExists(function ($query) use ($pointTable, $trxTable): void {
                $query->select(DB::raw(1))
                    ->from($pointTable)
                    ->whereColumn(
                        "{$pointTable}.member_point_transaction_trx_id",
                        "{$trxTable}.trx_id",
                    );
            })
            ->whereIn("{$trxTable}.trx_buyer_type", self::MEMBER_TYPES)
            ->where("{$trxTable}.trx_type", 'stock')
            ->whereIn("{$trxTable}.trx_status", ['received', 'completed'])
            ->whereBetween("{$trxTable}.trx_datetime", [$period->startOfMonth(), $period->endOfMonth()])
            ->select("{$trxTable}.trx_buyer_id as member_id")
            ->selectRaw("SUM({$detailTable}.trx_detail_qty) AS total_quantity")
            ->groupBy("{$trxTable}.trx_buyer_id")
            ->get();

        return $ledger->concat($legacy)
            ->groupBy('member_id')
            ->map(fn (Collection $rows): int => (int) $rows->sum('total_quantity'));
    }

    /** @return Collection<int, int> */
    private function purchaseAmounts(CarbonImmutable $period): Collection
    {
        $trxTable = (new Trx)->getTable();
        $pointTable = 'member_point_transaction';

        $ledger = DB::table($pointTable)
            ->join(
                $trxTable,
                "{$trxTable}.trx_id",
                '=',
                "{$pointTable}.member_point_transaction_trx_id",
            )
            ->whereBetween("{$pointTable}.member_point_transaction_approved_datetime", [
                $period->startOfMonth(),
                $period->endOfMonth(),
            ])
            ->select("{$pointTable}.member_point_transaction_member_id as member_id")
            ->selectRaw("SUM({$trxTable}.trx_grand_total_price) AS total_amount")
            ->groupBy("{$pointTable}.member_point_transaction_member_id")
            ->get();

        $legacy = DB::table($trxTable)
            ->whereNotExists(function ($query) use ($pointTable, $trxTable): void {
                $query->select(DB::raw(1))
                    ->from($pointTable)
                    ->whereColumn(
                        "{$pointTable}.member_point_transaction_trx_id",
                        "{$trxTable}.trx_id",
                    );
            })
            ->whereIn("{$trxTable}.trx_buyer_type", self::MEMBER_TYPES)
            ->where("{$trxTable}.trx_type", 'stock')
            ->whereIn("{$trxTable}.trx_status", ['received', 'completed'])
            ->whereBetween("{$trxTable}.trx_datetime", [$period->startOfMonth(), $period->endOfMonth()])
            ->select("{$trxTable}.trx_buyer_id as member_id")
            ->selectRaw("SUM({$trxTable}.trx_grand_total_price) AS total_amount")
            ->groupBy("{$trxTable}.trx_buyer_id")
            ->get();

        return $ledger->concat($legacy)
            ->groupBy('member_id')
            ->map(fn (Collection $rows): int => (int) $rows->sum('total_amount'));
    }

    /** @return Collection<int, int> */
    private function customerCounts(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Trx::query()
            ->whereIn('trx_seller_type', self::MEMBER_TYPES)
            ->where('trx_buyer_type', 'customer')
            ->where('trx_type', 'retail')
            ->whereIn('trx_status', self::PURCHASED_RETAIL_STATUSES)
            ->whereBetween('trx_datetime', [$from->startOfMonth(), $to->endOfMonth()])
            ->selectRaw('trx_seller_id, COUNT(DISTINCT trx_buyer_id) AS customer_count')
            ->groupBy('trx_seller_id')
            ->pluck('customer_count', 'trx_seller_id')
            ->map(fn (mixed $value): int => (int) $value);
    }

    private function storeMonthlyReward(Member $member, CarbonImmutable $period, int $quantity): void
    {
        $minimumQuantity = $this->integerConfig(
            'reward_monthly.reward_monthly_min_qty_'.strtolower($this->levelName($member->level->member_level_code)),
            match ($member->level->member_level_code) {
                'DST' => 50,
                'AGT' => 30,
                'RSL' => 20,
                default => PHP_INT_MAX,
            },
        );
        $existing = RewardPointMonthly::query()->firstOrNew([
            'reward_point_monthly_member_id' => $member->getKey(),
            'reward_point_monthly_year' => $period->year,
            'reward_point_monthly_month' => $period->month,
        ]);

        if ($existing->exists && $existing->reward_point_monthly_is_processed) {
            return;
        }

        $existing->fill([
            'reward_point_monthly_total_qty' => $quantity,
            'reward_point_monthly_bonus_value' => $quantity >= $minimumQuantity
                ? $quantity * (int) $member->level->member_level_point_value
                : 0,
        ]);
        if (! $existing->exists) {
            $existing->fill([
                'reward_point_monthly_upline_id' => (int) $member->member_parent_member_id,
                'reward_point_monthly_upline_level_id' => (int) ($member->parent?->member_member_level_id ?? 0),
                'reward_point_monthly_member_level_id' => $member->member_member_level_id,
                'reward_point_monthly_admin_id' => 0,
                'reward_point_monthly_is_processed' => 0,
                'reward_point_monthly_processed_datetime' => null,
            ]);
        }
        $existing->save();
    }

    private function storeStockistReward(Member $member, CarbonImmutable $period, int $amount): bool
    {
        if (! $member->stockist) {
            return false;
        }

        $minimumAmount = $this->integerConfig('reward_stockist.reward_stockist_min_amount', 50_000_000);
        if ($amount < $minimumAmount) {
            return false;
        }

        $percentage = $this->integerConfig('reward_stockist.reward_stockist_percentage_basis_points', 250);
        $reward = RewardStockist::query()->firstOrNew([
            'reward_stockist_member_id' => $member->getKey(),
            'reward_stockist_year' => $period->year,
            'reward_stockist_month' => $period->month,
        ]);
        if ($reward->exists && (int) $reward->reward_stockist_used_trx_id > 0) {
            return true;
        }
        $reward->fill([
            'reward_stockist_total_trx_amount' => $amount,
            'reward_stockist_bonus_value' => intdiv($amount * $percentage, 10_000),
            'reward_stockist_expiry_date' => $period->addMonth()->endOfMonth(),
            'reward_stockist_created_datetime' => now(),
        ]);
        if (! $reward->exists) {
            $reward->fill([
                'reward_stockist_used_value' => 0,
                'reward_stockist_used_trx_id' => 0,
            ]);
        }
        $reward->save();

        return true;
    }

    private function createSpreadPayments(CarbonImmutable $period): int
    {
        $transactions = Trx::query()
            ->whereIn('trx_seller_type', self::MEMBER_TYPES)
            ->whereIn('trx_buyer_type', self::MEMBER_TYPES)
            ->where('trx_type', 'stock')
            ->whereIn('trx_status', ['received', 'completed'])
            ->whereBetween('trx_datetime', [$period->startOfMonth(), $period->endOfMonth()])
            ->orderBy('trx_id')
            ->get();
        $sellerIds = $transactions->pluck('trx_seller_id')->unique()->values();
        $sellers = Member::query()->whereIn('member_id', $sellerIds)->get()->keyBy('member_id');
        $bankAccounts = MemberBankAccount::query()
            ->whereIn('member_bank_account_member_id', $sellerIds)
            ->where('member_bank_account_is_active', 1)
            ->orderByDesc('member_bank_account_is_default')
            ->orderBy('member_bank_account_id')
            ->get()
            ->groupBy('member_bank_account_member_id')
            ->map(fn (Collection $accounts): MemberBankAccount => $accounts->first());
        $percentage = $this->integerConfig('partnership.spread_payment_percentage', 1);
        $created = 0;

        foreach ($transactions as $transaction) {
            $seller = $sellers->get($transaction->trx_seller_id);
            if (! $seller) {
                continue;
            }

            $amount = (int) round((int) $transaction->trx_grand_total_price * $percentage / 100);
            if ($amount <= 0) {
                continue;
            }

            $bankAccount = $bankAccounts->get($seller->getKey());
            $spreadPayment = TrxSpreadPayment::query()->firstOrCreate(
                [
                    'trx_spread_payment_trx_id' => $transaction->getKey(),
                    'trx_spread_payment_upline_id' => $seller->getKey(),
                ],
                [
                    'trx_spread_payment_member_id' => $transaction->trx_buyer_id,
                    'trx_spread_payment_bank_id' => (int) ($bankAccount?->member_bank_account_bank_id ?? 0),
                    'trx_spread_payment_account_name' => $bankAccount?->member_bank_account_name ?? '',
                    'trx_spread_payment_account_number' => $bankAccount?->member_bank_account_number ?? '',
                    'trx_spread_payment_percentage' => $percentage,
                    'trx_spread_payment_amount' => $amount,
                    'trx_spread_payment_status' => 'pending',
                    'trx_spread_payment_approved_by' => 0,
                    'trx_spread_payment_paid_by' => 0,
                    'trx_spread_payment_note' => 'Dibentuk otomatis saat tutup buku bulanan.',
                    'trx_spread_payment_created_datetime' => now(),
                ],
            );
            if ($spreadPayment->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /** @param Collection<int, Member> $members */
    private function createUpgradeQualifications(Collection $members, CarbonImmutable $toPeriod): int
    {
        $fromPeriod = $toPeriod->subMonths(2)->startOfMonth();
        $customerCounts = $this->customerCounts($fromPeriod, $toPeriod);
        $resellerLevelId = (int) MemberLevel::query()
            ->where('member_level_code', 'RSL')
            ->value('member_level_id');
        $recruitedResellers = Member::query()
            ->where('member_member_level_id', $resellerLevelId)
            ->whereBetween('member_join_datetime', [$fromPeriod, $toPeriod->endOfMonth()])
            ->selectRaw('member_parent_member_id, COUNT(*) AS reseller_count')
            ->groupBy('member_parent_member_id')
            ->pluck('reseller_count', 'member_parent_member_id');
        $targetLevels = MemberLevel::query()
            ->whereIn('member_level_code', ['AGT', 'DST'])
            ->get()
            ->keyBy('member_level_code');
        $created = 0;

        foreach ($members as $member) {
            $levelCode = (string) $member->level?->member_level_code;
            $toLevelCode = match ($levelCode) {
                'RSL' => 'AGT',
                'AGT' => 'DST',
                default => null,
            };
            if ($toLevelCode === null || ! $targetLevels->has($toLevelCode)) {
                continue;
            }

            $achievements = MemberAchievement::query()
                ->where('member_achievement_member_id', $member->getKey())
                ->where(function ($query) use ($fromPeriod, $toPeriod): void {
                    $query->whereRaw(
                        '(member_achievement_year * 100 + member_achievement_month) BETWEEN ? AND ?',
                        [(int) $fromPeriod->format('Ym'), (int) $toPeriod->format('Ym')],
                    );
                })
                ->orderBy('member_achievement_year')
                ->orderBy('member_achievement_month')
                ->get();
            if ($achievements->count() !== 3
                || ! $this->meetsUpgradeRequirements(
                    $levelCode,
                    $achievements,
                    (int) ($customerCounts->get($member->getKey()) ?? 0),
                    (int) ($recruitedResellers->get($member->getKey()) ?? 0),
                )) {
                continue;
            }

            $hasPendingQualification = MemberUpgradeQualified::query()
                ->where('member_upgrade_qualified_member_id', $member->getKey())
                ->whereIn('member_upgrade_qualified_status', ['requested', 'scheduled'])
                ->exists();
            if ($hasPendingQualification) {
                continue;
            }

            $qualification = MemberUpgradeQualified::query()->firstOrCreate(
                [
                    'member_upgrade_qualified_member_id' => $member->getKey(),
                    'member_upgrade_qualified_from_level_id' => $member->member_member_level_id,
                    'member_upgrade_qualified_to_level_id' => $targetLevels->get($toLevelCode)->getKey(),
                    'member_upgrade_qualified_from_year_month' => (int) $fromPeriod->format('ym'),
                    'member_upgrade_qualified_to_year_month' => (int) $toPeriod->format('ym'),
                ],
                [
                    'member_upgrade_qualified_status' => 'requested',
                    'member_upgrade_qualified_admin_id' => 0,
                    'member_upgrade_qualified_created_datetime' => now(),
                ],
            );
            if ($qualification->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /** @param Collection<int, MemberAchievement> $achievements */
    private function meetsUpgradeRequirements(
        string $levelCode,
        Collection $achievements,
        int $customerCount,
        int $recruitedResellerCount,
    ): bool {
        $points = $achievements->pluck('member_achievement_point')->map(fn (mixed $value): int => (int) $value);

        return match ($levelCode) {
            'RSL' => $points->every(fn (int $point): bool => $point >= $this->integerConfig(
                'upgrade.upgrade_reseller_min_qty_per_month',
                50,
            ))
                && $points->sum() >= $this->integerConfig('upgrade.upgrade_reseller_min_total_qty', 150)
                && $customerCount >= $this->integerConfig('upgrade.upgrade_reseller_min_customer_count', 3),
            'AGT' => $points->every(fn (int $point): bool => $point >= $this->integerConfig(
                'upgrade.upgrade_agent_min_qty_per_month',
                270,
            ))
                && $points->sum() >= $this->integerConfig('upgrade.upgrade_agent_min_total_qty', 800)
                && $customerCount >= $this->integerConfig('upgrade.upgrade_agent_min_customer_count', 10)
                && $recruitedResellerCount >= $this->integerConfig(
                    'upgrade.upgrade_agent_min_reseller_count',
                    3,
                ),
            default => false,
        };
    }

    private function integerConfig(string $key, int $default): int
    {
        return (int) BusinessConfig::get($key, $default);
    }

    private function levelName(string $levelCode): string
    {
        return match ($levelCode) {
            'DST' => 'distributor',
            'AGT' => 'agent',
            'RSL' => 'reseller',
            default => strtolower($levelCode),
        };
    }
}
