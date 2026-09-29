<?php

namespace App\Http\Controllers\Api\V1\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Inventory\ListWarehouseStocksRequest;
use App\Http\Resources\Api\V1\Admin\AdminWarehouseStockResource;
use App\Http\Resources\DataTableResource;
use App\Models\WarehouseStock;
use App\Services\Inventory\AdminInventoryService;

class StockController extends Controller
{
    public function __construct(private readonly AdminInventoryService $inventoryService) {}

    public function index(ListWarehouseStocksRequest $request): DataTableResource
    {
        return (new DataTableResource($this->inventoryService->warehouseStocks($request->validated()), AdminWarehouseStockResource::class))
            ->additional(['success' => true, 'message' => 'Daftar stok gudang berhasil dimuat.']);
    }

    public function show(WarehouseStock $stock): AdminWarehouseStockResource
    {
        return (new AdminWarehouseStockResource($this->inventoryService->warehouseStock($stock)))
            ->additional(['success' => true, 'message' => 'Detail stok gudang berhasil dimuat.']);
    }
}
