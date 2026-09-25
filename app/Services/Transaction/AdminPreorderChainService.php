<?php

namespace App\Services\Transaction;

use App\Models\Trx;
use App\Services\Purchase\PreorderChainService;

class AdminPreorderChainService
{
    public function __construct(private readonly PreorderChainService $preorderChainService) {}

    public function load(Trx $trx): Trx
    {
        return $this->preorderChainService->loadVisiblePurchaseChain($trx);
    }

    public function cancelAfterTerminalPaymentRejection(Trx $trx): bool
    {
        return $this->preorderChainService->cancelAfterTerminalPaymentRejection($trx);
    }
}
