<?php

namespace App\Http\Controllers\Api\V1\Admin\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Product\BulkUpdateProductPricesRequest;
use App\Http\Requests\Api\V1\Admin\Product\ListProductPricesRequest;
use App\Http\Requests\Api\V1\Admin\Product\SaveProductPricesRequest;
use App\Http\Resources\Api\V1\Admin\AdminProductPriceResource;
use App\Http\Resources\DataTableResource;
use App\Models\Product;
use App\Services\Product\AdminProductService;
use Illuminate\Http\JsonResponse;

class PriceController extends Controller
{
    public function __construct(private readonly AdminProductService $productService) {}

    public function index(ListProductPricesRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->productService->prices($request->validated()),
            AdminProductPriceResource::class,
        ))->additional(['success' => true, 'message' => 'Daftar harga produk berhasil dimuat.']);
    }

    public function show(Product $product): AdminProductPriceResource
    {
        return (new AdminProductPriceResource($this->productService->price($product)))
            ->additional(['success' => true, 'message' => 'Detail harga produk berhasil dimuat.']);
    }

    public function update(SaveProductPricesRequest $request, Product $product): AdminProductPriceResource
    {
        return (new AdminProductPriceResource(
            $this->productService->updatePrices($product, $request->validated()),
        ))->additional(['success' => true, 'message' => 'Harga produk berhasil diperbarui.']);
    }

    public function bulkUpdate(BulkUpdateProductPricesRequest $request): JsonResponse
    {
        $updated = $this->productService->bulkUpdatePrices($request->validated('products'));

        return response()->json([
            'success' => true,
            'message' => "Harga {$updated} produk berhasil diperbarui.",
            'data' => ['updated_count' => $updated],
        ]);
    }
}
