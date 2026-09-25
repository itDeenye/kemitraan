<?php

namespace App\Services\Report;

use App\Libraries\DataTable;
use App\Models\Member;
use App\Models\MemberLevel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RefCity;
use App\Models\RefProvince;
use App\Models\Trx;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WarehouseStockLog;
use App\Services\Transaction\AdminTransactionService;
use Illuminate\Support\Facades\DB;

class AdminReportService
{
    public function __construct(private readonly AdminTransactionService $transactionService) {}

    /** @param array<string, mixed> $params */
    public function sales(array $params): array
    {
        return $this->transactionService->orders($params);
    }

    /** @param array<string, mixed> $params */
    public function partnerships(array $params): array
    {
        $memberTable = (new Member)->getTable();
        $levelTable = (new MemberLevel)->getTable();
        $provinceTable = (new RefProvince)->getTable();
        $cityTable = (new RefCity)->getTable();
        $trxTable = (new Trx)->getTable();
        $defaultAddresses = DB::table('member_address')
            ->where('member_address_is_default', 1);
        [$dateFrom, $dateTo] = $this->dateRange($params);

        $query = DataTable::select([
            "{$memberTable}.member_id as id",
            "{$memberTable}.member_code as code",
            "{$memberTable}.member_name as name",
            "{$memberTable}.member_member_level_id as member_level_id",
            "{$levelTable}.member_level_code as member_level_code",
            "{$levelTable}.member_level_name as member_level_name",
            'default_address.member_address_province_id as province_id',
            "{$provinceTable}.province_name as province_name",
            'default_address.member_address_city_id as city_id',
            "{$cityTable}.city_name as city_name",
            "{$memberTable}.member_status as status",
            "{$memberTable}.member_join_datetime as joined_at",
        ])
            ->selectRaw(
                "(SELECT COUNT(*) FROM {$trxTable} WHERE {$trxTable}.trx_buyer_id = {$memberTable}.member_id AND {$trxTable}.trx_buyer_type IN ('distributor','agent','reseller') AND {$trxTable}.trx_status NOT IN ('cancelled','rejected') AND {$trxTable}.trx_datetime BETWEEN ? AND ?) as total_purchases",
                [$dateFrom, $dateTo],
            )
            ->selectRaw(
                "(SELECT COALESCE(SUM({$trxTable}.trx_grand_total_nett_price), 0) FROM {$trxTable} WHERE {$trxTable}.trx_buyer_id = {$memberTable}.member_id AND {$trxTable}.trx_buyer_type IN ('distributor','agent','reseller') AND {$trxTable}.trx_status NOT IN ('cancelled','rejected') AND {$trxTable}.trx_datetime BETWEEN ? AND ?) as purchase_amount",
                [$dateFrom, $dateTo],
            )
            ->selectRaw(
                "(SELECT COALESCE(SUM({$trxTable}.trx_grand_total_nett_price), 0) FROM {$trxTable} WHERE {$trxTable}.trx_seller_id = {$memberTable}.member_id AND {$trxTable}.trx_seller_type IN ('distributor','agent','reseller') AND {$trxTable}.trx_status NOT IN ('cancelled','rejected') AND {$trxTable}.trx_datetime BETWEEN ? AND ?) as sales_amount",
                [$dateFrom, $dateTo],
            )
            ->from($memberTable)
            ->leftJoin($levelTable, "{$levelTable}.member_level_id = {$memberTable}.member_member_level_id")
            ->leftJoinSub($defaultAddresses, 'default_address', "default_address.member_address_member_id = {$memberTable}.member_id")
            ->leftJoin($provinceTable, "{$provinceTable}.province_id = default_address.member_address_province_id")
            ->leftJoin($cityTable, "{$cityTable}.city_id = default_address.member_address_city_id")
            ->where("{$memberTable}.member_status", '!=', 3)
            ->search(['code', 'name', 'member_level_code', 'member_level_name', 'province_name', 'city_name'])
            ->defaultSort('-id')
            ->allowUnpaginated();

        if (isset($params['date_from']) || isset($params['date_to'])) {
            $query->whereBetween("{$memberTable}.member_join_datetime", [$dateFrom, $dateTo]);
        }

        return $query->get($params);
    }

    /** @param array<string, mixed> $params */
    public function stocks(array $params): array
    {
        $stockTable = (new WarehouseStock)->getTable();
        $logTable = (new WarehouseStockLog)->getTable();
        $warehouseTable = (new Warehouse)->getTable();
        $productTable = (new Product)->getTable();
        $categoryTable = (new ProductCategory)->getTable();
        [$dateFrom, $dateTo] = $this->dateRange($params);

        return DataTable::select([
            "{$stockTable}.warehouse_stock_id as id",
            "{$stockTable}.warehouse_stock_warehouse_id as warehouse_id",
            "{$warehouseTable}.warehouse_name as warehouse_name",
            "{$stockTable}.warehouse_stock_product_id as product_id",
            "{$productTable}.product_code as product_code",
            "{$productTable}.product_name as product_name",
            "{$productTable}.product_product_category_id as category_id",
            "{$categoryTable}.product_category_name as category_name",
            "{$productTable}.product_unit as unit",
            "{$stockTable}.warehouse_stock_balance as ending_balance",
        ])
            ->selectRaw(
                "(SELECT COALESCE(SUM({$logTable}.warehouse_stock_log_quantity), 0) FROM {$logTable} WHERE {$logTable}.warehouse_stock_log_warehouse_id = {$stockTable}.warehouse_stock_warehouse_id AND {$logTable}.warehouse_stock_log_product_id = {$stockTable}.warehouse_stock_product_id AND {$logTable}.warehouse_stock_log_type = 'in' AND {$logTable}.warehouse_stock_log_datetime BETWEEN ? AND ?) as stock_in",
                [$dateFrom, $dateTo],
            )
            ->selectRaw(
                "(SELECT COALESCE(SUM({$logTable}.warehouse_stock_log_quantity), 0) FROM {$logTable} WHERE {$logTable}.warehouse_stock_log_warehouse_id = {$stockTable}.warehouse_stock_warehouse_id AND {$logTable}.warehouse_stock_log_product_id = {$stockTable}.warehouse_stock_product_id AND {$logTable}.warehouse_stock_log_type = 'out' AND {$logTable}.warehouse_stock_log_datetime BETWEEN ? AND ?) as stock_out",
                [$dateFrom, $dateTo],
            )
            ->from($stockTable)
            ->leftJoin($warehouseTable, "{$warehouseTable}.warehouse_id = {$stockTable}.warehouse_stock_warehouse_id")
            ->leftJoin($productTable, "{$productTable}.product_id = {$stockTable}.warehouse_stock_product_id")
            ->leftJoin($categoryTable, "{$categoryTable}.product_category_id = {$productTable}.product_product_category_id")
            ->where("{$productTable}.product_is_deleted", 0)
            ->search(['warehouse_name', 'product_code', 'product_name', 'category_name'])
            ->defaultSort('-id')
            ->allowUnpaginated()
            ->get($params);
    }

    /** @param array<string, mixed> $params */
    public function cashIncome(array $params): array
    {
        $params['filter'] = [...($params['filter'] ?? []), 'status' => 'approved'];

        return $this->transactionService->payments($params);
    }

    /** @param array<string, mixed> $params
     * @return array{string, string}
     */
    private function dateRange(array $params): array
    {
        return [
            ($params['date_from'] ?? now()->startOfMonth()->toDateString()).' 00:00:00',
            ($params['date_to'] ?? now()->endOfMonth()->toDateString()).' 23:59:59',
        ];
    }
}
