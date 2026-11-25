<?php

namespace App\Services\Purchase;

use App\Models\RewardStockist;
use App\Models\Trx;
use App\Models\TrxPaymentTransfer;
use App\Services\Inventory\StockAllocationService;
use App\Services\Notification\PartnershipEmailService;
use Illuminate\Support\Facades\DB;

class PaymentUploadExpiryService
{
    public function __construct(
        private readonly StockAllocationService $stockAllocationService,
        private readonly PartnershipEmailService $partnershipEmailService,
    ) {}

    public function expire(int $transactionId): bool
    {
        return DB::transaction(function () use ($transactionId): bool {
            $trx = Trx::query()->whereKey($transactionId)->lockForUpdate()->first();
            if (! $trx || ! $trx->paymentUploadDeadlinePassed()) {
                return false;
            }
            $payment = TrxPaymentTransfer::query()
                ->where('payment_transfer_trx_id', $trx->getKey())->lockForUpdate()->first();
            if ($payment && in_array($payment->payment_transfer_approval_status, ['submitted', 'approved'], true)) {
                return false;
            }

            $this->stockAllocationService->release($trx);
            RewardStockist::query()->where('reward_stockist_used_trx_id', $trx->getKey())
                ->update(['reward_stockist_used_value' => 0, 'reward_stockist_used_trx_id' => 0]);
            $trx->update(['trx_status' => 'expired', 'trx_status_datetime' => now()]);
            DB::afterCommit(fn () => $this->partnershipEmailService->sendPaymentExpired($trx));

            return true;
        }, 3);
    }
}
