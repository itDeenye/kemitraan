<?php

namespace App\Console\Commands;

use App\Services\Partnership\MemberLevelChangeService;
use Illuminate\Console\Command;

class ApplyScheduledMemberChanges extends Command
{
    protected $signature = 'members:apply-upgrade-downgrade';

    protected $description = 'Menerapkan upgrade, downgrade, dan perpindahan jaringan yang sudah efektif';

    public function handle(MemberLevelChangeService $levelChangeService): int
    {
        $result = $levelChangeService->applyScheduledChanges();

        $this->info("Perpindahan diterapkan: {$result['applied']}.");
        if ($result['skipped'] > 0) {
            $this->warn("Perpindahan dilewati karena konflik data: {$result['skipped']}.");
        }

        return self::SUCCESS;
    }
}
