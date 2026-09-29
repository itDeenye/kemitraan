<?php

namespace App\Services\Partnership;

use App\Libraries\DataTable;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

class AdminMemberStockService
{
    /** @param array<string, mixed> $params */
    public function list(array $params): array
    {
        $memberId = data_get($params, 'filter.member_id');

        if ($memberId !== null && $memberId !== '') {
            return $this->productsForMember($params, (int) $memberId);
        }

        return DataTable::select([
            'member_stock.member_stock_id as id', 'member_stock.member_stock_member_id as member_id',
            'member.member_code as member_code', 'member.member_name as member_name',
            'member_level.member_level_id as member_level_id', 'member_level.member_level_code as member_level_code',
            'member_level.member_level_name as member_level',
            'member_stock.member_stock_product_id as product_id', 'product.product_code as product_code',
            'product.product_name as product_name', 'product.product_product_category_id as category_id',
            'product_category.product_category_name as category_name', 'product.product_unit as unit',
            'member_stock.member_stock_balance as balance', 'member_stock.member_stock_transfer_in as transfer_in',
            'member_stock.member_stock_transfer_out as transfer_out',
        ])->selectRaw('(SELECT MAX(member_stock_log_datetime) FROM member_stock_log WHERE member_stock_log_member_id = member_stock.member_stock_member_id AND member_stock_log_product_id = member_stock.member_stock_product_id) as last_updated_at')
            ->from('member_stock')->leftJoin('member', 'member.member_id = member_stock.member_stock_member_id')
            ->leftJoin('member_level', 'member_level.member_level_id = member.member_member_level_id')
            ->leftJoin('product', 'product.product_id = member_stock.member_stock_product_id')
            ->leftJoin('product_category', 'product_category.product_category_id = product.product_product_category_id')
            ->where('member.member_status', '!=', 3)->where('product.product_is_deleted', 0)
            ->search(['member_code', 'member_name', 'product_code', 'product_name', 'category_name'])->defaultSort('-id')->get($params);
    }

    /** @param array<string, mixed> $params */
    private function productsForMember(array $params, int $memberId): array
    {
        $member = Member::query()
            ->with('level')
            ->where('member_status', '!=', 3)
            ->findOrFail($memberId);
        $memberStocks = DB::table('member_stock')
            ->where('member_stock_member_id', $memberId);

        unset($params['filter']['member_id'], $params['filter']['member_level_id'], $params['filter']['member_level']);

        return DataTable::select([
            'product.product_id as product_id',
            'product.product_code as product_code',
            'product.product_name as product_name',
            'product.product_product_category_id as category_id',
            'product_category.product_category_name as category_name',
            'product.product_unit as unit',
        ])
            ->selectRaw('member_stock.member_stock_id as id')
            ->selectRaw('? as member_id', [$member->getKey()])
            ->selectRaw('? as member_code', [$member->member_code])
            ->selectRaw('? as member_name', [$member->member_name])
            ->selectRaw('? as member_level_id', [$member->member_member_level_id])
            ->selectRaw('? as member_level_code', [$member->level?->member_level_code])
            ->selectRaw('? as member_level', [$member->level?->member_level_name])
            ->selectRaw('COALESCE(member_stock.member_stock_balance, 0) as balance')
            ->selectRaw('COALESCE(member_stock.member_stock_transfer_in, 0) as transfer_in')
            ->selectRaw('COALESCE(member_stock.member_stock_transfer_out, 0) as transfer_out')
            ->selectRaw('(SELECT MAX(member_stock_log_datetime) FROM member_stock_log WHERE member_stock_log_member_id = ? AND member_stock_log_product_id = product.product_id) as last_updated_at', [$member->getKey()])
            ->from('product')
            ->leftJoinSub($memberStocks, 'member_stock', 'member_stock.member_stock_product_id = product.product_id')
            ->leftJoin('product_category', 'product_category.product_category_id = product.product_product_category_id')
            ->where('product.product_is_deleted', 0)
            ->search(['product_code', 'product_name', 'category_name'])
            ->defaultSort('product_name')
            ->get($params);
    }
}
