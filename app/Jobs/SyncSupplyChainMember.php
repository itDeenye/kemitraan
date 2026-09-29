<?php

namespace App\Jobs;

use App\Models\Member;
use App\Services\Integration\SupplyChainSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;

class SyncSupplyChainMember implements ShouldBeUnique, ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public int $timeout = 120;

    public function __construct(public readonly int $memberId) {}

    public function handle(SupplyChainSyncService $service): void
    {
        $member = Member::query()->find($this->memberId);
        if ($member) {
            $service->syncMember($member);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->memberId;
    }
}
