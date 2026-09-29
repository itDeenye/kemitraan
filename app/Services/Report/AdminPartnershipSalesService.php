<?php

namespace App\Services\Report;

use App\Libraries\DataTable;
use App\Models\Customer;
use App\Models\Member;
use App\Models\Trx;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder;

class AdminPartnershipSalesService
{
    /** @param array<string, mixed> $params */
    public function sales(array $params): array
    {
        $trxTable = (new Trx)->getTable();
        $memberTable = (new Member)->getTable();
        $customerTable = (new Customer)->getTable();
        $warehouseTable = (new Warehouse)->getTable();

        $query = DataTable::select([
            "{$trxTable}.trx_id as id",
            "{$trxTable}.trx_datetime as datetime",
            "{$trxTable}.trx_code as code",
            "{$trxTable}.trx_is_preorder as is_preorder",
            "{$trxTable}.trx_buyer_type as buyer_type",
            "{$trxTable}.trx_buyer_id as buyer_id",
            'buyer_member.member_name as buyer_name',
            'buyer_member.member_code as buyer_code',
            'buyer_customer.customer_name as buyer_customer_name',
            "{$trxTable}.trx_seller_type as seller_type",
            "{$trxTable}.trx_seller_id as seller_id",
            'seller_member.member_name as seller_member_name',
            'seller_member.member_code as seller_member_code',
            'seller_warehouse.warehouse_name as seller_warehouse_name',
            "{$trxTable}.trx_grand_total_nett_price as total_price",
            "{$trxTable}.trx_status as status",
        ])
            ->from($trxTable)
            ->leftJoin("{$memberTable} as buyer_member", "buyer_member.member_id = {$trxTable}.trx_buyer_id")
            ->leftJoin("{$customerTable} as buyer_customer", "buyer_customer.customer_id = {$trxTable}.trx_buyer_id")
            ->leftJoin("{$memberTable} as seller_member", "seller_member.member_id = {$trxTable}.trx_seller_id")
            ->leftJoin("{$warehouseTable} as seller_warehouse", "seller_warehouse.warehouse_id = {$trxTable}.trx_seller_id")
            ->whereIn("{$trxTable}.trx_buyer_type", ['distributor', 'agent', 'reseller', 'customer'])
            ->whereIn("{$trxTable}.trx_seller_type", ['distributor', 'agent', 'reseller'])
            ->search([
                'code',
                'buyer_name',
                'buyer_code',
                'buyer_customer_name',
                'seller_member_name',
                'seller_member_code',
                'seller_warehouse_name',
            ])
            ->defaultSort('-datetime');

        $this->applyPartyFilters($query, $params);

        if (isset($params['date_from']) || isset($params['date_to'])) {
            $dateFrom = ($params['date_from'] ?? now()->startOfMonth()->toDateString()).' 00:00:00';
            $dateTo = ($params['date_to'] ?? now()->endOfMonth()->toDateString()).' 23:59:59';
            $query->whereBetween("{$trxTable}.trx_datetime", [$dateFrom, $dateTo]);
        }

        if (isset($params['filter']['status'])) {
            $query->where("{$trxTable}.trx_status", $params['filter']['status']);
        }

        if (isset($params['filter']['is_preorder'])) {
            $query->where("{$trxTable}.trx_is_preorder", $params['filter']['is_preorder'] ? 1 : 0);
        }

        if (isset($params['filter']['seller_type'])) {
            $query->where("{$trxTable}.trx_seller_type", $params['filter']['seller_type']);
        }

        if (isset($params['filter']['buyer_type'])) {
            $query->where("{$trxTable}.trx_buyer_type", $params['filter']['buyer_type']);
        }

        if (isset($params['pagination_bool']) && in_array($params['pagination_bool'], ['false', '0', false, 0], true)) {
            $query->allowUnpaginated();
        }

        return $query->get($params);
    }

    /** @param array<string, mixed> $params */
    private function applyPartyFilters(DataTable $query, array &$params): void
    {
        $buyerName = data_get($params, 'filter.buyer_name.like', data_get($params, 'buyer_name.like'));
        $buyerCode = data_get($params, 'filter.buyer_code.like', data_get($params, 'buyer_code.like'));
        $sellerName = data_get($params, 'filter.seller_name.like', data_get($params, 'seller_name.like'));
        $sellerCode = data_get($params, 'filter.seller_code.like', data_get($params, 'seller_code.like'));

        foreach (['buyer_name', 'buyer_code', 'seller_name', 'seller_code'] as $field) {
            unset($params['filter'][$field], $params[$field]);
        }

        if (is_string($buyerName) && $buyerName !== '') {
            $query->where(function (Builder $buyerQuery) use ($buyerName): void {
                $buyerQuery
                    ->where('buyer_member.member_name', 'like', $this->likePattern($buyerName))
                    ->orWhere('buyer_customer.customer_name', 'like', $this->likePattern($buyerName));
            });
        }

        if (is_string($buyerCode) && $buyerCode !== '') {
            $query->where('buyer_member.member_code', 'like', $this->likePattern($buyerCode));
        }

        if (is_string($sellerName) && $sellerName !== '') {
            $query->where(function (Builder $sellerQuery) use ($sellerName): void {
                $sellerQuery
                    ->where('seller_member.member_name', 'like', $this->likePattern($sellerName))
                    ->orWhere('seller_warehouse.warehouse_name', 'like', $this->likePattern($sellerName));
            });
        }

        if (is_string($sellerCode) && $sellerCode !== '') {
            $query->where('seller_member.member_code', 'like', $this->likePattern($sellerCode));
        }
    }

    private function likePattern(string $value): string
    {
        return str_contains($value, '%') || str_contains($value, '_')
            ? $value
            : "%{$value}%";
    }
}
