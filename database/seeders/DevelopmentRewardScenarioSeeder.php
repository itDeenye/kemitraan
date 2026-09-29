<?php

namespace Database\Seeders;

use App\Models\BankCompany;
use App\Models\Member;
use App\Models\Product;
use App\Models\RewardPointMonthly;
use App\Models\RewardStockist;
use App\Models\SiteAdministrator;
use App\Models\Stockist;
use App\Models\Trx;
use App\Models\TrxDetail;
use App\Models\TrxPaymentTransfer;
use App\Models\TrxSpreadPayment;
use App\Models\Warehouse;
use App\Services\Partnership\MemberCodeService;
use App\Support\BusinessConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class DevelopmentRewardScenarioSeeder extends Seeder
{
    private const RECEIPT = '/images/development/reward-receipt.svg';

    /**
     * Fixture untuk mencoba halaman reward, bukan menjalankan closing seluruh mitra.
     * Transaksi selesai menjadi sumber pembelian historis seperti fallback pada closing.
     * Tidak memanggil checkout, mutasi stok, notifikasi, maupun gateway pembayaran.
     *
     * @return list<array{string, string}>
     */
    public function run(?int $memberId = null, string $only = 'all', ?string $period = null): array
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            throw new LogicException('Seeder ini hanya untuk environment local/development.');
        }
        if (! $memberId || $memberId < 1) {
            throw new InvalidArgumentException('Gunakan development:seed-rewards {member_id}.');
        }
        if (! in_array($only, ['all', 'monthly', 'stockist', 'sharing', 'history'], true)) {
            throw new InvalidArgumentException('--only harus all, monthly, stockist, sharing, atau history.');
        }
        if ($period !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new InvalidArgumentException('--period harus berformat YYYY-MM.');
        }
        $month = $period === null
            ? CarbonImmutable::now()->startOfMonth()->subMonth()
            : CarbonImmutable::createFromFormat('!Y-m', $period);
        if ($month->year < 2000 || $month->greaterThanOrEqualTo(CarbonImmutable::now()->startOfMonth())) {
            throw new InvalidArgumentException('Gunakan periode bulan yang sudah selesai (tahun 2000 atau setelahnya).');
        }

        return DB::transaction(function () use ($memberId, $only, $month): array {
            $member = Member::query()->with(['level', 'parent.level'])->lockForUpdate()->find($memberId);
            if (! $member || $member->member_status !== 1 || ! $member->level?->member_level_is_active) {
                throw new LogicException('ID mitra tidak ditemukan atau mitra/level tidak aktif.');
            }
            $type = $this->memberType($member);
            if ($type !== 'distributor' && in_array($only, ['stockist', 'sharing', 'history'], true)) {
                throw new LogicException('Reward Stokis dan Sharing Profit khusus Distributor. Gunakan --only=monthly untuk Agent/Reseller.');
            }

            $rows = [['Mitra', "{$member->member_code} - {$member->member_name}"], ['Periode', $month->format('Y-m')]];
            if (in_array($only, ['all', 'monthly', 'stockist'], true)) {
                $this->monthlyAndStockist($member, $month, $only, $rows);
            }
            if ($type === 'distributor' && in_array($only, ['all', 'sharing', 'history'], true)) {
                $this->sharing($member, $month, $only, $rows);
            } elseif ($type !== 'distributor' && $only === 'all') {
                $rows[] = ['Stokis / Sharing Profit', 'Dilewati: hanya untuk Distributor.'];
            }

            return $rows;
        });
    }

    /** @param list<array{string, string}> $rows */
    private function monthlyAndStockist(Member $member, CarbonImmutable $month, string $only, array &$rows): void
    {
        $monthly = RewardPointMonthly::query()->where([
            'reward_point_monthly_member_id' => $member->getKey(),
            'reward_point_monthly_year' => $month->year,
            'reward_point_monthly_month' => $month->month,
        ])->first();
        $stockistReward = RewardStockist::query()->where([
            'reward_stockist_member_id' => $member->getKey(),
            'reward_stockist_year' => $month->year,
            'reward_stockist_month' => $month->month,
        ])->first();
        $wantMonthly = in_array($only, ['all', 'monthly'], true);
        $wantStockist = $this->memberType($member) === 'distributor' && in_array($only, ['all', 'stockist'], true);
        $purchase = null;

        if (($wantMonthly && ! $monthly) || ($wantStockist && ! $stockistReward)) {
            $minimum = (int) BusinessConfig::get('reward_monthly.reward_monthly_min_qty_'.$this->memberType($member), match ($this->memberType($member)) {
                'distributor' => 50,
                'agent' => 30,
                'reseller' => 20,
            });
            $quantity = max(1, $minimum);
            if ($this->memberType($member) === 'distributor') {
                $quantity = max($quantity, (int) ceil((int) BusinessConfig::get('reward_stockist.reward_stockist_min_amount', 50_000_000) / 100_000));
            }
            $purchase = $this->purchase($member, $member, $month, 'purchase', $quantity);
            $rows[] = ['Pembelian dummy', $purchase->trx_code];
        }

        if ($wantMonthly) {
            if (! $monthly) {
                $pointValue = (int) $member->level->member_level_point_value;
                if ($pointValue <= 0) {
                    throw new LogicException('Nilai poin level belum diatur. Isi nilai poin level sebelum membuat contoh Reward Bulanan.');
                }
                $quantity = (int) $purchase->details()->sum('trx_detail_qty');
                $monthly = RewardPointMonthly::query()->create([
                    'reward_point_monthly_member_id' => $member->getKey(),
                    'reward_point_monthly_member_level_id' => $member->member_member_level_id,
                    'reward_point_monthly_upline_id' => (int) $member->member_parent_member_id,
                    'reward_point_monthly_upline_level_id' => (int) ($member->parent?->member_member_level_id ?? 0),
                    'reward_point_monthly_year' => $month->year,
                    'reward_point_monthly_month' => $month->month,
                    'reward_point_monthly_total_qty' => $quantity,
                    'reward_point_monthly_bonus_value' => $quantity * $pointValue,
                    'reward_point_monthly_admin_id' => 0,
                    'reward_point_monthly_is_processed' => 0,
                ]);
            }
            $payer = $monthly->reward_point_monthly_upline_id ?: 'Perusahaan';
            $rows[] = ['Reward Bulanan', "ID {$monthly->getKey()} | pembayar: {$payer} | data/status existing dipertahankan"];
        }

        if ($wantStockist) {
            if (! $stockistReward) {
                $this->ensureStockist($member, $rows);
                $basisPoints = (int) BusinessConfig::get('reward_stockist.reward_stockist_percentage_basis_points', 250);
                if ($basisPoints <= 0) {
                    throw new LogicException('Persentase Reward Stokis belum diatur.');
                }
                $stockistReward = RewardStockist::query()->create([
                    'reward_stockist_member_id' => $member->getKey(),
                    'reward_stockist_year' => $month->year,
                    'reward_stockist_month' => $month->month,
                    'reward_stockist_total_trx_amount' => $purchase->trx_grand_total_price,
                    'reward_stockist_bonus_value' => intdiv((int) $purchase->trx_grand_total_price * $basisPoints, 10_000),
                    'reward_stockist_used_value' => 0,
                    'reward_stockist_used_trx_id' => 0,
                    'reward_stockist_expiry_date' => $month->addMonth()->endOfMonth(),
                    'reward_stockist_created_datetime' => $month->endOfMonth(),
                ]);
            }
            $rows[] = ['Reward Stokis', "Voucher ID {$stockistReward->getKey()} | berlaku sampai {$stockistReward->reward_stockist_expiry_date->format('Y-m-d')}"];
        }
    }

    /** @param list<array{string, string}> $rows */
    private function ensureStockist(Member $member, array &$rows): void
    {
        $stockist = Stockist::query()->firstOrCreate(['stockist_member_id' => $member->getKey()], [
            'stockist_name' => 'DUMMY Stokis '.$member->getKey(),
            'stockist_email' => 'dummy-stockist-'.$member->getKey().'@example.test',
            'stockist_note' => 'DUMMY untuk simulasi reward development.',
            'stockist_is_active' => 1,
            'stockist_is_deleted' => 0,
            'stockist_input_datetime' => now(),
        ]);
        if (! $stockist->stockist_is_active || $stockist->stockist_is_deleted) {
            throw new LogicException('Stokis mitra ini tidak aktif. Status existing tidak diubah oleh seeder.');
        }
        if ($stockist->wasRecentlyCreated) {
            $member->update(['member_stockist_id' => $stockist->getKey()]);
        }
        $rows[] = ['Stokis', "ID {$stockist->getKey()} (dipakai untuk contoh voucher)"];
    }

    /** @param list<array{string, string}> $rows */
    private function sharing(Member $member, CarbonImmutable $month, string $only, array &$rows): void
    {
        $bank = BankCompany::query()->where('bank_company_type', 'spread_payment')
            ->where('bank_company_bank_is_active', 1)->orderBy('bank_company_id')->first();
        if (! $bank) {
            throw new LogicException('Siapkan rekening perusahaan jenis spread_payment yang aktif terlebih dahulu.');
        }
        $buyer = $member->downlines()->with('level')->where('member_status', 1)
            ->where('member_member_level_id', $member->member_member_level_id)->orderBy('member_id')->first();
        if (! $buyer) {
            $buyer = Member::query()->create([
                'member_code' => app(MemberCodeService::class)->next($member->level, $member),
                'member_member_level_id' => $member->member_member_level_id,
                'member_parent_member_id' => $member->getKey(),
                'member_name' => 'DUMMY Distributor Downline '.$member->getKey(),
                'member_email' => 'dummy-downline-'.$member->getKey().'@example.test',
                'member_birth_date' => '1990-01-01',
                'member_join_datetime' => $month->startOfMonth(),
                'member_status' => 1,
            ]);
            $rows[] = ['Distributor downline', "Dibuat ID {$buyer->getKey()} / {$buyer->member_code} (dummy, tanpa akun login)"];
        } else {
            $rows[] = ['Distributor downline', "Memakai ID {$buyer->getKey()} / {$buyer->member_code}; jaringan tidak dipindahkan"];
        }

        foreach ($only === 'all' ? ['sharing', 'history'] : [$only] as $scenario) {
            $trx = $this->purchase($member, $buyer, $month, $scenario, 10);
            $percentage = (float) BusinessConfig::get('partnership.spread_payment_percentage', 1);
            $percentage = $percentage > 0 ? $percentage : 1.0;
            $paid = $scenario === 'history';
            $adminId = $this->administratorId();
            $spread = TrxSpreadPayment::query()->firstOrCreate([
                'trx_spread_payment_trx_id' => $trx->getKey(),
                'trx_spread_payment_upline_id' => $member->getKey(),
            ], [
                'trx_spread_payment_member_id' => $trx->trx_buyer_id,
                'trx_spread_payment_bank_id' => $bank->bank_company_bank_id,
                'trx_spread_payment_account_name' => $bank->bank_company_bank_acc_name,
                'trx_spread_payment_account_number' => $bank->bank_company_bank_acc_number,
                'trx_spread_payment_percentage' => $percentage,
                'trx_spread_payment_amount' => (int) ceil((int) $trx->trx_bill_amount * $percentage / 100),
                'trx_spread_payment_receipt_file' => self::RECEIPT,
                'trx_spread_payment_transfer_datetime' => $trx->trx_datetime,
                'trx_spread_payment_status' => $paid ? 'paid' : 'submitted',
                'trx_spread_payment_approved_by' => $paid ? $adminId : 0,
                'trx_spread_payment_approved_datetime' => $paid ? $trx->trx_datetime->copy()->addHour() : null,
                'trx_spread_payment_paid_by' => $paid ? $adminId : 0,
                'trx_spread_payment_paid_datetime' => $paid ? $trx->trx_datetime->copy()->addHours(2) : null,
                'trx_spread_payment_note' => 'DUMMY simulasi reward; tidak ada transfer uang sungguhan.',
                'trx_spread_payment_created_datetime' => $trx->trx_datetime,
            ]);
            $rows[] = [$paid ? 'Riwayat Sharing Profit' : 'Sharing Profit', "ID {$spread->getKey()} | {$spread->trx_spread_payment_status} | {$trx->trx_code}"];
        }
    }

    private function purchase(Member $target, Member $buyer, CarbonImmutable $month, string $scenario, int $quantity): Trx
    {
        $code = "DEV/RWD/{$target->getKey()}/{$month->format('Ym')}/{$scenario}";
        $existing = Trx::query()->where('trx_code', $code)->first();
        if ($existing) {
            return $existing;
        }
        $buyerType = $this->memberType($buyer);
        $seller = $buyerType === 'distributor'
            ? Warehouse::query()->where('warehouse_is_active', 1)->orderBy('warehouse_id')->first()
            : $buyer->parent;
        if (! $seller || ($seller instanceof Member && $seller->member_status !== 1)) {
            throw new LogicException('Gudang aktif / upline penjual belum tersedia. Siapkan data dasar terlebih dahulu.');
        }
        $adminId = $this->administratorId();
        $product = Product::query()->firstOrCreate(['product_code' => 'DUMMY-REWARD'], [
            'product_name' => 'DUMMY Produk Simulasi Reward',
            'product_description' => 'Khusus contoh transaksi reward; bukan stok fisik.',
            'product_customer_price' => 100_000,
            'product_is_publish' => 0,
            'product_is_active' => 0,
            'product_input_datetime' => now(),
        ]);
        $amount = $quantity * 100_000;
        $orderedAt = $month->addDays(14)->setTime(10, 0);
        $trx = Trx::query()->create([
            'trx_code' => $code,
            'trx_seller_type' => $seller instanceof Warehouse ? 'warehouse' : $this->memberType($seller),
            'trx_seller_id' => $seller->getKey(),
            'trx_buyer_type' => $buyerType,
            'trx_buyer_id' => $buyer->getKey(),
            'trx_type' => 'stock',
            'trx_total_price' => $amount,
            'trx_grand_total_price' => $amount,
            'trx_grand_total_nett_price' => $amount,
            'trx_bill_amount' => $amount,
            'trx_payment_method' => 'transfer',
            'trx_shipping_method' => 'pickup',
            'trx_status' => 'completed',
            'trx_status_datetime' => $orderedAt->addHours(3),
            'trx_datetime' => $orderedAt,
        ]);
        TrxDetail::query()->create([
            'trx_detail_trx_id' => $trx->getKey(),
            'trx_detail_product_id' => $product->getKey(),
            'trx_detail_product_code' => $product->product_code,
            'trx_detail_product_name' => $product->product_name,
            'trx_detail_product_price' => 100_000,
            'trx_detail_nett_price' => 100_000,
            'trx_detail_qty' => $quantity,
        ]);
        TrxPaymentTransfer::query()->create([
            'payment_transfer_trx_id' => $trx->getKey(),
            'payment_transfer_bill_amount' => $amount,
            'payment_transfer_amount' => $amount,
            'payment_transfer_account_name' => 'DUMMY - Bukan rekening pembayaran',
            'payment_transfer_account_number' => '0000000000',
            'payment_transfer_receipt_file' => self::RECEIPT,
            'payment_transfer_datetime' => $orderedAt,
            'payment_transfer_approval_status' => 'approved',
            'payment_transfer_approval_admin_id' => $adminId,
            'payment_transfer_approval_datetime' => $orderedAt->addHour(),
            'payment_transfer_note' => 'DUMMY untuk simulasi reward; stok tidak dimutasi.',
        ]);

        return $trx;
    }

    private function administratorId(): int
    {
        $id = SiteAdministrator::query()->where('administrator_is_active', 1)->orderBy('administrator_id')->value('administrator_id');
        if (! $id) {
            throw new LogicException('Administrator aktif belum tersedia. Jalankan seeder data dasar terlebih dahulu.');
        }

        return (int) $id;
    }

    private function memberType(Member $member): string
    {
        return match ($member->level?->member_level_code) {
            'DST' => 'distributor',
            'AGT' => 'agent',
            'RSL' => 'reseller',
            default => throw new LogicException('Level mitra tidak didukung untuk simulasi reward.'),
        };
    }
}
