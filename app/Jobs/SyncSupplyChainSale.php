<?php

namespace App\Jobs;

use App\Models\Trx;
use App\Services\Integration\SupplyChainSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;

class SyncSupplyChainSale implements ShouldBeUnique, ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public int $timeout = 180;

    public function __construct(public readonly int $trxId) {}

    public function handle(SupplyChainSyncService $service): void
    {
        $trx = Trx::query()->find($this->trxId);
        if ($trx) {
            $service->syncSale($trx);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->trxId;
    }
}
