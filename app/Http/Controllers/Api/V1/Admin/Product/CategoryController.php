<?php

namespace App\Http\Controllers\Api\V1\Admin\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Product\ListProductCategoriesRequest;
use App\Http\Requests\Api\V1\Admin\Product\SaveProductCategoryRequest;
use App\Http\Resources\Api\V1\Admin\AdminProductCategoryResource;
use App\Http\Resources\DataTableResource;
use App\Models\ProductCategory;
use App\Services\Product\AdminProductService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(private readonly AdminProductService $productService) {}

    public function index(ListProductCategoriesRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->productService->categories($request->validated()),
            AdminProductCategoryResource::class
        ))->additional(['success' => true, 'message' => 'Daftar kategori produk berhasil dimuat.']);
    }

    public function show(ProductCategory $category): AdminProductCategoryResource
    {
        return (new AdminProductCategoryResource($this->productService->category($category)))
            ->additional(['success' => true, 'message' => 'Detail kategori produk berhasil dimuat.']);
    }

    public function store(SaveProductCategoryRequest $request): AdminProductCategoryResource
    {
        return (new AdminProductCategoryResource(
            $this->productService->createCategory($request->validated())
        ))->additional(['success' => true, 'message' => 'Kategori produk berhasil dibuat.']);
    }

    public function update(
        SaveProductCategoryRequest $request,
        ProductCategory $category
    ): AdminProductCategoryResource {
        return (new AdminProductCategoryResource(
            $this->productService->updateCategory($category, $request->validated())
        ))->additional(['success' => true, 'message' => 'Kategori produk berhasil diperbarui.']);
    }

    public function destroy(ProductCategory $category): JsonResponse
    {
        $this->productService->deleteCategory($category);

        return response()->json([
            'success' => true,
            'message' => 'Kategori produk berhasil dihapus.',
            'data' => null,
        ]);
    }
}
