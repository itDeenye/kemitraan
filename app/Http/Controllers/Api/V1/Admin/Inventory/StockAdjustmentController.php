<?php

namespace App\Http\Controllers\Api\V1\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Inventory\ListStockAdjustmentsRequest;
use App\Http\Requests\Api\V1\Admin\Inventory\SaveStockAdjustmentRequest;
use App\Http\Resources\Api\V1\Admin\AdminStockAdjustmentResource;
use App\Http\Resources\DataTableResource;
use App\Models\SiteAdministrator;
use App\Models\WarehouseStockAdjustment;
use App\Services\Inventory\AdminInventoryService;

class StockAdjustmentController extends Controller
{
    public function __construct(private readonly AdminInventoryService $inventoryService) {}

    public function index(ListStockAdjustmentsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->inventoryService->stockAdjustments($request->validated()),
            AdminStockAdjustmentResource::class,
        ))->additional(['success' => true, 'message' => 'Daftar penyesuaian stok berhasil dimuat.']);
    }

    public function show(WarehouseStockAdjustment $adjustment): AdminStockAdjustmentResource
    {
        return (new AdminStockAdjustmentResource($this->inventoryService->stockAdjustment($adjustment)))
            ->additional(['success' => true, 'message' => 'Detail penyesuaian stok berhasil dimuat.']);
    }

    public function store(SaveStockAdjustmentRequest $request): AdminStockAdjustmentResource
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return (new AdminStockAdjustmentResource(
            $this->inventoryService->createStockAdjustment($administrator, $request->validated()),
        ))->additional(['success' => true, 'message' => 'Penyesuaian stok berhasil disimpan.']);
    }
}
