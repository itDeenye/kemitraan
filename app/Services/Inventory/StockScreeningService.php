<?php

namespace App\Services\Inventory;

use App\Exceptions\ProcessException;
use App\Mail\StockScreeningApprovedMail;
use App\Models\RewardStockist;
use App\Models\Trx;
use App\Services\Notification\MemberNotificationService;
use App\Services\Purchase\PreorderChainService;
use App\Services\Purchase\PreorderPaymentSequenceGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class StockScreeningService
{
    public function __construct(
        private readonly StockAllocationService $stockAllocationService,
        private readonly MemberNotificationService $memberNotificationService,
        private readonly PreorderPaymentSequenceGuard $preorderPaymentSequenceGuard,
        private readonly PreorderChainService $preorderChainService,
    ) {}

    public function requiresScreening(Trx $trx): bool
    {
        return $trx->trx_seller_type === 'warehouse'
            && $trx->trx_buyer_type === 'distributor';
    }

    public function approve(
        Trx $trx,
    ): Trx {
        $trxId = DB::transaction(function () use ($trx): int {
            $lockedTrx = $this->lockPending($trx);
            $this->preorderPaymentSequenceGuard->ensurePreviousPaymentsApproved($lockedTrx);

            $lockedTrx->update([
                'trx_status' => 'waiting_payment',
                'trx_status_datetime' => now(),
            ]);

            return (int) $lockedTrx->getKey();
        });

        $trx = Trx::query()->with('buyer')->findOrFail($trxId);
        $this->memberNotificationService->transactionBuyer(
            $trx,
            'Screening Stok Disetujui',
            "Screening stok pesanan {$trx->trx_code} telah disetujui. Silakan unggah bukti pembayaran.",
            'stock_screening',
        );
        $email = trim((string) $trx->buyer?->member_email);
        if ($email !== '') {
            try {
                Mail::to($email)->send(new StockScreeningApprovedMail($trx));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $trx;
    }

    public function reject(
        Trx $trx,
        ?string $note,
    ): Trx {
        $trxId = DB::transaction(function () use ($trx): int {
            $lockedTrx = $this->lockPending($trx);
            $this->preorderPaymentSequenceGuard->ensurePreviousPaymentsApproved($lockedTrx);

            if ($lockedTrx->trx_is_preorder) {
                $this->preorderChainService->cancelFromTerminalSeller($lockedTrx);
            } else {
                $this->stockAllocationService->release($lockedTrx);
                RewardStockist::query()
                    ->where('reward_stockist_used_trx_id', $lockedTrx->getKey())
                    ->update([
                        'reward_stockist_used_value' => 0,
                        'reward_stockist_used_trx_id' => 0,
                    ]);
                $lockedTrx->update([
                    'trx_status' => 'cancelled',
                    'trx_status_datetime' => now(),
                ]);
            }

            return (int) $lockedTrx->getKey();
        });

        $trx = Trx::query()->findOrFail($trxId);
        $reason = filled($note) ? " Alasan: {$note}" : '';
        $cancellationMessage = $trx->trx_is_preorder
            ? ' Seluruh rangkaian PO dibatalkan dan alokasi stok dilepas.'
            : ' Pesanan dibatalkan dan alokasi stok dilepas.';
        $this->memberNotificationService->transactionBuyer(
            $trx,
            'Screening Stok Ditolak',
            "Screening stok pesanan {$trx->trx_code} ditolak.{$cancellationMessage}{$reason}",
            'stock_screening',
        );

        return $trx;
    }

    private function lockPending(Trx $trx): Trx
    {
        $lockedTrx = Trx::query()->whereKey($trx->getKey())->lockForUpdate()->firstOrFail();
        $this->assertScreeningTransaction($lockedTrx);

        if ($lockedTrx->trx_status !== 'waiting_stock_screening') {
            throw new ProcessException('Screening stok transaksi ini sudah pernah diproses.');
        }

        return $lockedTrx;
    }

    private function assertScreeningTransaction(Trx $trx): void
    {
        if (! $this->requiresScreening($trx)) {
            throw new ProcessException(
                'Screening stok hanya berlaku untuk transaksi distributor ke perusahaan.',
                404,
            );
        }
    }
}
