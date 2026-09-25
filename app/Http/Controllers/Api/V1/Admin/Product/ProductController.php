<?php

namespace App\Http\Controllers\Api\V1\Admin\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Product\ListProductsRequest;
use App\Http\Requests\Api\V1\Admin\Product\SaveProductRequest;
use App\Http\Resources\Api\V1\Admin\AdminProductResource;
use App\Http\Resources\DataTableResource;
use App\Models\Product;
use App\Services\Product\AdminProductService;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(private readonly AdminProductService $productService) {}

    public function index(ListProductsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->productService->products($request->validated()),
            AdminProductResource::class
        ))->additional(['success' => true, 'message' => 'Daftar produk berhasil dimuat.']);
    }

    public function show(Product $product): AdminProductResource
    {
        return (new AdminProductResource($this->productService->product($product)))
            ->additional(['success' => true, 'message' => 'Detail produk berhasil dimuat.']);
    }

    public function store(SaveProductRequest $request): AdminProductResource
    {
        return (new AdminProductResource(
            $this->productService->createProduct($request->validated())
        ))->additional(['success' => true, 'message' => 'Produk berhasil dibuat.']);
    }

    public function update(SaveProductRequest $request, Product $product): AdminProductResource
    {
        return (new AdminProductResource(
            $this->productService->updateProduct($product, $request->validated())
        ))->additional(['success' => true, 'message' => 'Produk berhasil diperbarui.']);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->productService->deleteProduct($product);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus.',
            'data' => null,
        ]);
    }
}
