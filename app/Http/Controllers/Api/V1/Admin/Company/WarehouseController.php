<?php

namespace App\Http\Controllers\Api\V1\Admin\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Company\ListWarehousesRequest;
use App\Http\Requests\Api\V1\Admin\Company\SaveWarehouseRequest;
use App\Http\Resources\Api\V1\Admin\AdminWarehouseResource;
use App\Http\Resources\DataTableResource;
use App\Models\Warehouse;
use App\Services\Company\AdminCompanyService;
use Illuminate\Http\JsonResponse;

class WarehouseController extends Controller
{
    public function __construct(private readonly AdminCompanyService $companyService) {}

    public function index(ListWarehousesRequest $request): DataTableResource
    {
        return (new DataTableResource($this->companyService->warehouses($request->validated()), AdminWarehouseResource::class))
            ->additional(['success' => true, 'message' => 'Daftar gudang perusahaan berhasil dimuat.']);
    }

    public function show(Warehouse $warehouse): AdminWarehouseResource
    {
        return (new AdminWarehouseResource($this->companyService->warehouse($warehouse)))
            ->additional(['success' => true, 'message' => 'Detail gudang berhasil dimuat.']);
    }

    public function store(SaveWarehouseRequest $request): AdminWarehouseResource
    {
        return (new AdminWarehouseResource(
            $this->companyService->createWarehouse($request->validated())
        ))->additional(['success' => true, 'message' => 'Gudang berhasil dibuat.']);
    }

    public function update(
        SaveWarehouseRequest $request,
        Warehouse $warehouse
    ): AdminWarehouseResource {
        return (new AdminWarehouseResource(
            $this->companyService->updateWarehouse($warehouse, $request->validated())
        ))->additional(['success' => true, 'message' => 'Gudang berhasil diperbarui.']);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->companyService->deleteWarehouse($warehouse);

        return response()->json([
            'success' => true,
            'message' => 'Gudang berhasil dihapus.',
            'data' => null,
        ]);
    }
}
