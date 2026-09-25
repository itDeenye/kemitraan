<?php

namespace App\Services\Report;

use App\Libraries\DataTable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AdminMemberBatchStockReportService
{
    /** @param array<string, mixed> $params */
    public function list(array $params): array
    {
        $query = DataTable::select([
            'batch_stock.member_id as member_id',
            'member.member_code as member_code',
            'member.member_name as member_name',
            'member.member_status as member_status',
            'member.member_member_level_id as member_level_id',
            'member_level.member_level_code as member_level_code',
            'member_level.member_level_name as member_level_name',
            'batch_stock.product_id as product_id',
            'product.product_code as product_code',
            'product.product_name as product_name',
            'product.product_product_category_id as category_id',
            'product_category.product_category_name as category_name',
            'product.product_unit as unit',
            'batch_stock.batch_number as batch_number',
            'batch_stock.expiry_date as expiry_date',
            'batch_stock.balance as balance',
        ])
            ->fromSub($this->batchStock(), 'batch_stock')
            ->join('inner', 'member', 'member.member_id = batch_stock.member_id')
            ->leftJoin('member_level', 'member_level.member_level_id = member.member_member_level_id')
            ->join('inner', 'product', 'product.product_id = batch_stock.product_id')
            ->leftJoin(
                'product_category',
                'product_category.product_category_id = product.product_product_category_id',
            )
            ->where('member.member_status', '!=', 3)
            ->where('product.product_is_deleted', '=', 0)
            ->search([
                'member_code',
                'member_name',
                'member_level_code',
                'member_level_name',
                'product_code',
                'product_name',
                'category_name',
                'batch_number',
            ])
            ->defaultSort('member_name,product_name,batch_number')
            ->allowUnpaginated();

        $this->applySpecialFilters($query, $params);

        return $query->get($params);
    }

    private function batchStock(): Builder
    {
        $knownBalances = DB::query()
            ->fromSub($this->movements(), 'movement')
            ->select([
                'movement.member_id',
                'movement.product_id',
                'movement.batch_number',
            ])
            ->selectRaw('MAX(movement.expiry_date) as expiry_date')
            ->selectRaw('SUM(movement.quantity) as balance')
            ->groupBy([
                'movement.member_id',
                'movement.product_id',
                'movement.batch_number',
            ]);

        $knownTotals = DB::query()
            ->fromSub(clone $knownBalances, 'known_balance')
            ->select(['known_balance.member_id', 'known_balance.product_id'])
            ->selectRaw('SUM(known_balance.balance) as known_balance')
            ->groupBy(['known_balance.member_id', 'known_balance.product_id']);

        $knownRows = DB::query()
            ->fromSub(clone $knownBalances, 'known_batch')
            ->select([
                'known_batch.member_id',
                'known_batch.product_id',
                'known_batch.batch_number',
                'known_batch.expiry_date',
                'known_batch.balance',
            ])
            ->where('known_batch.balance', '>', 0);

        $unassignedRows = DB::table('member_stock as current_stock')
            ->leftJoinSub($knownTotals, 'known_total', function ($join): void {
                $join->on('known_total.member_id', '=', 'current_stock.member_stock_member_id')
                    ->on('known_total.product_id', '=', 'current_stock.member_stock_product_id');
            })
            ->selectRaw('current_stock.member_stock_member_id as member_id')
            ->selectRaw('current_stock.member_stock_product_id as product_id')
            ->selectRaw('NULL as batch_number')
            ->selectRaw('NULL as expiry_date')
            ->selectRaw(
                'current_stock.member_stock_balance - COALESCE(known_total.known_balance, 0) as balance',
            )
            ->whereRaw(
                'current_stock.member_stock_balance - COALESCE(known_total.known_balance, 0) > 0',
            );

        return $knownRows->unionAll($unassignedRows);
    }

    private function movements(): Builder
    {
        $received = DB::table('goods_receive_detail as receive_detail')
            ->join(
                'goods_receive as receive',
                'receive.goods_receive_id',
                '=',
                'receive_detail.goods_receive_detail_receive_id',
            )
            ->where('receive.goods_receive_status', 'completed')
            ->whereIn('receive.goods_receive_buyer_type', ['distributor', 'agent', 'reseller'])
            ->selectRaw('receive.goods_receive_buyer_id as member_id')
            ->selectRaw('receive_detail.goods_receive_detail_product_id as product_id')
            ->selectRaw('receive_detail.goods_receive_detail_batch_number as batch_number')
            ->selectRaw('receive_detail.goods_receive_detail_expire_date as expiry_date')
            ->selectRaw('receive_detail.goods_receive_detail_qty as quantity');

        $shippingItems = DB::query()
            ->fromSub($this->shippingHeaders(), 'shipping')
            ->join('shipping_detail as detail', function ($join): void {
                $join->on('detail.shipping_detail_shipping_type', '=', 'shipping.shipping_type')
                    ->on('detail.shipping_detail_shipping_id', '=', 'shipping.shipping_id');
            });

        $sold = (clone $shippingItems)
            ->join('trx as trx_record', function ($join): void {
                $join->on('trx_record.trx_id', '=', 'shipping.reference_id')
                    ->where('shipping.reference_type', '=', 'trx');
            })
            ->whereIn('trx_record.trx_seller_type', ['distributor', 'agent', 'reseller'])
            ->selectRaw('trx_record.trx_seller_id as member_id')
            ->selectRaw('detail.shipping_detail_product_id as product_id')
            ->selectRaw('detail.shipping_detail_batch_number as batch_number')
            ->selectRaw('detail.shipping_detail_expire_date as expiry_date')
            ->selectRaw('-detail.shipping_detail_qty as quantity');

        $returned = (clone $shippingItems)
            ->join('return as product_return', function ($join): void {
                $join->on('product_return.return_id', '=', 'shipping.reference_id')
                    ->where('shipping.reference_type', '=', 'return_company');
            })
            ->whereNotNull('product_return.return_received_datetime')
            ->selectRaw('product_return.return_member_id as member_id')
            ->selectRaw('detail.shipping_detail_product_id as product_id')
            ->selectRaw('detail.shipping_detail_batch_number as batch_number')
            ->selectRaw('detail.shipping_detail_expire_date as expiry_date')
            ->selectRaw('-detail.shipping_detail_qty as quantity');

        $replacement = (clone $shippingItems)
            ->join('return as product_return', function ($join): void {
                $join->on('product_return.return_id', '=', 'shipping.reference_id')
                    ->where('shipping.reference_type', '=', 'return_replacement');
            })
            ->whereNotNull('product_return.return_completed_datetime')
            ->selectRaw('product_return.return_member_id as member_id')
            ->selectRaw('detail.shipping_detail_product_id as product_id')
            ->selectRaw('detail.shipping_detail_batch_number as batch_number')
            ->selectRaw('detail.shipping_detail_expire_date as expiry_date')
            ->selectRaw('detail.shipping_detail_qty as quantity');

        return $received
            ->unionAll($sold)
            ->unionAll($returned)
            ->unionAll($replacement);
    }

    private function shippingHeaders(): Builder
    {
        $express = DB::table('shipping_courier_express')
            ->selectRaw("'courier_express' as shipping_type")
            ->selectRaw('shipping_courier_express_id as shipping_id')
            ->selectRaw('shipping_courier_express_ref_type as reference_type')
            ->selectRaw('shipping_courier_express_ref_id as reference_id');
        $instant = DB::table('shipping_courier_instant')
            ->selectRaw("'courier_instant' as shipping_type")
            ->selectRaw('shipping_courier_instant_id as shipping_id')
            ->selectRaw('shipping_courier_instant_ref_type as reference_type')
            ->selectRaw('shipping_courier_instant_ref_id as reference_id');
        $manual = DB::table('shipping_courier_manual')
            ->selectRaw("'courier_manual' as shipping_type")
            ->selectRaw('shipping_courier_manual_id as shipping_id')
            ->selectRaw('shipping_courier_manual_ref_type as reference_type')
            ->selectRaw('shipping_courier_manual_ref_id as reference_id');
        $pickup = DB::table('shipping_pickup')
            ->selectRaw("'pickup' as shipping_type")
            ->selectRaw('shipping_pickup_id as shipping_id')
            ->selectRaw('shipping_pickup_ref_type as reference_type')
            ->selectRaw('shipping_pickup_ref_id as reference_id');

        return $express
            ->unionAll($instant)
            ->unionAll($manual)
            ->unionAll($pickup);
    }

    /** @param array<string, mixed> $params */
    private function applySpecialFilters(DataTable $query, array &$params): void
    {
        $filters = is_array($params['filter'] ?? null) ? $params['filter'] : [];

        if (filled($filters['expiry_date_from'] ?? null)) {
            $query->where('batch_stock.expiry_date', '>=', $filters['expiry_date_from']);
        }
        if (filled($filters['expiry_date_to'] ?? null)) {
            $query->where('batch_stock.expiry_date', '<=', $filters['expiry_date_to']);
        }
        if (filled($filters['expiry_status'] ?? null)) {
            $this->applyExpiryStatus($query, (string) $filters['expiry_status']);
        }
        unset(
            $params['filter']['expiry_date_from'],
            $params['filter']['expiry_date_to'],
            $params['filter']['expiry_status'],
        );
    }

    private function applyExpiryStatus(DataTable $query, string $status): void
    {
        $today = now()->toDateString();
        $warningDate = now()->addDays(90)->toDateString();

        match ($status) {
            'unknown' => $query->whereNull('batch_stock.expiry_date'),
            'expired' => $query->where('batch_stock.expiry_date', '<', $today),
            'expiring_soon' => $query
                ->whereBetween('batch_stock.expiry_date', [$today, $warningDate]),
            'safe' => $query->where('batch_stock.expiry_date', '>', $warningDate),
            default => null,
        };
    }
}
