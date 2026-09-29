<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WarehouseStockAdjustment;
use App\Models\WarehouseStockAdjustmentDetail;
use App\Models\WarehouseStockLog;
use App\Services\Document\DocumentCodeService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DevelopmentInventoryDataSeeder extends Seeder
{
    private DocumentCodeService $documentCodeService;

    public function run(DocumentCodeService $documentCodeService): void
    {
        $this->documentCodeService = $documentCodeService;
        $warehouse = Warehouse::query()->find(1);
        $products = Product::query()
            ->where('product_is_deleted', 0)
            ->orderBy('product_id')
            ->limit(3)
            ->get();

        if (! $warehouse || $products->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($products, $warehouse): void {
            $now = now();
            $stocks = $this->stocks($warehouse, $products);
            $this->logs($stocks, $products, $now);
            $this->adjustment($stocks, $products, $now);
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @return \Illuminate\Support\Collection<string, WarehouseStock>
     */
    private function stocks(Warehouse $warehouse, $products)
    {
        $stocks = collect();

        foreach ($products->values() as $index => $product) {
            $balance = 1_500 - ($index * 200);
            $stock = WarehouseStock::query()->updateOrCreate(
                [
                    'warehouse_stock_warehouse_id' => $warehouse->getKey(),
                    'warehouse_stock_product_id' => $product->getKey(),
                ],
                [
                    'warehouse_stock_balance' => $balance,
                    'warehouse_stock_transfer_in' => 0,
                    'warehouse_stock_transfer_out' => 0,
                ],
            );
            $stocks->put("{$warehouse->getKey()}-{$product->getKey()}", $stock);
        }

        return $stocks;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, WarehouseStock>  $stocks
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     */
    private function logs($stocks, $products, CarbonInterface $now): void
    {
        foreach ($products as $index => $product) {
            $stock = $stocks->get("1-{$product->getKey()}");
            $openingBalance = (int) $stock->warehouse_stock_balance + ($index === 0 ? 2 : 0);
            WarehouseStockLog::query()->updateOrCreate(
                [
                    'warehouse_stock_log_warehouse_id' => 1,
                    'warehouse_stock_log_product_id' => $product->getKey(),
                    'warehouse_stock_log_note' => 'Saldo awal data development',
                ],
                [
                    'warehouse_stock_log_type' => 'in',
                    'warehouse_stock_log_quantity' => $openingBalance,
                    'warehouse_stock_log_unit_price' => (int) $product->product_customer_price,
                    'warehouse_stock_log_balance' => $openingBalance,
                    'warehouse_stock_log_datetime' => $now->copy()->subDays(10),
                ],
            );
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<string, WarehouseStock>  $stocks
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     */
    private function adjustment($stocks, $products, CarbonInterface $now): void
    {
        $product = $products->first();
        $stock = $stocks->get("1-{$product->getKey()}");
        $key = 'DEV-ADJ-WH-001';
        $adjustment = $this->developmentAdjustment($key) ?? new WarehouseStockAdjustment;
        if (! $adjustment->exists) {
            $adjustment->stock_adjustment_code = $this->documentCodeService->next(
                DocumentCodeService::ADJUSTMENT,
                DocumentCodeService::deterministicUniqueSuffix($key),
            );
        }
        $adjustment->fill([
            'stock_adjustment_administrator_id' => 1,
            'stock_adjustment_warehouse_id' => 1,
            'stock_adjustment_note' => 'Penyesuaian contoh barang rusak.',
            'stock_adjustment_datetime' => $now->copy()->subDays(3),
        ])->save();

        WarehouseStockAdjustmentDetail::query()->updateOrCreate(
            [
                'stock_adjustment_detail_stock_adjustment_id' => $adjustment->getKey(),
                'stock_adjustment_detail_product_id' => $product->getKey(),
            ],
            [
                'stock_adjustment_detail_stock_warehouse_id' => $stock->getKey(),
                'stock_adjustment_detail_type' => 'out',
                'stock_adjustment_detail_qty' => 2,
                'stock_adjustment_detail_current_price' => (int) $product->product_customer_price,
                'stock_adjustment_detail_note' => 'Kemasan rusak di warehouse.',
            ],
        );

        WarehouseStockLog::query()->updateOrCreate(
            [
                'warehouse_stock_log_warehouse_id' => 1,
                'warehouse_stock_log_product_id' => $product->getKey(),
                'warehouse_stock_log_note' => 'Penyesuaian Stok: Kemasan rusak di warehouse.',
            ],
            [
                'warehouse_stock_log_type' => 'out',
                'warehouse_stock_log_quantity' => 2,
                'warehouse_stock_log_unit_price' => (int) $product->product_customer_price,
                'warehouse_stock_log_balance' => (int) $stock->warehouse_stock_balance,
                'warehouse_stock_log_datetime' => $now->copy()->subDays(3),
            ],
        );
    }

    private function developmentAdjustment(string $key): ?WarehouseStockAdjustment
    {
        return WarehouseStockAdjustment::query()
            ->where('stock_adjustment_code', $key)
            ->orWhere(
                'stock_adjustment_code',
                'like',
                '%/'.DocumentCodeService::deterministicUniqueSuffix($key),
            )
            ->first();
    }

}
