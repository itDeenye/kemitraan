<?php

namespace App\Http\Controllers\Api\V1\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Inventory\ListWarehouseStockLogsRequest;
use App\Http\Resources\Api\V1\Admin\AdminWarehouseStockLogResource;
use App\Http\Resources\DataTableResource;
use App\Services\Inventory\AdminInventoryService;

class StockMutationController extends Controller
{
    public function __construct(private readonly AdminInventoryService $inventoryService) {}

    public function index(ListWarehouseStockLogsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->inventoryService->warehouseStockMutations($request->validated()),
            AdminWarehouseStockLogResource::class,
        ))->additional(['success' => true, 'message' => 'Riwayat perubahan stok gudang berhasil dimuat.']);
    }
}
