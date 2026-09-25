<?php

namespace App\Console\Commands;

use Database\Seeders\DevelopmentRewardScenarioSeeder;
use Illuminate\Console\Command;
use Throwable;

class SeedDevelopmentRewards extends Command
{
    protected $signature = 'development:seed-rewards
                            {member_id : ID mitra penerima reward / upline penerima sharing profit}
                            {--only=all : all, monthly, stockist, sharing, atau history}
                            {--period= : Periode YYYY-MM; bawaan bulan sebelumnya}';

    protected $description = 'Membuat contoh reward dan transaksi DUMMY untuk satu mitra di development';

    public function handle(DevelopmentRewardScenarioSeeder $seeder): int
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            $this->error('Command ini hanya dapat dijalankan pada environment local/development.');

            return self::FAILURE;
        }

        $memberId = filter_var($this->argument('member_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($memberId === false) {
            $this->error('member_id harus berupa angka lebih dari 0.');

            return self::INVALID;
        }

        try {
            $rows = $seeder->run($memberId, (string) $this->option('only'), $this->option('period'));
            $this->warn('DATA DUMMY: bukan transfer sungguhan. Stok fisik, email, dan webhook tidak diproses.');
            $this->table(['Contoh', 'Hasil'], $rows);
            $this->info('Selesai. Data/status yang sudah ada dipertahankan saat command diulang.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
