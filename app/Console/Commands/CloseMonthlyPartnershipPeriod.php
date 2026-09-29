<?php

namespace App\Console\Commands;

use App\Services\Partnership\MonthlyPartnershipClosingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class CloseMonthlyPartnershipPeriod extends Command
{
    protected $signature = 'rewards:close-month {--period= : Periode yang ditutup dalam format YYYY-MM}';

    protected $description = 'Menutup periode kemitraan untuk pencapaian, reward, dan kualifikasi upgrade';

    public function handle(MonthlyPartnershipClosingService $closingService): int
    {
        try {
            $period = $this->option('period')
                ? CarbonImmutable::createFromFormat('!Y-m', (string) $this->option('period'))
                : CarbonImmutable::now()->subMonth()->startOfMonth();
        } catch (Throwable) {
            $this->error('Periode harus menggunakan format YYYY-MM.');

            return self::FAILURE;
        }

        if ($period->greaterThanOrEqualTo(CarbonImmutable::now()->startOfMonth())) {
            $this->error('Periode berjalan atau periode mendatang belum dapat ditutup.');

            return self::FAILURE;
        }

        $result = $closingService->close($period);
        $this->info("Periode {$result['period']} berhasil ditutup.");
        $this->line("Pencapaian: {$result['achievements']}.");
        $this->line("Reward bulanan: {$result['monthly_rewards']}.");
        $this->line("Reward stokis: {$result['stockist_rewards']}.");
        $this->line("Sharing profit: {$result['spread_payments']}.");
        $this->line("Kualifikasi upgrade baru: {$result['upgrade_qualifications']}.");

        return self::SUCCESS;
    }
}
