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
        foreach ([
            'buyer_name' => 'buyer_member.member_name',
            'buyer_code' => 'buyer_member.member_code',
            'seller_name' => 'seller_member.member_name',
            'seller_code' => 'seller_member.member_code',
        ] as $field => $column) {
            $filter = $params['filter'][$field] ?? $params[$field] ?? null;
            unset($params['filter'][$field], $params[$field]);

            foreach (is_array($filter) ? $filter : ['eq' => $filter] as $operator => $value) {
                if (! in_array($operator, ['eq', 'like'], true) || ! is_string($value) || $value === '') {
                    continue;
                }
                $value = $operator === 'like' ? $this->likePattern($value) : $value;
                $operator = $operator === 'like' ? 'like' : '=';

                if ($field === 'buyer_name') {
                    $query->where(function (Builder $buyerQuery) use ($column, $operator, $value): void {
                        $buyerQuery->where(function (Builder $memberQuery) use ($column, $operator, $value): void {
                            $memberQuery->whereIn('trx.trx_buyer_type', ['distributor', 'agent', 'reseller'])
                                ->where($column, $operator, $value);
                        })->orWhere(function (Builder $customerQuery) use ($operator, $value): void {
                            $customerQuery->where('trx.trx_buyer_type', 'customer')
                                ->where('buyer_customer.customer_name', $operator, $value);
                        });
                    });
                } else {
                    if ($field === 'buyer_code') {
                        $query->whereIn('trx.trx_buyer_type', ['distributor', 'agent', 'reseller']);
                    }
                    $query->where($column, $operator, $value);
                }
            }
        }
    }

    private function likePattern(string $value): string
    {
        return str_contains($value, '%') || str_contains($value, '_')
            ? $value
            : "%{$value}%";
    }
}
