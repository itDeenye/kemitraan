<?php

namespace App\Services\Partnership;

use App\Models\Member;
use App\Models\RewardStockist;
use App\Models\Trx;
use App\Services\Inventory\StockAllocationService;
use App\Services\Purchase\PreorderChainService;
use App\Services\Reward\MemberPointService;
use Illuminate\Database\Eloquent\Builder;

class MemberTransactionCancellationService
{
    public const CANCELLABLE_STATUSES = [
        'waiting_stock_screening',
        'waiting_payment',
        'waiting_payment_approval',
        'processing',
    ];

    private const TERMINAL_STATUSES = ['completed', 'cancelled', 'rejected'];

    public function __construct(
        private readonly StockAllocationService $stockAllocationService,
        private readonly PreorderChainService $preorderChainService,
        private readonly MemberPointService $memberPointService,
    ) {}

    /** @return array{active: int, cancellable: int, blocking: int} */
    public function summary(Member $member): array
    {
        $activeQuery = $this->transactionsFor($member)
            ->whereNotIn('trx_status', self::TERMINAL_STATUSES);
        $cancellable = (clone $activeQuery)
            ->whereIn('trx_status', self::CANCELLABLE_STATUSES)
            ->count();
        $active = (clone $activeQuery)->count();

        return [
            'active' => $active,
            'cancellable' => $cancellable,
            'blocking' => $active - $cancellable,
        ];
    }

    /** @return array{cancelled_transactions: int, released_stock_orders: int} */
    public function cancelCancellableTransactions(Member $member): array
    {
        $transactions = $this->transactionsFor($member)
            ->with('details')
            ->whereIn('trx_status', self::CANCELLABLE_STATUSES)
            ->orderBy('trx_id')
            ->lockForUpdate()
            ->get();
        $cancelledIds = [];
        $releasedStockOrders = 0;

        foreach ($transactions as $transaction) {
            if (isset($cancelledIds[$transaction->getKey()])) {
                continue;
            }

            $lockedTransaction = Trx::query()
                ->with('details')
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->first();
            if (! $lockedTransaction
                || ! in_array($lockedTransaction->trx_status, self::CANCELLABLE_STATUSES, true)) {
                continue;
            }

            if ($lockedTransaction->trx_is_preorder) {
                $cancelledChain = $this->preorderChainService->cancelFromOrder($lockedTransaction);
                foreach ($cancelledChain as $cancelledOrder) {
                    $cancelledIds[$cancelledOrder->getKey()] = true;
                }
                $releasedStockOrders++;

                continue;
            }

            $this->stockAllocationService->release($lockedTransaction);
            $this->memberPointService->reverseForCancelledTransaction($lockedTransaction);
            RewardStockist::query()
                ->where('reward_stockist_used_trx_id', $lockedTransaction->getKey())
                ->update([
                    'reward_stockist_used_value' => 0,
                    'reward_stockist_used_trx_id' => 0,
                ]);
            $lockedTransaction->update([
                'trx_status' => 'cancelled',
                'trx_status_datetime' => now(),
            ]);
            $cancelledIds[$lockedTransaction->getKey()] = true;
            $releasedStockOrders++;
        }

        return [
            'cancelled_transactions' => count($cancelledIds),
            'released_stock_orders' => $releasedStockOrders,
        ];
    }

    private function transactionsFor(Member $member): Builder
    {
        return Trx::query()->where(function (Builder $query) use ($member): void {
            $query->where(function (Builder $partyQuery) use ($member): void {
                $partyQuery->where('trx_buyer_id', $member->getKey())
                    ->whereIn('trx_buyer_type', ['distributor', 'agent', 'reseller']);
            })->orWhere(function (Builder $partyQuery) use ($member): void {
                $partyQuery->where('trx_seller_id', $member->getKey())
                    ->whereIn('trx_seller_type', ['distributor', 'agent', 'reseller']);
            });
        });
    }
}
