<?php

namespace App\Services\Inventory;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SiteAdministrator;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WarehouseStockAdjustment;
use App\Models\WarehouseStockAdjustmentDetail;
use App\Models\WarehouseStockLog;
use App\Services\Document\DocumentCodeService;
use Illuminate\Support\Facades\DB;

class AdminInventoryService
{
    public function __construct(private readonly DocumentCodeService $documentCodeService) {}

    /** @param array<string, mixed> $params */
    public function warehouseStocks(array $params): array
    {
        return DataTable::select([
            'warehouse_stock.warehouse_stock_id as id', 'warehouse_stock.warehouse_stock_warehouse_id as warehouse_id',
            'warehouse.warehouse_name as warehouse_name', 'warehouse_stock.warehouse_stock_product_id as product_id',
            'product.product_code as product_code', 'product.product_name as product_name',
            'product.product_product_category_id as category_id', 'product_category.product_category_name as category_name',
            'product.product_unit as unit', 'warehouse_stock.warehouse_stock_balance as balance',
            'warehouse_stock.warehouse_stock_transfer_in as transfer_in', 'warehouse_stock.warehouse_stock_transfer_out as transfer_out',
        ])->from('warehouse_stock')->leftJoin('warehouse', 'warehouse.warehouse_id = warehouse_stock.warehouse_stock_warehouse_id')
            ->leftJoin('product', 'product.product_id = warehouse_stock.warehouse_stock_product_id')
            ->leftJoin('product_category', 'product_category.product_category_id = product.product_product_category_id')
            ->where('product.product_is_deleted', 0)->search(['warehouse_name', 'product_code', 'product_name', 'category_name'])
            ->defaultSort('-id')->get($params);
    }

    public function warehouseStock(WarehouseStock $stock): object
    {
        $stockTable = (new WarehouseStock)->getTable();
        $warehouseTable = (new Warehouse)->getTable();
        $productTable = (new Product)->getTable();
        $categoryTable = (new ProductCategory)->getTable();

        return DB::table($stockTable)
            ->leftJoin($warehouseTable, "{$warehouseTable}.warehouse_id", '=', "{$stockTable}.warehouse_stock_warehouse_id")
            ->leftJoin($productTable, "{$productTable}.product_id", '=', "{$stockTable}.warehouse_stock_product_id")
            ->leftJoin($categoryTable, "{$categoryTable}.product_category_id", '=', "{$productTable}.product_product_category_id")
            ->where("{$productTable}.product_is_deleted", 0)
            ->where("{$stockTable}.warehouse_stock_id", $stock->getKey())
            ->select([
                "{$stockTable}.warehouse_stock_id as id",
                "{$stockTable}.warehouse_stock_warehouse_id as warehouse_id",
                "{$warehouseTable}.warehouse_name as warehouse_name",
                "{$stockTable}.warehouse_stock_product_id as product_id",
                "{$productTable}.product_code as product_code",
                "{$productTable}.product_name as product_name",
                "{$productTable}.product_product_category_id as category_id",
                "{$categoryTable}.product_category_name as category_name",
                "{$productTable}.product_unit as unit",
                "{$stockTable}.warehouse_stock_balance as balance",
                "{$stockTable}.warehouse_stock_transfer_in as transfer_in",
                "{$stockTable}.warehouse_stock_transfer_out as transfer_out",
            ])
            ->firstOrFail();
    }

    /** @param array<string, mixed> $params */
    public function warehouseStockMutations(array $params): array
    {
        $logTable = (new WarehouseStockLog)->getTable();
        $warehouseTable = (new Warehouse)->getTable();
        $productTable = (new Product)->getTable();

        $query = DataTable::select([
            "{$logTable}.warehouse_stock_log_id as id",
            "{$logTable}.warehouse_stock_log_warehouse_id as warehouse_id",
            "{$warehouseTable}.warehouse_name as warehouse_name",
            "{$logTable}.warehouse_stock_log_product_id as product_id",
            "{$productTable}.product_code as product_code",
            "{$productTable}.product_name as product_name",
            "{$logTable}.warehouse_stock_log_type as type",
            "{$logTable}.warehouse_stock_log_quantity as quantity",
            "{$logTable}.warehouse_stock_log_unit_price as unit_price",
            "{$logTable}.warehouse_stock_log_balance as balance",
            "{$logTable}.warehouse_stock_log_note as note",
            "{$logTable}.warehouse_stock_log_datetime as happened_at",
        ])
            ->from($logTable)
            ->leftJoin($warehouseTable, "{$warehouseTable}.warehouse_id = {$logTable}.warehouse_stock_log_warehouse_id")
            ->leftJoin($productTable, "{$productTable}.product_id = {$logTable}.warehouse_stock_log_product_id")
            ->search(['warehouse_name', 'product_code', 'product_name', 'note'])
            ->defaultSort('-id');

        if (isset($params['date_from']) || isset($params['date_to'])) {
            $query->whereBetween("{$logTable}.warehouse_stock_log_datetime", [
                ($params['date_from'] ?? '1970-01-01').' 00:00:00',
                ($params['date_to'] ?? '2999-12-31').' 23:59:59',
            ]);
        }

        return $query->get($params);
    }

    /** @param array<string, mixed> $params */
    public function stockAdjustments(array $params): array
    {
        $adjustmentTable = (new WarehouseStockAdjustment)->getTable();
        $detailTable = (new WarehouseStockAdjustmentDetail)->getTable();
        $warehouseTable = (new Warehouse)->getTable();
        $adminTable = (new SiteAdministrator)->getTable();

        $query = DataTable::select([
            "{$adjustmentTable}.stock_adjustment_id as id",
            "{$adjustmentTable}.stock_adjustment_code as code",
            "{$adjustmentTable}.stock_adjustment_note as note",
            "{$adjustmentTable}.stock_adjustment_warehouse_id as warehouse_id",
            "{$warehouseTable}.warehouse_name as warehouse_name",
            "{$adjustmentTable}.stock_adjustment_administrator_id as administrator_id",
            "{$adminTable}.administrator_name as administrator_name",
            "{$adjustmentTable}.stock_adjustment_datetime as happened_at",
        ])
            ->selectRaw("(SELECT COUNT(*) FROM {$detailTable} WHERE {$detailTable}.stock_adjustment_detail_stock_adjustment_id = {$adjustmentTable}.stock_adjustment_id) as total_items")
            ->selectRaw("(SELECT COALESCE(SUM({$detailTable}.stock_adjustment_detail_qty), 0) FROM {$detailTable} WHERE {$detailTable}.stock_adjustment_detail_stock_adjustment_id = {$adjustmentTable}.stock_adjustment_id) as total_quantity")
            ->from($adjustmentTable)
            ->leftJoin($warehouseTable, "{$warehouseTable}.warehouse_id = {$adjustmentTable}.stock_adjustment_warehouse_id")
            ->leftJoin($adminTable, "{$adminTable}.administrator_id = {$adjustmentTable}.stock_adjustment_administrator_id")
            ->search(['code', 'note', 'warehouse_name', 'administrator_name'])
            ->defaultSort('-id');

        if (isset($params['date_from']) || isset($params['date_to'])) {
            $query->whereBetween("{$adjustmentTable}.stock_adjustment_datetime", [
                ($params['date_from'] ?? '1970-01-01').' 00:00:00',
                ($params['date_to'] ?? '2999-12-31').' 23:59:59',
            ]);
        }

        return $query->get($params);
    }

    public function stockAdjustment(WarehouseStockAdjustment $adjustment): WarehouseStockAdjustment
    {
        return $adjustment->load([
            'administrator',
            'warehouse',
            'details.product.category',
            'details.warehouseStock',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function createStockAdjustment(SiteAdministrator $administrator, array $data): WarehouseStockAdjustment
    {
        return DB::transaction(function () use ($administrator, $data): WarehouseStockAdjustment {
            Warehouse::query()->whereKey($data['warehouse_id'])->lockForUpdate()->firstOrFail();

            $adjustment = WarehouseStockAdjustment::query()->create([
                'stock_adjustment_administrator_id' => $administrator->getKey(),
                'stock_adjustment_warehouse_id' => $data['warehouse_id'],
                'stock_adjustment_code' => $this->documentCodeService->next(
                    DocumentCodeService::ADJUSTMENT,
                ),
                'stock_adjustment_note' => $data['note'],
                'stock_adjustment_datetime' => now(),
            ]);

            $products = Product::query()
                ->whereIn('product_id', collect($data['details'])->pluck('product_id'))
                ->get()
                ->keyBy('product_id');

            foreach ($data['details'] as $detail) {
                $stock = WarehouseStock::query()->firstOrCreate(
                    [
                        'warehouse_stock_warehouse_id' => $data['warehouse_id'],
                        'warehouse_stock_product_id' => $detail['product_id'],
                    ],
                    [
                        'warehouse_stock_balance' => 0,
                        'warehouse_stock_transfer_in' => 0,
                        'warehouse_stock_transfer_out' => 0,
                    ],
                );
                $stock->refresh();

                $quantity = (int) $detail['quantity'];
                $balance = (int) $stock->warehouse_stock_balance;
                $newBalance = $detail['type'] === 'in' ? $balance + $quantity : $balance - $quantity;

                if ($newBalance < 0) {
                    $product = $products->get((int) $detail['product_id']);
                    throw new ProcessException(
                        "Stok {$product?->product_name} tidak cukup untuk dikurangi sebanyak {$quantity} pcs."
                    );
                }

                $stock->update(['warehouse_stock_balance' => $newBalance]);
                $unitPrice = (int) ($detail['unit_price'] ?? $products->get((int) $detail['product_id'])?->product_customer_price ?? 0);

                WarehouseStockAdjustmentDetail::query()->create([
                    'stock_adjustment_detail_stock_adjustment_id' => $adjustment->getKey(),
                    'stock_adjustment_detail_stock_warehouse_id' => $stock->getKey(),
                    'stock_adjustment_detail_product_id' => $detail['product_id'],
                    'stock_adjustment_detail_batch_number' => $detail['batch_number'],
                    'stock_adjustment_detail_type' => $detail['type'],
                    'stock_adjustment_detail_qty' => $quantity,
                    'stock_adjustment_detail_current_price' => $unitPrice,
                    'stock_adjustment_detail_note' => $detail['note'] ?? null,
                ]);

                WarehouseStockLog::query()->create([
                    'warehouse_stock_log_warehouse_id' => $data['warehouse_id'],
                    'warehouse_stock_log_product_id' => $detail['product_id'],
                    'warehouse_stock_log_type' => $detail['type'],
                    'warehouse_stock_log_quantity' => $quantity,
                    'warehouse_stock_log_unit_price' => $unitPrice,
                    'warehouse_stock_log_balance' => $newBalance,
                    'warehouse_stock_log_note' => "Penyesuaian {$adjustment->stock_adjustment_code}: ".($detail['note'] ?? $data['note']),
                    'warehouse_stock_log_datetime' => now(),
                ]);
            }

            return $this->stockAdjustment($adjustment);
        });
    }
}
