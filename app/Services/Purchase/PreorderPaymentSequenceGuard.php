<?php

namespace App\Services\Purchase;

use App\Exceptions\ProcessException;
use App\Models\Trx;
use App\Models\TrxPaymentTransfer;

class PreorderPaymentSequenceGuard
{
    public function previousPaymentsApproved(Trx $trx): bool
    {
        if (! $trx->trx_is_preorder) {
            return true;
        }

        $parentId = (int) $trx->trx_parent_trx_id;
        $visitedIds = [];
        $loadedTransactions = $trx->relationLoaded('preorderChain')
            ? $trx->preorderChain->keyBy(fn (Trx $transaction): int => (int) $transaction->getKey())
            : null;

        while ($parentId > 0) {
            if (isset($visitedIds[$parentId])) {
                return false;
            }
            $visitedIds[$parentId] = true;

            $parent = $loadedTransactions?->get($parentId)
                ?? Trx::query()->with('paymentTransfer')->find($parentId);
            if (! $parent
                || $parent->paymentTransfer?->payment_transfer_approval_status !== 'approved') {
                return false;
            }

            $parentId = (int) $parent->trx_parent_trx_id;
        }

        return true;
    }

    public function ensurePreviousPaymentsApproved(Trx $trx): void
    {
        if (! $trx->trx_is_preorder) {
            return;
        }

        $parentId = (int) $trx->trx_parent_trx_id;
        $visitedIds = [];

        while ($parentId > 0) {
            if (isset($visitedIds[$parentId])) {
                throw new ProcessException('Rantai transaksi PO tidak valid.');
            }
            $visitedIds[$parentId] = true;

            $parent = Trx::query()
                ->whereKey($parentId)
                ->lockForUpdate()
                ->first();
            if (! $parent) {
                throw new ProcessException('Transaksi PO tahap sebelumnya tidak ditemukan.');
            }

            $paymentStatus = TrxPaymentTransfer::query()
                ->where('payment_transfer_trx_id', $parent->getKey())
                ->lockForUpdate()
                ->value('payment_transfer_approval_status');
            if ($paymentStatus !== 'approved') {
                throw new ProcessException('Pembayaran PO tahap sebelumnya harus disetujui terlebih dahulu.');
            }

            $parentId = (int) $parent->trx_parent_trx_id;
        }
    }
}
