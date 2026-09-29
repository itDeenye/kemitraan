<?php

namespace App\Services\Reward;

use App\Models\MemberPointTransaction;
use App\Models\RewardPointAnnual;
use App\Models\RewardPointAnnualLog;
use App\Models\Trx;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MemberPointService
{
    /**
     * Catat poin pembelian reguler segera setelah disetujui. Khusus PO,
     * seluruh buyer baru menerima poin setelah semua pembayaran rantai disetujui.
     */
    public function recordForApprovedPayment(Trx $transaction): void
    {
        if (! $this->isEligiblePurchase($transaction)) {
            return;
        }

        if (! $transaction->trx_is_preorder) {
            $this->recordTransaction($transaction);

            return;
        }

        $chain = $this->preorderChain($transaction);
        if ($chain->isEmpty() || $chain->contains(
            fn (Trx $order): bool => $order->paymentTransfer?->payment_transfer_approval_status !== 'approved',
        )) {
            return;
        }

        foreach ($chain as $order) {
            if ($this->isEligiblePurchase($order)) {
                $this->recordTransaction($order);
            }
        }
    }

    private function recordTransaction(Trx $transaction): void
    {
        $quantity = (int) $transaction->details()->sum('trx_detail_qty');
        if ($quantity <= 0) {
            return;
        }

        $approvedAt = Carbon::now();
        $ledger = MemberPointTransaction::query()
            ->where('member_point_transaction_trx_id', $transaction->getKey())
            ->lockForUpdate()
            ->first();

        if ($ledger) {
            return;
        }

        MemberPointTransaction::query()->create([
            'member_point_transaction_member_id' => $transaction->trx_buyer_id,
            'member_point_transaction_trx_id' => $transaction->getKey(),
            'member_point_transaction_quantity' => $quantity,
            'member_point_transaction_year' => $approvedAt->year,
            'member_point_transaction_month' => $approvedAt->month,
            'member_point_transaction_approved_datetime' => $approvedAt,
        ]);

        RewardPointAnnualLog::query()->create([
            'reward_point_annual_log_member_id' => $transaction->trx_buyer_id,
            'reward_point_annual_log_trx_id' => $transaction->getKey(),
            'reward_point_annual_log_type' => 'in',
            'reward_point_annual_log_points' => $quantity,
            'reward_point_annual_log_note' => "Poin dari transaksi {$transaction->trx_code}",
            'reward_point_annual_log_datetime' => $approvedAt,
        ]);

        $annualReward = RewardPointAnnual::query()
            ->where('reward_point_annual_member_id', $transaction->trx_buyer_id)
            ->where('reward_point_annual_year', $approvedAt->year)
            ->lockForUpdate()
            ->first();

        if ($annualReward) {
            $annualReward->increment('reward_point_annual_total_points', $quantity, [
                'reward_point_annual_last_updated_datetime' => $approvedAt,
            ]);
        } else {
            RewardPointAnnual::query()->create([
                'reward_point_annual_member_id' => $transaction->trx_buyer_id,
                'reward_point_annual_year' => $approvedAt->year,
                'reward_point_annual_total_points' => $quantity,
                'reward_point_annual_last_updated_datetime' => $approvedAt,
            ]);
        }
    }

    private function isEligiblePurchase(Trx $transaction): bool
    {
        return $transaction->trx_type === 'stock'
            && in_array($transaction->trx_buyer_type, ['distributor', 'agent', 'reseller'], true);
    }

    /** @return Collection<int, Trx> */
    private function preorderChain(Trx $transaction): Collection
    {
        $root = Trx::query()
            ->with('paymentTransfer')
            ->whereKey($transaction->getKey())
            ->lockForUpdate()
            ->first();
        if (! $root) {
            return collect();
        }

        $visited = [];
        while ((int) $root->trx_parent_trx_id > 0 && ! isset($visited[$root->getKey()])) {
            $visited[$root->getKey()] = true;
            $parent = Trx::query()
                ->with('paymentTransfer')
                ->whereKey($root->trx_parent_trx_id)
                ->lockForUpdate()
                ->first();
            if (! $parent) {
                return collect();
            }
            $root = $parent;
        }

        $orders = collect();
        $frontier = collect([$root]);
        $visited = [];

        while ($frontier->isNotEmpty()) {
            foreach ($frontier as $order) {
                if (isset($visited[$order->getKey()])) {
                    continue;
                }
                $visited[$order->getKey()] = true;
                $orders->push($order);
            }

            $frontier = Trx::query()
                ->with('paymentTransfer')
                ->whereIn('trx_parent_trx_id', $frontier->pluck('trx_id')->all())
                ->where('trx_is_preorder', true)
                ->orderBy('trx_id')
                ->lockForUpdate()
                ->get()
                ->reject(fn (Trx $order): bool => isset($visited[$order->getKey()]))
                ->values();
        }

        return $orders;
    }

    public function reverseForCancelledTransaction(Trx $transaction): void
    {
        $ledger = MemberPointTransaction::query()
            ->where('member_point_transaction_trx_id', $transaction->getKey())
            ->lockForUpdate()
            ->first();
        if (! $ledger) {
            return;
        }

        $quantity = (int) $ledger->member_point_transaction_quantity;
        $year = (int) $ledger->member_point_transaction_year;
        $reversedAt = Carbon::now();

        RewardPointAnnualLog::query()->create([
            'reward_point_annual_log_member_id' => $ledger->member_point_transaction_member_id,
            'reward_point_annual_log_trx_id' => $transaction->getKey(),
            'reward_point_annual_log_type' => 'out',
            'reward_point_annual_log_points' => $quantity,
            'reward_point_annual_log_note' => "Poin dibatalkan karena transaksi {$transaction->trx_code} dibatalkan",
            'reward_point_annual_log_datetime' => $reversedAt,
        ]);

        $annualReward = RewardPointAnnual::query()
            ->where('reward_point_annual_member_id', $ledger->member_point_transaction_member_id)
            ->where('reward_point_annual_year', $year)
            ->lockForUpdate()
            ->first();
        if ($annualReward) {
            $annualReward->update([
                'reward_point_annual_total_points' => max(
                    0,
                    (int) $annualReward->reward_point_annual_total_points - $quantity,
                ),
                'reward_point_annual_last_updated_datetime' => $reversedAt,
            ]);
        }

        $ledger->delete();
    }
}
