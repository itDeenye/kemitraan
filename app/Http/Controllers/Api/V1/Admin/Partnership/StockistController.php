<?php

namespace App\Http\Controllers\Api\V1\Admin\Partnership;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Partnership\ListStockistsRequest;
use App\Http\Requests\Api\V1\Admin\Partnership\SaveStockistRequest;
use App\Http\Resources\Api\V1\Admin\AdminStockistResource;
use App\Http\Resources\DataTableResource;
use App\Models\Stockist;
use App\Services\Partnership\AdminStockistService;
use Illuminate\Http\JsonResponse;

class StockistController extends Controller
{
    public function __construct(private readonly AdminStockistService $stockistService) {}

    public function index(ListStockistsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->stockistService->stockists($request->validated()),
            AdminStockistResource::class
        ))->additional(['success' => true, 'message' => 'Daftar stockist berhasil dimuat.']);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Pilihan mitra Distributor untuk stokis berhasil dimuat.',
            'data' => $this->stockistService->options(),
        ]);
    }

    public function show(Stockist $stockist): AdminStockistResource
    {
        return (new AdminStockistResource($this->stockistService->stockist($stockist)))
            ->additional(['success' => true, 'message' => 'Detail stockist berhasil dimuat.']);
    }

    public function store(SaveStockistRequest $request): AdminStockistResource
    {
        return (new AdminStockistResource(
            $this->stockistService->createStockist($request->validated())
        ))->additional(['success' => true, 'message' => 'Stockist berhasil dibuat.']);
    }

    public function update(SaveStockistRequest $request, Stockist $stockist): AdminStockistResource
    {
        return (new AdminStockistResource(
            $this->stockistService->updateStockist($stockist, $request->validated())
        ))->additional(['success' => true, 'message' => 'Stockist berhasil diperbarui.']);
    }

    public function destroy(Stockist $stockist): JsonResponse
    {
        $this->stockistService->deleteStockist($stockist);

        return response()->json([
            'success' => true,
            'message' => 'Stockist berhasil dihapus.',
            'data' => null,
        ]);
    }
}
