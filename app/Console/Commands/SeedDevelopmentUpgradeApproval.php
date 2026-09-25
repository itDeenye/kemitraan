<?php

namespace App\Console\Commands;

use Database\Seeders\DevelopmentUpgradeApprovalSeeder;
use Illuminate\Console\Command;
use Throwable;

class SeedDevelopmentUpgradeApproval extends Command
{
    protected $signature = 'development:seed-upgrade-approval
                            {member_id : ID Reseller yang akan disiapkan untuk approval upgrade}';

    protected $description = 'Menyiapkan skenario approval upgrade Reseller menjadi Agent pada environment development';

    public function handle(DevelopmentUpgradeApprovalSeeder $seeder): int
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            $this->error('Command ini hanya dapat dijalankan pada environment local/development.');

            return self::FAILURE;
        }

        $memberId = filter_var($this->argument('member_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($memberId === false) {
            $this->error('member_id harus berupa angka lebih dari 0.');

            return self::INVALID;
        }

        try {
            $seeder->setCommand($this)->run($memberId);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
