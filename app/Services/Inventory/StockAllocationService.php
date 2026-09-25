<?php

namespace App\Services\Inventory;

use App\Exceptions\ProcessException;
use App\Models\Member;
use App\Models\MemberStock;
use App\Models\MemberStockLog;
use App\Models\Trx;
use App\Models\TrxDetail;
use App\Models\WarehouseStock;
use App\Models\WarehouseStockLog;

/**
 * Menangani stok seller yang dicadangkan saat checkout sampai dikirim atau dibatalkan.
 */
class StockAllocationService
{
    public function reserve(Trx $trx): void
    {
        $trx->loadMissing('details');

        foreach ($trx->details as $detail) {
            // A member seller in a PO is only forwarding the payment request
            // to its upline. It does not own/reserve stock for this leg.
            if ($trx->trx_is_preorder && $trx->trx_seller_type !== 'warehouse') {
                continue;
            }
            if ($trx->trx_is_preorder && $trx->trx_parent_trx_id !== 0) {
                $ancestor = Trx::query()->whereKey($trx->trx_parent_trx_id)->first();
                if ($ancestor && $ancestor->trx_seller_type !== 'warehouse') {
                    continue;
                }
            }
            $stock = $this->lockStock(
                $trx,
                $detail,
                createWarehouseStock: $trx->trx_seller_type === 'warehouse',
            );
            if (! $stock) {
                throw new ProcessException('Stok penjual untuk produk yang dipesan tidak tersedia.');
            }

            // Balance is already net of checkout reservations.
            $available = $this->balanceValue($trx, $stock);
            $quantity = (int) $detail->trx_detail_qty;
            if ($available < $quantity) {
                throw new ProcessException('Stok penjual tidak mencukupi untuk seluruh produk yang dipesan.');
            }

            $stock->update([
                $this->column($trx, 'balance') => $available - $quantity,
                $this->column($trx, 'transfer_out') => $this->transferOutValue($trx, $stock) + $quantity,
            ]);
            $this->logReservation($trx, $detail, $stock, $quantity);
        }

        // The buyer's complete quantity is also held as incoming stock. This
        // covers PO lines that are not yet available at the seller and keeps
        // them out of the buyer's available stock until goods receipt.
        if ($this->shouldReserveIncoming($trx)) {
            $this->reserveIncoming($trx);
        }
    }

    public function release(Trx $trx): void
    {
        $trx->loadMissing('details');

        if ($trx->trx_is_preorder && (int) $trx->trx_parent_trx_id !== 0) {
            $root = $this->rootPreorderOrder($trx);
            if ($root && $root->getKey() !== $trx->getKey()) {
                $this->release($root);

                return;
            }
        }

        if ($trx->trx_is_preorder
            && (int) $trx->trx_parent_trx_id === 0
            && $trx->trx_seller_type !== 'warehouse') {
            $terminal = $this->terminalReservationOrder($trx);
            if ($terminal) {
                $this->release($terminal);
            }
        }

        foreach ($trx->details as $detail) {
            // A member seller in a PO only forwards the payment request and
            // therefore has no seller reservation to release.
            if ($trx->trx_is_preorder && $trx->trx_seller_type !== 'warehouse') {
                continue;
            }
            $reserved = (int) $detail->trx_detail_qty;
            if ($reserved <= 0) {
                continue;
            }

            $stock = $this->lockStock($trx, $detail);
            if ($stock) {
                $transferOut = $this->transferOutValue($trx, $stock);
                $released = min($reserved, $transferOut);
                if ($released <= 0) {
                    continue;
                }
                $stock->update([
                    $this->column($trx, 'balance') => $this->balanceValue($trx, $stock) + $released,
                    $this->column($trx, 'transfer_out') => max(
                        0,
                        $transferOut - $released,
                    ),
                ]);
                $this->logRelease($trx, $detail, $stock, $released);
            }
        }

        if ($this->shouldReserveIncoming($trx)) {
            $this->releaseIncoming($trx);
        }
    }

    public function restoreConsumed(Trx $trx): void
    {
        $trx->loadMissing('details');

        foreach ($trx->details as $detail) {
            if ($trx->trx_is_preorder && $trx->trx_seller_type !== 'warehouse') {
                continue;
            }

            $stock = $this->lockStock($trx, $detail);
            if (! $stock) {
                throw new ProcessException('Stok penjual untuk pengembalian pengiriman tidak ditemukan.');
            }

            $quantity = (int) $detail->trx_detail_qty;
            if ($quantity <= 0) {
                continue;
            }

            $stock->update([
                $this->column($trx, 'balance') => $this->balanceValue($trx, $stock) + $quantity,
            ]);
            $this->logRestoration($trx, $detail, $stock);
        }

        if ($this->shouldReserveIncoming($trx)) {
            $this->releaseIncoming($trx);
        }
    }

    private function reserveIncoming(Trx $trx): void
    {
        foreach ($trx->details as $detail) {
            $stock = MemberStock::query()
                ->where('member_stock_member_id', $trx->trx_buyer_id)
                ->where('member_stock_product_id', $detail->trx_detail_product_id)
                ->lockForUpdate()
                ->first();
            if (! $stock) {
                $stock = MemberStock::query()->create([
                    'member_stock_member_id' => $trx->trx_buyer_id,
                    'member_stock_product_id' => $detail->trx_detail_product_id,
                    'member_stock_balance' => 0,
                    'member_stock_transfer_in' => 0,
                    'member_stock_transfer_out' => 0,
                ]);
            }
            $stock->increment('member_stock_transfer_in', (int) $detail->trx_detail_qty);
        }
    }

    private function releaseIncoming(Trx $trx): void
    {
        foreach ($trx->details as $detail) {
            $stock = MemberStock::query()
                ->where('member_stock_member_id', $trx->trx_buyer_id)
                ->where('member_stock_product_id', $detail->trx_detail_product_id)
                ->lockForUpdate()
                ->first();
            if ($stock) {
                $stock->update([
                    'member_stock_transfer_in' => max(
                        0,
                        (int) $stock->member_stock_transfer_in - (int) $detail->trx_detail_qty,
                    ),
                ]);
            }
        }
    }

    private function shouldReserveIncoming(Trx $trx): bool
    {
        return $trx->trx_type === 'stock'
            && (int) $trx->trx_parent_trx_id === 0
            && in_array($trx->trx_buyer_type, ['distributor', 'agent', 'reseller'], true);
    }

    private function rootPreorderOrder(Trx $trx): ?Trx
    {
        $current = $trx;

        while ((int) $current->trx_parent_trx_id !== 0) {
            $parent = Trx::query()
                ->with('details')
                ->whereKey($current->trx_parent_trx_id)
                ->lockForUpdate()
                ->first();
            if (! $parent) {
                return null;
            }

            $current = $parent;
        }

        return $current->loadMissing('details');
    }

    private function terminalReservationOrder(Trx $root): ?Trx
    {
        $member = $this->rootSellerMember($root);
        while ($member) {
            if ($this->memberHasReservationForRoot($member, $root)) {
                $terminal = $root->replicate();
                $terminal->trx_is_preorder = false;
                $terminal->trx_seller_type = $this->memberSellerType($member);
                $terminal->trx_seller_id = $member->getKey();
                $terminal->setRelation('details', $root->details);

                return $terminal;
            }

            if ((int) $member->member_parent_member_id === 0) {
                break;
            }

            $member = Member::query()
                ->with('level')
                ->whereKey($member->member_parent_member_id)
                ->lockForUpdate()
                ->first();
        }

        $terminal = $root->replicate();
        $terminal->trx_is_preorder = false;
        $terminal->trx_seller_type = 'warehouse';
        $terminal->trx_seller_id = 1;
        $terminal->setRelation('details', $root->details);

        return $terminal;
    }

    private function rootSellerMember(Trx $root): ?Member
    {
        if ($root->trx_seller_type === 'warehouse') {
            return null;
        }

        return Member::query()
            ->with('level')
            ->whereKey($root->trx_seller_id)
            ->lockForUpdate()
            ->first();
    }

    private function memberHasReservationForRoot(Member $member, Trx $root): bool
    {
        foreach ($root->details as $detail) {
            $stock = MemberStock::query()
                ->where('member_stock_member_id', $member->getKey())
                ->where('member_stock_product_id', $detail->trx_detail_product_id)
                ->lockForUpdate()
                ->first();

            if (! $stock || (int) $stock->member_stock_transfer_out < (int) $detail->trx_detail_qty) {
                return false;
            }

            $hasLog = MemberStockLog::query()
                ->where('member_stock_log_member_id', $member->getKey())
                ->where('member_stock_log_product_id', $detail->trx_detail_product_id)
                ->where('member_stock_log_type', 'out')
                ->where('member_stock_log_note', "Pemesanan {$root->trx_code}")
                ->exists();

            if (! $hasLog) {
                return false;
            }
        }

        return true;
    }

    private function memberSellerType(Member $member): string
    {
        return match ($member->level?->member_level_code) {
            'DST' => 'distributor',
            'AGT' => 'agent',
            'RSL' => 'reseller',
            default => 'member',
        };
    }

    public function consume(Trx $trx): void
    {
        $trx->loadMissing('details');

        foreach ($trx->details as $detail) {
            $stock = $this->lockStock(
                $trx,
                $detail,
                createWarehouseStock: $trx->trx_seller_type === 'warehouse',
            );
            // A missing stock row represents an untracked/preorder line. Keep
            // the transaction flow intact; tracked stock is still validated.
            if (! $stock) {
                if ($trx->trx_is_preorder) {
                    throw new ProcessException('Stok penjual untuk pesanan inden belum tersedia untuk dikirim.');
                }

                continue;
            }
            $quantity = (int) $detail->trx_detail_qty;
            $transferOut = $this->transferOutValue($trx, $stock);
            $requiresOutgoingLog = false;
            if ($transferOut >= $quantity) {
                // Checkout already deducted balance and recorded the outgoing
                // mutation; shipping only clears the pending transfer.
                $stock->update([
                    $this->column($trx, 'transfer_out') => $transferOut - $quantity,
                ]);
            } elseif ($transferOut === 0) {
                // Keep manually-created legacy processing transactions working.
                if ($this->balanceValue($trx, $stock) < $quantity) {
                    throw new ProcessException('Stok penjual belum mencukupi untuk dikirim.');
                }
                $stock->update([
                    $this->column($trx, 'balance') => $this->balanceValue($trx, $stock) - $quantity,
                ]);
                $requiresOutgoingLog = true;
            } else {
                throw new ProcessException('Alokasi stok untuk pesanan tidak mencukupi untuk dikirim.');
            }

            if ($requiresOutgoingLog) {
                $this->logConsumption($trx, $detail, $stock, $quantity);
            }
        }
    }

    private function lockStock(
        Trx $trx,
        TrxDetail $detail,
        bool $createWarehouseStock = false,
    ): MemberStock|WarehouseStock|null {
        if ($trx->trx_seller_type === 'warehouse') {
            $query = WarehouseStock::query()
                ->where('warehouse_stock_warehouse_id', $trx->trx_seller_id)
                ->where('warehouse_stock_product_id', $detail->trx_detail_product_id);
            $stock = (clone $query)->lockForUpdate()->first();

            if (! $stock && $createWarehouseStock) {
                WarehouseStock::query()->firstOrCreate([
                    'warehouse_stock_warehouse_id' => $trx->trx_seller_id,
                    'warehouse_stock_product_id' => $detail->trx_detail_product_id,
                ], [
                    'warehouse_stock_balance' => 0,
                    'warehouse_stock_transfer_in' => 0,
                    'warehouse_stock_transfer_out' => 0,
                ]);

                $stock = (clone $query)->lockForUpdate()->first();
            }

            return $stock;
        }

        return MemberStock::query()
            ->where('member_stock_member_id', $trx->trx_seller_id)
            ->where('member_stock_product_id', $detail->trx_detail_product_id)
            ->lockForUpdate()
            ->first();
    }

    private function column(Trx $trx, string $field): string
    {
        return $trx->trx_seller_type === 'warehouse'
            ? "warehouse_stock_{$field}"
            : "member_stock_{$field}";
    }

    private function balanceValue(Trx $trx, MemberStock|WarehouseStock $stock): int
    {
        return $trx->trx_seller_type === 'warehouse'
            ? (int) $stock->warehouse_stock_balance
            : (int) $stock->member_stock_balance;
    }

    private function transferOutValue(Trx $trx, MemberStock|WarehouseStock $stock): int
    {
        return $trx->trx_seller_type === 'warehouse'
            ? (int) $stock->warehouse_stock_transfer_out
            : (int) $stock->member_stock_transfer_out;
    }

    private function logReservation(
        Trx $trx,
        TrxDetail $detail,
        MemberStock|WarehouseStock $stock,
        int $quantity,
    ): void {
        $this->logMovement(
            $trx,
            $detail,
            $stock,
            'out',
            $quantity,
            "Pemesanan {$trx->trx_code}",
        );
    }

    private function logRelease(
        Trx $trx,
        TrxDetail $detail,
        MemberStock|WarehouseStock $stock,
        int $quantity,
    ): void {
        $this->logMovement(
            $trx,
            $detail,
            $stock,
            'in',
            $quantity,
            "Pembatalan pesanan {$trx->trx_code}",
        );
    }

    private function logConsumption(
        Trx $trx,
        TrxDetail $detail,
        MemberStock|WarehouseStock $stock,
        int $quantity,
    ): void {
        $this->logMovement(
            $trx,
            $detail,
            $stock,
            'out',
            $quantity,
            "Pengiriman {$trx->trx_code}",
        );
    }

    private function logRestoration(Trx $trx, TrxDetail $detail, MemberStock|WarehouseStock $stock): void
    {
        $this->logMovement(
            $trx,
            $detail,
            $stock,
            'in',
            (int) $detail->trx_detail_qty,
            "Stok dikembalikan karena pengiriman {$trx->trx_code} dibatalkan",
        );
    }

    private function logMovement(
        Trx $trx,
        TrxDetail $detail,
        MemberStock|WarehouseStock $stock,
        string $type,
        int $quantity,
        string $note,
    ): void {
        if ($stock instanceof WarehouseStock) {
            WarehouseStockLog::query()->create([
                'warehouse_stock_log_warehouse_id' => $trx->trx_seller_id,
                'warehouse_stock_log_product_id' => $detail->trx_detail_product_id,
                'warehouse_stock_log_type' => $type,
                'warehouse_stock_log_quantity' => $quantity,
                'warehouse_stock_log_unit_price' => $detail->trx_detail_nett_price,
                'warehouse_stock_log_balance' => $this->balanceValue($trx, $stock),
                'warehouse_stock_log_note' => $note,
                'warehouse_stock_log_datetime' => now(),
            ]);

            return;
        }

        MemberStockLog::query()->create([
            'member_stock_log_member_id' => $trx->trx_seller_id,
            'member_stock_log_product_id' => $detail->trx_detail_product_id,
            'member_stock_log_type' => $type,
            'member_stock_log_quantity' => $quantity,
            'member_stock_log_unit_price' => $detail->trx_detail_nett_price,
            'member_stock_log_balance' => $this->balanceValue($trx, $stock),
            'member_stock_log_note' => $note,
            'member_stock_log_datetime' => now(),
        ]);
    }
}
